<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Ringkasan dashboard (data asli) — di-scope sesuai role & SKPD pengguna.
     *
     * - PPK: hanya paket pada sub kegiatan yang ter-mapping ke akunnya.
     * - Kepala OPD / Kepala Sub Unit: hanya paket pada SKPD-nya.
     * - Admin / Verifikator: semua paket.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = DB::table('dev.identifikasi_kebutuhan as ik')
            ->leftJoin('dev.identifikasi_kebutuhan_anggaran as ika', 'ika.identifikasi_kebutuhan_id', '=', 'ik.id');

        // Scoping SKPD
        if ($user?->kode_skpd) {
            $query->where('ik.kode_skpd', $user->kode_skpd);
        }

        // Scoping PPK: hanya sub kegiatan ter-mapping
        $ppkCodes = $user?->ppkSubKegiatanCodes();
        if ($ppkCodes !== null) {
            $query->whereIn('ik.kode_sub_kegiatan', $ppkCodes);
        }

        // Verifikator melihat paket Diajukan (menunggu review), Disetujui, dan Perlu Perbaikan —
        // draft (belum final) milik PPK tidak boleh terlihat di dashboard.
        if ($user && strtoupper((string) $user->role) === 'VERIFIKATOR') {
            $query->whereIn('ik.status_review', ['Diajukan', 'Disetujui', 'Perlu Perbaikan']);
        }

        $base = clone $query;

        // Jumlah paket per status
        $perStatus = (clone $base)
            ->select('ik.status_review', DB::raw('COUNT(DISTINCT ik.id) as jumlah'))
            ->groupBy('ik.status_review')
            ->get()
            ->pluck('jumlah', 'status_review');

        // Jumlah paket per jenis pengadaan
        $perJenis = (clone $base)
            ->select('ik.jenis_pengadaan', DB::raw('COUNT(DISTINCT ik.id) as jumlah'))
            ->groupBy('ik.jenis_pengadaan')
            ->get()
            ->pluck('jumlah', 'jenis_pengadaan');

        // Jumlah paket per cara pengadaan (Penyedia/Swakelola)
        $perCara = (clone $base)
            ->select('ik.cara_pengadaan', DB::raw('COUNT(DISTINCT ik.id) as jumlah'))
            ->groupBy('ik.cara_pengadaan')
            ->get()
            ->pluck('jumlah', 'cara_pengadaan');

        // Total paket (distinct)
        $totalPaket = (clone $base)->distinct()->count('ik.id');

        // Total pagu paket (jumlah semua anggaran milik paket yang ter-scope)
        $totalPaguPaket = (float) (clone $query)
            ->whereNotNull('ika.id')
            ->sum('ika.pagu');

        // Perlu review = status Diajukan
        $perluReview = (int) ($perStatus['Diajukan'] ?? 0);

        // Paket terbaru (5) — scoping sama seperti agregat di atas.
        $terbaru = DB::table('dev.identifikasi_kebutuhan as ik')
            ->leftJoin('dev.users as u', 'u.id', '=', 'ik.user_id')
            ->leftJoin('dev.ref_skpd as skpd', 'skpd.kode_skpd', '=', 'ik.kode_skpd');

        if ($user?->kode_skpd) {
            $terbaru->where('ik.kode_skpd', $user->kode_skpd);
        }
        if ($ppkCodes !== null) {
            $terbaru->whereIn('ik.kode_sub_kegiatan', $ppkCodes);
        }
        if ($user && strtoupper((string) $user->role) === 'VERIFIKATOR') {
            $terbaru->whereIn('ik.status_review', ['Diajukan', 'Disetujui', 'Perlu Perbaikan']);
        }

        $terbaru = $terbaru
            ->select(
                'ik.id',
                'ik.nama_paket',
                'ik.status_review',
                'ik.jenis_pengadaan',
                'ik.cara_pengadaan',
                'ik.created_at',
                'ik.updated_at',
                'u.nama as nama_user',
                'skpd.nama_skpd'
            )
            ->orderByDesc('ik.updated_at')
            ->limit(5)
            ->get();

        return response()->json([
            'data' => [
                'total_paket' => $totalPaket,
                'total_pagu_paket' => $totalPaguPaket,
                'perlu_review' => $perluReview,
                'paket_per_status' => $perStatus,
                'paket_per_jenis' => $perJenis,
                'paket_per_cara' => $perCara,
                'paket_terbaru' => $terbaru,
            ],
        ]);
    }

    /**
     * Keterisian per akun PPK (arahan atasan #2):
     * per PPK tampilkan jumlah sub kegiatan ter-mapping, total pagu APBD dari sub kegiatan tsb,
     * pagu paket yang sudah dibuat, jumlah paket, dan persentase keterisian.
     *
     * - PPK: hanya baris miliknya sendiri.
     * - Admin / Verifikator / Kepala: seluruh akun PPK.
     */
    public function keterisianPpk(Request $request): JsonResponse
    {
        $user = $request->user();
        $tahun = (int) $request->query('tahun', 0);
        if ($tahun <= 0) {
            $tahun = (int) DB::table('dev.sipd_penetapan_apbd')->max('tahun');
        }

        // 1) Sub kegiatan ter-mapping + total pagu APBD per PPK (tidak digabung dengan paket
        //    agar tidak ada inflasi cross-join).
        $paguRows = DB::table('dev.users as u')
            ->leftJoin('dev.user_sub_kegiatan as usk', 'usk.user_id', '=', 'u.id')
            ->leftJoin('dev.sipd_penetapan_apbd as spa', function ($j) use ($tahun) {
                $j->on('spa.kode_sub_kegiatan', '=', 'usk.kode_sub_kegiatan')
                    ->where('spa.tahun', $tahun);
            })
            ->whereRaw('LOWER(u.role) = ?', ['ppk'])
            ->when($user && strtoupper((string) $user->role) === 'PPK', fn ($q) => $q->where('u.id', $user->id))
            ->select(
                'u.id as user_id',
                'u.username',
                'u.nama',
                DB::raw('COUNT(DISTINCT usk.kode_sub_kegiatan) as total_sub_kegiatan'),
                DB::raw('COALESCE(SUM(spa.pagu), 0) as total_pagu_apbd')
            )
            ->groupBy('u.id', 'u.username', 'u.nama')
            ->orderBy('u.nama')
            ->get()
            ->keyBy('user_id');

        // 2) Paket dibuat per PPK + pagu paket.
        $paketRows = DB::table('dev.users as u')
            ->leftJoin('dev.identifikasi_kebutuhan as ik', 'ik.user_id', '=', 'u.id')
            ->leftJoin('dev.identifikasi_kebutuhan_anggaran as ika', 'ika.identifikasi_kebutuhan_id', '=', 'ik.id')
            ->whereRaw('LOWER(u.role) = ?', ['ppk'])
            ->when($user && strtoupper((string) $user->role) === 'PPK', fn ($q) => $q->where('u.id', $user->id))
            ->select(
                'u.id as user_id',
                DB::raw('COUNT(DISTINCT ik.id) as jumlah_paket'),
                DB::raw('COALESCE(SUM(ika.pagu), 0) as total_pagu_paket')
            )
            ->groupBy('u.id')
            ->get()
            ->keyBy('user_id');

        $rows = $paguRows->map(function ($r) use ($paketRows, $tahun) {
            $p = $paketRows->get($r->user_id);
            $totalPagu = (float) $r->total_pagu_apbd;
            $paguPaket = (float) ($p->total_pagu_paket ?? 0);

            return [
                'user_id' => (int) $r->user_id,
                'username' => $r->username,
                'nama' => $r->nama,
                'total_sub_kegiatan' => (int) $r->total_sub_kegiatan,
                'total_pagu_apbd' => $totalPagu,
                'jumlah_paket' => (int) ($p->jumlah_paket ?? 0),
                'total_pagu_paket' => $paguPaket,
                'keterisian_persen' => $totalPagu > 0 ? round(($paguPaket / $totalPagu) * 100, 2) : 0,
                'tahun' => $tahun,
            ];
        })->values();

        return response()->json(['data' => $rows]);
    }
}