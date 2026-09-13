<?php

namespace App\Services;

use App\Models\WaDevice;
use App\Models\WaMessage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Jembatan antara backend SIKEBUT dan service WA Gateway (Node.js + Baileys).
 *
 * - Gateway berjalan di 127.0.0.1:{WA_GATEWAY_PORT} (default 3001) — internal saja.
 * - Semua request memakai header X-Gateway-Key (WA_GATEWAY_KEY, harus sama di .env backend & gateway).
 * - Saat kirim pesan: disimpan dulu di dev.wa_messages (status pending) lalu POST /send.
 *   Gateway yang memutuskan device mana yang dipakai (fallback utama → backup otomatis).
 * - Callback dari gateway (status device / delivery report) diproses di WaGatewayController.
 */
class WaGatewayService
{
    /**
     * URL base gateway tanpa slash di akhir.
     */
    public static function baseUrl(): string
    {
        $url = (string) config('services.wa_gateway.url', env('WA_GATEWAY_URL', 'http://127.0.0.1:3001'));

        return rtrim($url, '/');
    }

    /**
     * API key bersama backend ↔ gateway.
     */
    public static function apiKey(): string
    {
        return (string) config('services.wa_gateway.key', env('WA_GATEWAY_KEY', 'sikebut-wa-key-ganti-di-produksi'));
    }

    /**
     * Kirim request autentikasi ke gateway.
     *
     * @param  array<string, mixed>|null  $body
     * @return array{ok: bool, status: int, data: array<string, mixed>}
     */
    private static function request(string $method, string $path, ?array $body = null, int $timeout = 10): array
    {
        try {
            $http = Http::withHeaders([
                'X-Gateway-Key' => self::apiKey(),
                'Accept' => 'application/json',
            ])->timeout($timeout);

            $response = match (strtoupper($method)) {
                'GET' => $http->get(self::baseUrl().$path),
                'POST' => $http->post(self::baseUrl().$path, $body ?? []),
                'DELETE' => $http->delete(self::baseUrl().$path),
                default => throw new \InvalidArgumentException("Method {$method} tidak didukung"),
            };

            $data = $response->json() ?? [];

            return [
                'ok' => $response->successful() && ($data['ok'] ?? false) !== false,
                'status' => $response->status(),
                'data' => $data,
            ];
        } catch (ConnectionException $e) {
            Log::warning('[WA-GATEWAY] Tidak dapat menjangkau gateway: '.$e->getMessage());

            return ['ok' => false, 'status' => 0, 'data' => ['error' => 'Gateway tidak terjangkau']];
        }
    }

    /**
     * Cek apakah gateway hidup (health check).
     */
    public static function isReachable(): bool
    {
        $res = self::request('GET', '/health', null, 3);

        return $res['ok'];
    }

    /**
     * Daftar device + status dari gateway.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function devices(): array
    {
        $res = self::request('GET', '/devices', null, 5);

        return $res['ok'] ? ($res['data']['devices'] ?? []) : [];
    }

    /**
     * Ambil QR untuk scan (device belum terhubung).
     *
     * @return array<string, mixed>
     */
    public static function qr(string $deviceId): array
    {
        // Timeout panjang (35 dtk) — koneksi Baileys ke server WA kadang lambat
        // untuk device baru, dan gateway menunggu QR hingga ~30 dtk.
        return self::request('GET', '/devices/'.rawurlencode($deviceId).'/qr', null, 35);
    }

    /**
     * Daftarkan device baru di gateway.
     *
     * @return array<string, mixed>
     */
    public static function registerDevice(string $deviceId, ?int $priority = null): array
    {
        $body = ['id' => $deviceId];
        if ($priority !== null) {
            $body['priority'] = $priority;
        }

        return self::request('POST', '/devices', $body, 5);
    }

    /**
     * Logout device di gateway (sesi tetap tersimpan).
     *
     * @return array<string, mixed>
     */
    public static function logoutDevice(string $deviceId): array
    {
        return self::request('POST', '/devices/'.rawurlencode($deviceId).'/logout', [], 10);
    }

    /**
     * Hapus device + folder sesi di gateway (wajib scan ulang).
     *
     * @return array<string, mixed>
     */
    public static function deleteDevice(string $deviceId): array
    {
        return self::request('DELETE', '/devices/'.rawurlencode($deviceId), null, 10);
    }

    /**
     * Ubah prioritas device (0 = utama, 1 = backup, dst).
     *
     * @return array<string, mixed>
     */
    public static function setPriority(string $deviceId, int $priority): array
    {
        return self::request('POST', '/devices/'.rawurlencode($deviceId).'/priority', ['priority' => $priority], 5);
    }

    /**
     * Kirim pesan WA melalui gateway dengan fallback otomatis.
     * Pesan dicatat di dev.wa_messages (status pending → sent/failed via callback).
     *
     * @param  array<string, mixed>  $options
     */
    public static function send(
        string $nomorTujuan,
        string $pesan,
        array $options = []
    ): array {
        $nomor = preg_replace('/\D/', '', (string) $nomorTujuan) ?? '';
        if ($nomor === '' || mb_strlen($nomor) < 10) {
            return ['ok' => false, 'error' => 'Nomor WhatsApp tujuan tidak valid'];
        }

        $messageId = (string) Str::uuid();

        $log = WaMessage::create([
            'user_id' => $options['user_id'] ?? null,
            'nomor_tujuan' => $nomor,
            'pesan' => $pesan,
            'status' => 'pending',
            'identifikasi_kebutuhan_id' => $options['identifikasi_kebutuhan_id'] ?? null,
            'message_id' => $messageId,
        ]);

        $res = self::request('POST', '/send', [
            'nomor' => $nomor,
            'pesan' => $pesan,
            'messageId' => $messageId,
        ], 20);

        if (! $res['ok']) {
            $error = $res['data']['error'] ?? ($res['status'] === 0 ? 'Gateway tidak terjangkau' : 'Gagal dikirim gateway');

            $log->update([
                'status' => 'failed',
                'error' => is_string($error) ? $error : json_encode($error),
            ]);

            return ['ok' => false, 'error' => $error];
        }

        // Gateway akan mengirim delivery report via callback → status jadi sent.
        // Di sini kita optimis set sent (device_id dari gateway).
        $log->update([
            'status' => 'sent',
            'device_id' => isset($res['data']['deviceId']) ? (string) $res['data']['deviceId'] : null,
            'sent_at' => now(),
        ]);

        return ['ok' => true, 'message_id' => $messageId];
    }

    /**
     * Proses callback dari gateway: heartbeat status device & delivery report.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function handleCallback(array $payload): void
    {
        $type = (string) ($payload['type'] ?? '');

        if ($type === 'heartbeat') {
            self::syncDevices($payload['devices'] ?? []);
        }

        if ($type === 'status') {
            self::upsertDevice([
                'device_id' => (string) ($payload['id'] ?? ''),
                'status' => (string) ($payload['status'] ?? 'disconnected'),
                'nomor' => isset($payload['nomor']) ? (string) $payload['nomor'] : null,
            ]);
        }

        if ($type === 'delivery' && ! empty($payload['messageId'])) {
            $message = WaMessage::query()
                ->where('message_id', (string) $payload['messageId'])
                ->first();

            if ($message) {
                $message->update([
                    'status' => ($payload['ok'] ?? false) ? 'sent' : 'failed',
                    'device_id' => isset($payload['deviceId']) ? (string) $payload['deviceId'] : $message->device_id,
                    'error' => isset($payload['error']) ? (string) $payload['error'] : $message->error,
                    'sent_at' => ($payload['ok'] ?? false) ? now() : $message->sent_at,
                ]);
            }
        }
    }

    /**
     * Sinkronkan daftar device dari heartbeat gateway ke tabel wa_devices.
     *
     * @param  array<int, mixed>  $devices
     */
    private static function syncDevices(array $devices): void
    {
        foreach ($devices as $d) {
            if (! is_array($d) || empty($d['id'])) {
                continue;
            }

            self::upsertDevice([
                'device_id' => (string) $d['id'],
                'status' => (string) ($d['status'] ?? 'disconnected'),
                'nomor' => isset($d['nomor']) ? (string) $d['nomor'] : null,
            ]);
        }
    }

    /**
     * Upsert satu device dari data gateway.
     *
     * @param  array<string, mixed>  $data
     */
    private static function upsertDevice(array $data): void
    {
        $deviceId = (string) ($data['device_id'] ?? '');
        if ($deviceId === '') {
            return;
        }

        $device = WaDevice::query()->firstOrNew(['device_id' => $deviceId]);

        $device->device_id = $deviceId;
        $device->status = (string) ($data['status'] ?? $device->status ?? 'disconnected');
        $device->last_heartbeat = now();

        if (! empty($data['nomor'])) {
            $device->nomor = (string) $data['nomor'];
        }
        if (empty($device->nama)) {
            $device->nama = $deviceId;
        }

        $device->save();
    }
}
