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

        // Verifikator HANYA melihat paket yang sudah diajukan (menunggu review) —
        // draft (belum final) milik PPK tidak boleh terlihat di dashboard.
        if ($user && strtoupper((string) $user->role) === 'VERIFIKATOR') {
            $query->where('ik.status_review', 'Diajukan');
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
            $terbaru->where('ik.status_review', 'Diajukan');
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
}