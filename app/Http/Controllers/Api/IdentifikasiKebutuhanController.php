<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IdentifikasiKebutuhan\StoreIdentifikasiKebutuhanRequest;
use App\Http\Requests\IdentifikasiKebutuhan\UpdateIdentifikasiKebutuhanRequest;
use App\Http\Resources\IdentifikasiKebutuhanResource;
use App\Http\Resources\IdentifikasiKebutuhanRiwayatResource;
use App\Models\IdentifikasiKebutuhan;
use App\Models\IdentifikasiKebutuhanRiwayat;
use App\Models\Notification;
use App\Models\User;
use App\Services\WaGatewayService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class IdentifikasiKebutuhanController extends Controller
{
    /**
     * @var list<string>
     */
    private const RELATIONS = [
        'anggaran.standarHarga',
        'anggaran.sipdPenetapan.akun.indikator',
        'anggaran.sipdPenetapan',
        'pembuat',
        'skpd',
        'program',
        'kegiatan',
        'subKegiatan',
        'rkbmdItems',
    ];

    /**
     * Scope to the caller's SKPD, and to mapped sub kegiatan when role is PPK.
     *
     * @param  bool  $respectQuery  allow kode_skpd override, only safe for listing
     * @return Builder<IdentifikasiKebutuhan>
     */
    private function scopedQuery(Request $request, bool $respectQuery = true): Builder
    {
        $user = $request->user();
        $param = (string) ($respectQuery ? ($request->query('kode_skpd') ?? '') : '');
        $kodeSkpd = $param !== '' ? $param : $user?->kode_skpd;
        $ppkCodes = $user?->ppkSubKegiatanCodes();

        $query = IdentifikasiKebutuhan::query();

        if ($kodeSkpd) {
            $query->where('kode_skpd', $kodeSkpd);
        }

        if ($ppkCodes !== null) {
            $query->whereIn('kode_sub_kegiatan', $ppkCodes);
        }

        // Verifikator boleh melihat paket Diajukan (menunggu review), Disetujui, dan
        // Perlu Perbaikan. Paket Draft (belum final) milik PPK TIDAK boleh terlihat/diubah.
        if ($user && strtoupper((string) $user->role) === 'VERIFIKATOR') {
            $query->whereIn('status_review', [
                self::STATUS_DIAJUKAN,
                self::STATUS_DISETUJUI,
                self::STATUS_PERLU_PERBAIKAN,
            ]);
        }

        return $query;
    }

    /**
     * List identifikasi kebutuhan with nested anggaran.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $this->scopedQuery($request)->with(self::RELATIONS);

        $search = $request->query('search');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_paket', 'ilike', "%{$search}%")
                    ->orWhere('kode_sub_kegiatan', 'ilike', "%{$search}%");
            });
        }

        foreach (['status_review', 'cara_pengadaan', 'jenis_pengadaan', 'kode_program', 'kode_kegiatan', 'kode_sub_kegiatan'] as $field) {
            $value = $request->query($field);
            if ($value) {
                $query->where($field, $value);
            }
        }

        $requestedSort = $request->query('sort_by');
        $allowedSorts = ['id', 'nama_paket', 'status_review', 'created_at', 'updated_at'];
        $sortBy = in_array($requestedSort, $allowedSorts, true) ? $requestedSort : 'id';
        $sortDirection = strtolower((string) $request->query('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = (int) $request->query('per_page', 15);

        $query->orderBy($sortBy, $sortDirection);

        $data = $perPage > 0 ? $query->paginate($perPage) : $query->get();

        return IdentifikasiKebutuhanResource::collection($data);
    }

    /**
     * Sinkronkan detail RKBMD (tabel identifikasi_kebutuhan_rkbmd) dari form_data.
     * Pola sama seperti anggaran: hapus semua → tulis ulang dari payload terbaru.
     * `id_pengadaan` menampung id RKBMD (id_pengadaan untuk jenis pengadaan,
     * id_pemeliharaan untuk jenis pemeliharaan) — dibedakan kolom `jenis_rkbmd`.
     *
     * @param  array<string, mixed>  $formData
     */
    private function sinkronRkbmdItems(IdentifikasiKebutuhan $kebutuhan, array $formData): void
    {
        $kebutuhan->rkbmdItems()->delete();

        $perAnggaran = $formData['rkbmd_per_anggaran'] ?? [];
        if (! is_array($perAnggaran)) {
            return;
        }

        $rows = [];
        foreach ($perAnggaran as $item) {
            $items = $item['items'] ?? [];
            $kodeStandar = $item['kode_standar_harga'] ?? null;
            $kodeRekening = $item['kode_rekening'] ?? null;

            foreach ($items as $barang) {
                $sumber = $barang['sumber'] ?? null;
                if ($sumber !== 'pengadaan' && $sumber !== 'pemeliharaan') {
                    continue;
                }

                // ID item RKBMD dari format "pengadaan-123" / "pemeliharaan-456".
                $idRkbmd = null;
                $idStr = $barang['id'] ?? null;
                if (is_string($idStr)) {
                    $parts = explode('-', $idStr, 2);
                    if (isset($parts[1]) && ctype_digit($parts[1])) {
                        $idRkbmd = (int) $parts[1];
                    }
                }

                $rows[] = [
                    'identifikasi_kebutuhan_id' => $kebutuhan->id,
                    'kode_standar' => is_string($kodeStandar) ? $kodeStandar : null,
                    'kode_rekening' => is_string($kodeRekening) ? $kodeRekening : null,
                    'id_pengadaan' => $idRkbmd,
                    'jenis_rkbmd' => $sumber,
                    'jumlah' => isset($barang['jumlah']) ? (int) $barang['jumlah'] : 0,
                ];
            }
        }

        if ($rows !== []) {
            $kebutuhan->rkbmdItems()->insert($rows);
        }
    }

    /**
     * Persist a new identifikasi kebutuhan together with its anggaran rows.
     */
    public function store(StoreIdentifikasiKebutuhanRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $kebutuhan = DB::transaction(function () use ($validated, $request) {
            $this->assertAnggaranDalamSisa($validated['anggaran'] ?? []);

            $kebutuhan = IdentifikasiKebutuhan::create([
                ...collect($validated)->except('anggaran')->all(),
                'user_id' => $request->user()->id,
                'status_review' => $validated['status_review'] ?? 'Draft',
            ]);

            foreach ($validated['anggaran'] as $anggaran) {
                $kebutuhan->anggaran()->create($anggaran);
            }

            $this->sinkronRkbmdItems($kebutuhan, $validated['form_data'] ?? []);

            $this->catatRiwayat(
                $kebutuhan,
                null,
                (string) ($validated['status_review'] ?? 'Draft'),
                'Paket identifikasi kebutuhan dibuat.',
                $request->user()->id
            );

            // "Ajukan Langsung" dari wizard → langsung berstatus Diajukan → beri tahu Verifikator
            if (($validated['status_review'] ?? 'Draft') === self::STATUS_DIAJUKAN) {
                $this->notifyReviewers(
                    $kebutuhan,
                    'Paket "'.$kebutuhan->nama_paket.'" diajukan untuk review dan menunggu verifikasi.'
                );
            }

            return $kebutuhan;
        });

        $kebutuhan->load(self::RELATIONS);

        return response()->json([
            'message' => 'Identifikasi kebutuhan created successfully',
            'data' => new IdentifikasiKebutuhanResource($kebutuhan),
        ], 201);
    }

    /**
     * Show one identifikasi kebutuhan with its anggaran rows.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $kebutuhan = $this->scopedQuery($request, false)->with(self::RELATIONS)->findOrFail($id);

        return response()->json([
            'data' => new IdentifikasiKebutuhanResource($kebutuhan),
        ]);
    }

    /**
     * Update the header and reconcile the anggaran collection.
     */
    public function update(UpdateIdentifikasiKebutuhanRequest $request, int $id): JsonResponse
    {
        $kebutuhan = $this->scopedQuery($request, false)->findOrFail($id);
        $validated = $request->validated();

        DB::transaction(function () use ($kebutuhan, $validated) {
            $this->assertAnggaranDalamSisa($validated['anggaran'] ?? [], (int) $kebutuhan->id);

            $header = collect($validated)->except('anggaran')->all();

            if ($header !== []) {
                $statusSebelum = $kebutuhan->status_review;
                $kebutuhan->update($header);

                if (isset($header['status_review']) && $header['status_review'] !== $statusSebelum) {
                    $this->catatRiwayat(
                        $kebutuhan,
                        $statusSebelum,
                        (string) $header['status_review'],
                        'Status paket diubah saat pembaruan data.',
                        $kebutuhan->user_id
                    );
                }
            }

            if (array_key_exists('anggaran', $validated)) {
                $owned = $kebutuhan->anggaran()->pluck('id')->all();
                $keep = [];

                foreach ($validated['anggaran'] as $anggaran) {
                    $row = collect($anggaran)->except('id')->all();

                    if (isset($anggaran['id']) && in_array((int) $anggaran['id'], $owned, true)) {
                        $kebutuhan->anggaran()->whereKey((int) $anggaran['id'])->update($row);
                        $keep[] = (int) $anggaran['id'];

                        continue;
                    }

                    $keep[] = $kebutuhan->anggaran()->create($row)->id;
                }

                $kebutuhan->anggaran()->whereNotIn('id', $keep)->delete();
            }

            if (array_key_exists('form_data', $validated)) {
                $this->sinkronRkbmdItems($kebutuhan, $validated['form_data'] ?? []);
            }
        });

        $kebutuhan->load(self::RELATIONS);

        return response()->json([
            'message' => 'Identifikasi kebutuhan updated successfully',
            'data' => new IdentifikasiKebutuhanResource($kebutuhan),
        ]);
    }

    /**
     * Remove an identifikasi kebutuhan; anggaran rows cascade with it.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $kebutuhan = $this->scopedQuery($request, false)->findOrFail($id);
        $kebutuhan->delete();

        return response()->json([
            'message' => 'Identifikasi kebutuhan deleted successfully',
        ]);
    }

    /**
     * Status values (sistem of record).
     */
    private const STATUS_DRAFT = 'Draft';

    private const STATUS_DIAJUKAN = 'Diajukan';

    private const STATUS_DISETUJUI = 'Disetujui';

    private const STATUS_PERLU_PERBAIKAN = 'Perlu Perbaikan';

    /**
     * Role yang boleh men-review (setujui / kembalikan / simpan catatan).
     */
    private function isReviewer(Request $request): bool
    {
        return in_array(strtoupper((string) $request->user()?->role), ['VERIFIKATOR', 'ADMIN'], true);
    }

    /**
     * Role yang boleh mengajukan paket (PPK atau Admin).
     */
    private function isPengaju(Request $request): bool
    {
        return in_array(strtoupper((string) $request->user()?->role), ['PPK', 'ADMIN'], true);
    }

    /**
     * Submit identifikasi kebutuhan for review (Draft / Perlu Perbaikan → Diajukan).
     */
    public function submit(Request $request, int $id): JsonResponse
    {
        if (! $this->isPengaju($request)) {
            return response()->json([
                'message' => 'Hanya PPK atau Admin yang dapat mengajukan paket untuk review.',
            ], 403);
        }

        $kebutuhan = $this->scopedQuery($request, false)->findOrFail($id);

        if (! in_array($kebutuhan->status_review, [self::STATUS_DRAFT, self::STATUS_PERLU_PERBAIKAN], true)) {
            return response()->json([
                'message' => 'Hanya paket berstatus Draft atau Perlu Perbaikan yang dapat diajukan.',
            ], 422);
        }

        $statusSebelum = $kebutuhan->status_review;
        $kebutuhan->update(['status_review' => self::STATUS_DIAJUKAN]);
        $this->catatRiwayat($kebutuhan, $statusSebelum, self::STATUS_DIAJUKAN, null, $request->user()->id);
        $this->notifyReviewers($kebutuhan, 'Paket "'.$kebutuhan->nama_paket.'" diajukan untuk review dan menunggu verifikasi.');
        $kebutuhan->load(self::RELATIONS);

        return response()->json([
            'message' => 'Identifikasi kebutuhan berhasil diajukan untuk review',
            'data' => new IdentifikasiKebutuhanResource($kebutuhan),
        ]);
    }

    /**
     * Verify / approve identifikasi kebutuhan (Diajukan → Disetujui).
     */
    public function verify(Request $request, int $id): JsonResponse
    {
        if (! $this->isReviewer($request)) {
            return response()->json([
                'message' => 'Hanya Verifikator atau Admin yang dapat menyetujui paket.',
            ], 403);
        }

        $kebutuhan = $this->scopedQuery($request, false)->findOrFail($id);

        if ($kebutuhan->status_review !== self::STATUS_DIAJUKAN) {
            return response()->json([
                'message' => 'Hanya paket berstatus Diajukan yang dapat disetujui.',
            ], 422);
        }

        $validated = $request->validate([
            'catatan_reviewer' => 'nullable|string',
            'catatan_reviewer_detail' => 'nullable|array',
        ]);

        $statusSebelum = $kebutuhan->status_review;
        $kebutuhan->update([
            'status_review' => self::STATUS_DISETUJUI,
            // Paket final (Disetujui) tidak lagi menampilkan catatan review lama —
            // catatan global & per-field dibersihkan. Jejak audit tetap tersimpan
            // di tabel riwayat (identifikasi_kebutuhan_riwayat) untuk keperluan audit.
            'catatan_reviewer' => null,
            'catatan_reviewer_detail' => null,
        ]);
        $this->catatRiwayat(
            $kebutuhan,
            $statusSebelum,
            self::STATUS_DISETUJUI,
            $validated['catatan_reviewer'] ?? null,
            $request->user()->id
        );
        $this->notifyPembuat($kebutuhan, 'Paket "'.$kebutuhan->nama_paket.'" telah DISETUJUI.');

        $kebutuhan->load(self::RELATIONS);

        return response()->json([
            'message' => 'Identifikasi kebutuhan berhasil disetujui',
            'data' => new IdentifikasiKebutuhanResource($kebutuhan),
        ]);
    }

    /**
     * Return identifikasi kebutuhan for revision (Diajukan → Perlu Perbaikan).
     */
    public function returnForRevision(Request $request, int $id): JsonResponse
    {
        if (! $this->isReviewer($request)) {
            return response()->json([
                'message' => 'Hanya Verifikator atau Admin yang dapat mengembalikan paket.',
            ], 403);
        }

        $kebutuhan = $this->scopedQuery($request, false)->findOrFail($id);

        if ($kebutuhan->status_review !== self::STATUS_DIAJUKAN) {
            return response()->json([
                'message' => 'Hanya paket berstatus Diajukan yang dapat dikembalikan untuk perbaikan.',
            ], 422);
        }

        $validated = $request->validate([
            'catatan_reviewer' => 'required|string',
            'catatan_reviewer_detail' => 'nullable|array',
        ]);

        $statusSebelum = $kebutuhan->status_review;
        $kebutuhan->update([
            'status_review' => self::STATUS_PERLU_PERBAIKAN,
            'catatan_reviewer' => $validated['catatan_reviewer'],
            'catatan_reviewer_detail' => $validated['catatan_reviewer_detail'] ?? $kebutuhan->catatan_reviewer_detail,
        ]);
        $this->catatRiwayat(
            $kebutuhan,
            $statusSebelum,
            self::STATUS_PERLU_PERBAIKAN,
            $validated['catatan_reviewer'],
            $request->user()->id
        );
        $this->notifyPembuat($kebutuhan, 'Paket "'.$kebutuhan->nama_paket.'" DIKEMBALIKAN untuk perbaikan: '.($validated['catatan_reviewer'] ?? ''));

        $kebutuhan->load(self::RELATIONS);

        return response()->json([
            'message' => 'Identifikasi kebutuhan berhasil dikembalikan untuk perbaikan',
            'data' => new IdentifikasiKebutuhanResource($kebutuhan),
        ]);
    }

    /**
     * Simpan catatan reviewer tanpa mengubah status (Diajukan tetap Diajukan).
     */
    public function note(Request $request, int $id): JsonResponse
    {
        if (! $this->isReviewer($request)) {
            return response()->json([
                'message' => 'Hanya Verifikator atau Admin yang dapat menyimpan catatan.',
            ], 403);
        }

        $kebutuhan = $this->scopedQuery($request, false)->findOrFail($id);

        $validated = $request->validate([
            'catatan_reviewer' => 'nullable|string',
            'catatan_reviewer_detail' => 'nullable|array',
        ]);

        $kebutuhan->update([
            'catatan_reviewer' => $validated['catatan_reviewer'] ?? $kebutuhan->catatan_reviewer,
            'catatan_reviewer_detail' => $validated['catatan_reviewer_detail'] ?? $kebutuhan->catatan_reviewer_detail,
        ]);
        $this->catatRiwayat(
            $kebutuhan,
            $kebutuhan->status_review,
            $kebutuhan->status_review,
            $validated['catatan_reviewer'] ?? null,
            $request->user()->id
        );

        $kebutuhan->load(self::RELATIONS);

        return response()->json([
            'message' => 'Catatan reviewer berhasil disimpan (status tidak berubah)',
            'data' => new IdentifikasiKebutuhanResource($kebutuhan),
        ]);
    }

    /**
     * Kirim notifikasi ke semua Verifikator & Admin (saat paket diajukan).
     * Web notifikasi selalu dibuat; WA dikirim bila user punya nomor (info.no_hp).
     */
    private function notifyReviewers(IdentifikasiKebutuhan $kebutuhan, string $pesan): void
    {
        $users = User::query()
            ->whereIn('role', ['Verifikator', 'verifikator', 'Admin', 'admin'])
            ->get(['id', 'info']);

        foreach ($users as $user) {
            Notification::create([
                'user_id' => $user->id,
                'tipe' => 'paket_diajukan',
                'pesan' => $pesan,
                'identifikasi_kebutuhan_id' => $kebutuhan->id,
            ]);

            $this->kirimWaJikaAdaNomor($user, $pesan, $kebutuhan->id);
        }
    }

    /**
     * Kirim notifikasi ke pembuat paket (saat disetujui / dikembalikan).
     * Web notifikasi selalu dibuat; WA dikirim bila user punya nomor (info.no_hp).
     */
    private function notifyPembuat(IdentifikasiKebutuhan $kebutuhan, string $pesan): void
    {
        if (! $kebutuhan->user_id) {
            return;
        }

        $pembuat = User::query()->find($kebutuhan->user_id);
        if (! $pembuat) {
            return;
        }

        Notification::create([
            'user_id' => $pembuat->id,
            'tipe' => 'paket_direview',
            'pesan' => $pesan,
            'identifikasi_kebutuhan_id' => $kebutuhan->id,
        ]);

        $this->kirimWaJikaAdaNomor($pembuat, $pesan, $kebutuhan->id);
    }

    /**
     * Kirim pesan WA bila user punya nomor WhatsApp (info.no_hp).
     * Gagal kirim WA TIDAK menggagalkan alur utama (web notifikasi tetap jalan).
     */
    private function kirimWaJikaAdaNomor(User $user, string $pesan, ?int $identifikasiKebutuhanId = null): void
    {
        $info = is_array($user->info) ? $user->info : [];
        $nomor = (string) ($info['no_hp'] ?? '');
        if ($nomor === '') {
            return;
        }

        try {
            WaGatewayService::send($nomor, $pesan, [
                'user_id' => $user->id,
                'identifikasi_kebutuhan_id' => $identifikasiKebutuhanId,
            ]);
        } catch (\Throwable $e) {
            // Jangan sampai notifikasi WA memblokir alur utama.
            Log::warning('[WA] Gagal kirim notifikasi: '.$e->getMessage());
        }
    }

    /**
     * Riwayat / audit trail perubahan status paket.
     */
    public function riwayat(Request $request, int $id): JsonResponse
    {
        $kebutuhan = $this->scopedQuery($request, false)->findOrFail($id);

        $riwayat = IdentifikasiKebutuhanRiwayat::query()
            ->where('identifikasi_kebutuhan_id', $kebutuhan->id)
            ->with('pembuat')
            ->latest('created_at')
            ->get();

        return response()->json([
            'data' => IdentifikasiKebutuhanRiwayatResource::collection($riwayat),
        ]);
    }

    /**
     * Catat satu entri audit trail ke tabel riwayat.
     */
    private function catatRiwayat(
        IdentifikasiKebutuhan $kebutuhan,
        ?string $statusDari,
        string $statusKe,
        ?string $catatan,
        ?int $userId = null,
    ): void {
        IdentifikasiKebutuhanRiwayat::create([
            'identifikasi_kebutuhan_id' => $kebutuhan->id,
            'user_id' => $userId ?? $kebutuhan->user_id,
            'status_dari' => $statusDari,
            'status_ke' => $statusKe,
            'catatan' => $catatan,
        ]);
    }

    /**
     * Tolak bila rencana pagu pada sebuah standar harga (id_sipd_penetapan) melebihi
     * sisa pagunya — konsisten dgn perhitungan sisa di endpoint Modal SIPD:
     *   sisa = pagu SIPD − Σ pagu kebutuhan seluruh paket lain (semua status).
     *
     * @param  list<array{id_sipd_penetapan?: int|string, pagu?: float|int|string}>  $anggaran
     * @param  int|null  $excludeIdentifikasiId  id paket sendiri saat update (jangan hitung ganda)
     *
     * @throws ValidationException
     */
    private function assertAnggaranDalamSisa(array $anggaran, ?int $excludeIdentifikasiId = null): void
    {
        // Gabungkan beberapa baris yang menunjuk ke standar harga yang sama.
        $permintaan = [];
        foreach ($anggaran as $baris) {
            if (empty($baris['id_sipd_penetapan'])) {
                continue;
            }
            $id = (int) $baris['id_sipd_penetapan'];
            $nilai = (string) ($baris['pagu'] ?? '0');
            $permintaan[$id] = bcadd($permintaan[$id] ?? '0', $nilai, 2);
        }

        $ids = array_keys($permintaan);
        if ($ids === []) {
            return;
        }

        // Kunci baris standar harga agar perhitungan aman dari balapan tulis bersamaan.
        $standarHarga = DB::table('dev.sipd_penetapan_apbd')
            ->whereIn('id', $ids)
            ->lockForUpdate()
            ->pluck('pagu', 'id');

        // Σ pagu kebutuhan paket LAIN (bukan paket yang sedang di-update) per standar harga.
        $terpakai = DB::table('dev.identifikasi_kebutuhan_anggaran as ika')
            ->join('dev.identifikasi_kebutuhan as ik', 'ik.id', '=', 'ika.identifikasi_kebutuhan_id')
            ->whereIn('ika.id_sipd_penetapan', $ids)
            ->when($excludeIdentifikasiId !== null, fn ($q) => $q->where('ik.id', '!=', $excludeIdentifikasiId))
            ->groupBy('ika.id_sipd_penetapan')
            ->select('ika.id_sipd_penetapan', DB::raw('COALESCE(SUM(ika.pagu), 0) as total_kebutuhan'))
            ->get()
            ->pluck('total_kebutuhan', 'id_sipd_penetapan');

        $errors = [];
        foreach ($permintaan as $id => $nilaiDiminta) {
            $pagu = (string) ($standarHarga[$id] ?? '0');
            $dipakai = (string) ($terpakai[$id] ?? '0');
            $sisa = bcsub($pagu, $dipakai, 2);

            if (bccomp($nilaiDiminta, $sisa, 2) > 0) {
                $errors[] = sprintf(
                    'Rencana pagu paket untuk standar harga #%d (Rp %s) melebihi sisa pagu yang tersedia (Rp %s).',
                    $id,
                    number_format((float) $nilaiDiminta, 0, ',', '.'),
                    number_format((float) max($sisa, 0), 0, ',', '.')
                );
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['anggaran' => $errors]);
        }
    }
}
