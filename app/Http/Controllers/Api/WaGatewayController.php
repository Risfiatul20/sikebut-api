<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WaDevice;
use App\Models\WaMessage;
use App\Services\WaGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaGatewayController extends Controller
{
    /**
     * Hanya Admin yang boleh mengelola gateway.
     */
    private function authorizeAdmin(Request $request): void
    {
        abort_unless(
            strtoupper((string) $request->user()?->role) === 'ADMIN',
            403,
            'Hanya Admin yang dapat mengelola WhatsApp Gateway.'
        );
    }

    /**
     * Ringkasan status gateway + daftar device (admin).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $devices = WaDevice::query()
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->map(fn (WaDevice $d) => [
                'id' => $d->id,
                'device_id' => $d->device_id,
                'nama' => $d->nama,
                'nomor' => $d->nomor,
                'status' => $d->status,
                'is_active' => $d->is_active,
                'priority' => $d->priority,
                'last_heartbeat' => $d->last_heartbeat?->toISOString(),
                'created_at' => $d->created_at?->toISOString(),
            ]);

        return response()->json([
            'data' => [
                'reachable' => WaGatewayService::isReachable(),
                'gateway_url' => WaGatewayService::baseUrl(),
                'devices' => $devices,
            ],
        ]);
    }

    /**
     * Daftarkan device baru (admin) — lalu user scan QR dari halaman.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'device_id' => 'required|string|regex:/^[a-zA-Z0-9_-]{2,50}$/',
            'nama' => 'nullable|string|max:100',
            'priority' => 'nullable|integer|min:0|max:99',
        ]);

        $deviceId = $validated['device_id'];

        $device = WaDevice::query()->firstOrNew(['device_id' => $deviceId]);
        $device->nama = $validated['nama'] ?? $device->nama ?? $deviceId;
        if (array_key_exists('priority', $validated)) {
            $device->priority = (int) $validated['priority'];
        }
        $device->save();

        WaGatewayService::registerDevice($deviceId, $device->priority);

        return response()->json([
            'message' => 'Device WhatsApp ditambahkan. Silakan scan QR.',
            'data' => $device,
        ], 201);
    }

    /**
     * Ambil QR untuk scan (admin) — memanggil gateway.
     */
    public function qr(Request $request, string $deviceId): JsonResponse
    {
        $this->authorizeAdmin($request);

        $device = WaDevice::query()->where('device_id', $deviceId)->firstOrFail();
        $res = WaGatewayService::qr($device->device_id);

        if (! $res['ok'] && $res['status'] !== 200) {
            return response()->json([
                'message' => $res['data']['error'] ?? 'Gagal mengambil QR dari gateway',
            ], 502);
        }

        return response()->json(['data' => $res['data']]);
    }

    /**
     * Logout device (admin) — sesi tersimpan, harus scan ulang saat dipakai lagi.
     */
    public function logout(Request $request, string $deviceId): JsonResponse
    {
        $this->authorizeAdmin($request);

        $device = WaDevice::query()->where('device_id', $deviceId)->firstOrFail();
        WaGatewayService::logoutDevice($device->device_id);
        $device->update(['status' => 'logged_out']);

        return response()->json(['message' => 'Device WhatsApp di-logout']);
    }

    /**
     * Hapus device + sesi (admin) — wajib scan ulang dari awal.
     */
    public function destroy(Request $request, string $deviceId): JsonResponse
    {
        $this->authorizeAdmin($request);

        $device = WaDevice::query()->where('device_id', $deviceId)->firstOrFail();
        WaGatewayService::deleteDevice($device->device_id);
        $device->delete();

        return response()->json(['message' => 'Device WhatsApp dihapus']);
    }

    /**
     * Ubah prioritas device (admin): 0 = utama, 1 = backup, dst.
     */
    public function setPriority(Request $request, string $deviceId): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'priority' => 'required|integer|min:0|max:99',
        ]);

        $device = WaDevice::query()->where('device_id', $deviceId)->firstOrFail();
        $device->update(['priority' => (int) $validated['priority']]);

        WaGatewayService::setPriority($device->device_id, (int) $validated['priority']);

        return response()->json(['message' => 'Prioritas device diperbarui', 'data' => $device]);
    }

    /**
     * Kirim pesan tes (admin) — menguji alur notifikasi WA end-to-end.
     */
    public function testSend(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'nomor' => 'required|string|max:20',
            'pesan' => 'required|string|max:1000',
        ]);

        $res = WaGatewayService::send($validated['nomor'], $validated['pesan'], [
            'user_id' => $request->user()->id,
        ]);

        if (! $res['ok']) {
            return response()->json([
                'message' => 'Gagal mengirim WA: '.($res['error'] ?? 'unknown'),
            ], 502);
        }

        return response()->json(['message' => 'Pesan WA terkirim']);
    }

    /**
     * Log pengiriman pesan WA (admin).
     */
    public function messages(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $query = WaMessage::query()
            ->with(['user:id,nama,username', 'kebutuhan:id,nama_paket'])
            ->latest('id');

        $status = $request->query('status');
        if ($status) {
            $query->where('status', $status);
        }

        $limit = (int) $request->query('limit', 30);
        $items = $query->limit(min(max($limit, 1), 100))->get();

        return response()->json([
            'data' => $items->map(fn (WaMessage $m) => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'nama_user' => $m->user?->nama,
                'nomor_tujuan' => $m->nomor_tujuan,
                'pesan' => $m->pesan,
                'status' => $m->status,
                'device_id' => $m->device_id,
                'error' => $m->error,
                'identifikasi_kebutuhan_id' => $m->identifikasi_kebutuhan_id,
                'nama_paket' => $m->kebutuhan?->nama_paket,
                'sent_at' => $m->sent_at?->toISOString(),
                'created_at' => $m->created_at?->toISOString(),
            ]),
        ]);
    }

    /**
     * Callback dari gateway (tidak butuh auth Sanctum — pakai X-Gateway-Key + secret).
     */
    public function callback(Request $request): JsonResponse
    {
        $key = $request->header('X-Gateway-Key');
        if (! is_string($key) || ! hash_equals(WaGatewayService::apiKey(), $key)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        WaGatewayService::handleCallback($request->all());

        return response()->json(['message' => 'OK']);
    }
}