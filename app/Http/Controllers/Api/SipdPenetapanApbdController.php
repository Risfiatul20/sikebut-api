<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SipdPenetapanApbdResource;
use App\Models\SipdPenetapanApbd;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class SipdPenetapanApbdController extends Controller
{
    /**
     * Query pre-scoped to the logged-in user's SKPD and PPK sub kegiatan mapping.
     *
     * @return Builder<SipdPenetapanApbd>
     */
    private function scopedQuery(Request $request): Builder
    {
        $user = $request->user();
        $kodeSubUnit = $request->query('kode_sub_unit') ?? $user?->kode_skpd;
        $ppkCodes = $user?->ppkSubKegiatanCodes();

        $query = SipdPenetapanApbd::query();

        if ($kodeSubUnit) {
            $query->where('kode_sub_unit', $kodeSubUnit);
        }

        if ($ppkCodes !== null) {
            $query->whereIn('kode_sub_kegiatan', $ppkCodes);
        }

        return $query;
    }

    /**
     * List SIPD penetapan APBD rows.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $this->scopedQuery($request)->with(['subUnit.parent', 'subKegiatan.kegiatan.program.bidangUrusan.urusan', 'standarHarga', 'akun.indikator']);

        $tahun = $request->query('tahun', date('Y'));
        if ($tahun !== null && $tahun !== '') {
            $query->where('tahun', (int) $tahun);
        }

        foreach (['kode_sub_kegiatan', 'kode_rekening', 'kode_sumber_dana', 'kode_standar_harga', 'versi'] as $field) {
            $value = $request->query($field);
            if ($value) {
                $query->where($field, $value);
            }
        }

        $search = $request->query('search');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_sumber_dana', 'ilike', "%{$search}%")
                    ->orWhere('kode_rekening', 'ilike', "%{$search}%")
                    ->orWhere('kode_sub_kegiatan', 'ilike', "%{$search}%");
            });
        }

        $requestedSort = $request->query('sort_by');
        $allowedSorts = ['id', 'tahun', 'pagu', 'kode_sub_kegiatan', 'kode_rekening', 'created_at'];
        $sortBy = in_array($requestedSort, $allowedSorts, true) ? $requestedSort : 'kode_sub_kegiatan';
        $sortDirection = strtolower((string) $request->query('sort_direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        $perPage = (int) $request->query('per_page', 25);

        $query->orderBy($sortBy, $sortDirection);

        $data = $perPage > 0 ? $query->paginate($perPage) : $query->get();

        return SipdPenetapanApbdResource::collection($data);
    }

    /**
     * Data untuk modal Pagu SIPD (Rekapitulasi hierarki SKPD, Sub Unit, Program, Kegiatan, Sub Kegiatan & List Standar Harga).
     */
    public function modal(Request $request): JsonResponse
    {
        $user = $request->user();
        $kodeSubUnit = $request->query('kode_sub_unit') ?? $request->query('kode_skpd') ?? $user?->kode_skpd;
        $kodeSubKegiatan = $request->query('kode_sub_kegiatan') ?? $request->query('kode_subkegiatan') ?? $request->query('subkegiatan') ?? $request->query('sub_kegiatan');
        $tahun = $request->query('tahun');
        $versi = $request->query('versi');

        if (! $kodeSubKegiatan) {
            return response()->json([
                'message' => 'Parameter kode_sub_kegiatan (atau subkegiatan) wajib diisi.',
            ], 422);
        }

        if (! $kodeSubUnit) {
            return response()->json([
                'message' => 'Parameter kode_sub_unit tidak ditemukan dan user tidak memiliki kode_skpd.',
            ], 422);
        }

        // PPK Role Scoping
        $ppkCodes = $user?->ppkSubKegiatanCodes();
        if ($ppkCodes !== null && ! in_array($kodeSubKegiatan, $ppkCodes, true)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke sub kegiatan ini.',
            ], 403);
        }

        // 1. Resolve context from dev.ref_sipd_view
        $contextQuery = DB::table('dev.ref_sipd_view')
            ->where('kode_sub_unit', $kodeSubUnit)
            ->where('kode_sub_kegiatan', $kodeSubKegiatan);

        if ($tahun !== null && $tahun !== '') {
            $contextQuery->where('tahun', (int) $tahun);
        }

        $context = $contextQuery->select([
            'kode_skpd', 'nama_skpd',
            'kode_sub_unit', 'nama_sub_unit',
            'kode_program', 'nama_program',
            'kode_kegiatan', 'nama_kegiatan',
            'kode_sub_kegiatan', 'nama_sub_kegiatan',
            'tahun',
        ])->first();

        if (! $context) {
            return response()->json([
                'message' => 'Data SIPD untuk sub kegiatan dan sub unit tersebut tidak ditemukan.',
            ], 404);
        }

        $tahunAktif = (int) $context->tahun;

        // 2-6. Agregasi pagu RKA & kebutuhan per level hierarki — dioptimasi dari 10 query
        // beruntun menjadi 2 query agregat (1 untuk pagu SIPD, 1 untuk kebutuhan terpakai),
        // lalu diakumulasi di PHP. Hasil akhir identik dengan ringkasan 5 level sebelumnya.
        $sipdSummaryRows = DB::table('dev.ref_sipd_view as rsv')
            ->where('rsv.kode_skpd', $context->kode_skpd)
            ->where('rsv.tahun', $tahunAktif)
            ->select([
                'rsv.kode_sub_unit',
                'rsv.kode_program',
                'rsv.kode_kegiatan',
                'rsv.kode_sub_kegiatan',
                DB::raw('COALESCE(SUM(rsv.pagu), 0) as total_pagu'),
                DB::raw('COALESCE(SUM(CASE WHEN rsv.is_belanja_pengadaan = true THEN rsv.pagu ELSE 0 END), 0) as total_pagu_pengadaan'),
                DB::raw('COALESCE(SUM(CASE WHEN rsv.is_belanja_pengadaan = true THEN 0 ELSE rsv.pagu END), 0) as total_pagu_non_pengadaan'),
            ])
            ->groupBy(['rsv.kode_sub_unit', 'rsv.kode_program', 'rsv.kode_kegiatan', 'rsv.kode_sub_kegiatan'])
            ->get();

        $zero = static fn (): array => ['pagu' => 0.0, 'pengadaan' => 0.0, 'non' => 0.0];
        $add = static function (array &$bucket, object $row): void {
            $bucket['pagu'] += (float) $row->total_pagu;
            $bucket['pengadaan'] += (float) $row->total_pagu_pengadaan;
            $bucket['non'] += (float) $row->total_pagu_non_pengadaan;
        };

        $skpd = $zero();
        $subUnit = $zero();
        $program = $zero();
        $kegiatan = $zero();
        $subKegiatan = $zero();
        foreach ($sipdSummaryRows as $row) {
            $add($skpd, $row);
            if ($row->kode_sub_unit === $context->kode_sub_unit) {
                $add($subUnit, $row);
                if ($row->kode_program === $context->kode_program) {
                    $add($program, $row);
                    if ($row->kode_kegiatan === $context->kode_kegiatan) {
                        $add($kegiatan, $row);
                        if ($row->kode_sub_kegiatan === $context->kode_sub_kegiatan) {
                            $add($subKegiatan, $row);
                        }
                    }
                }
            }
        }

        // Kebutuhan (pagu terpakai identifikasi) per sub kegiatan dalam lingkup OPD.
        $kebutuhanRows = DB::table('dev.identifikasi_kebutuhan_anggaran as ika')
            ->join('dev.sipd_penetapan_apbd as spa', 'ika.id_sipd_penetapan', '=', 'spa.id')
            ->join('dev.ref_skpd as su', 'spa.kode_sub_unit', '=', 'su.kode_skpd')
            ->leftJoin('dev.ref_sub_kegiatan as rsk', 'spa.kode_sub_kegiatan', '=', 'rsk.kode_sub_kegiatan')
            ->leftJoin('dev.ref_kegiatan as rk', 'rsk.kode_kegiatan', '=', 'rk.kode_kegiatan')
            ->where(DB::raw('COALESCE(su.parent_kode_skpd, su.kode_skpd)'), $context->kode_skpd)
            ->where('spa.tahun', $tahunAktif)
            ->select([
                'spa.kode_sub_unit',
                'rk.kode_program',
                'rk.kode_kegiatan',
                'spa.kode_sub_kegiatan',
                DB::raw('COALESCE(SUM(ika.pagu), 0) as total'),
            ])
            ->groupBy(['spa.kode_sub_unit', 'rk.kode_program', 'rk.kode_kegiatan', 'spa.kode_sub_kegiatan'])
            ->get();

        $skpdKebutuhan = 0.0;
        $subUnitKebutuhan = 0.0;
        $programKebutuhan = 0.0;
        $kegiatanKebutuhan = 0.0;
        $subKegiatanKebutuhan = 0.0;
        foreach ($kebutuhanRows as $row) {
            $need = (float) $row->total;
            $skpdKebutuhan += $need;
            if ($row->kode_sub_unit === $context->kode_sub_unit) {
                $subUnitKebutuhan += $need;
                if ($row->kode_program === $context->kode_program) {
                    $programKebutuhan += $need;
                    if ($row->kode_kegiatan === $context->kode_kegiatan) {
                        $kegiatanKebutuhan += $need;
                        if ($row->kode_sub_kegiatan === $context->kode_sub_kegiatan) {
                            $subKegiatanKebutuhan += $need;
                        }
                    }
                }
            }
        }

        $skpdTotalPagu = $skpd['pagu'];
        $skpdPaguPengadaan = $skpd['pengadaan'];
        $skpdPaguNonPengadaan = $skpd['non'];
        $subUnitTotalPagu = $subUnit['pagu'];
        $subUnitPaguPengadaan = $subUnit['pengadaan'];
        $subUnitPaguNonPengadaan = $subUnit['non'];
        $programTotalPagu = $program['pagu'];
        $programPaguPengadaan = $program['pengadaan'];
        $programPaguNonPengadaan = $program['non'];
        $kegiatanTotalPagu = $kegiatan['pagu'];
        $kegiatanPaguPengadaan = $kegiatan['pengadaan'];
        $kegiatanPaguNonPengadaan = $kegiatan['non'];
        $subKegiatanTotalPagu = $subKegiatan['pagu'];
        $subKegiatanPaguPengadaan = $subKegiatan['pengadaan'];
        $subKegiatanPaguNonPengadaan = $subKegiatan['non'];

        // 7. List Standar Harga / Rekening
        $items = DB::table('dev.sipd_penetapan_apbd as spa')
            ->join('dev.ref_skpd as sub_unit', 'spa.kode_sub_unit', '=', 'sub_unit.kode_skpd')
            ->join('dev.ref_sub_kegiatan as sub_keg', 'spa.kode_sub_kegiatan', '=', 'sub_keg.kode_sub_kegiatan')
            ->leftJoin('dev.ref_standar_harga as sh', 'spa.kode_standar_harga', '=', 'sh.kode_standar_harga')
            ->leftJoin('dev.ref_akun as akun', 'spa.kode_rekening', '=', 'akun.kode_akun')
            ->leftJoin('dev.akun_indikator_rkbmd as ind', 'spa.kode_rekening', '=', 'ind.kode_akun')
            ->leftJoin('dev.identifikasi_kebutuhan_anggaran as ika', 'ika.id_sipd_penetapan', '=', 'spa.id')
            ->where('spa.kode_sub_unit', $context->kode_sub_unit)
            ->where('spa.kode_sub_kegiatan', $context->kode_sub_kegiatan)
            ->where('spa.tahun', $tahunAktif)
            ->where('spa.pagu', '>', 0) // Pagu kosong tidak ditampilkan (arahan: pagu kosong jangan diambil)
            // Hanya rekening belanja pengadaan yang ditampilkan di Daftar Standar Harga (arahan butir 10).
            ->whereRaw('COALESCE(ind.is_belanja_pengadaan, false) = true')
            ->select([
                'spa.id as id_sipd_penetapan',
                'spa.kode_rekening',
                'akun.nama_akun as nama_rekening',
                'spa.kode_standar_harga',
                'sh.nama_standar_harga',
                'spa.kode_sumber_dana',
                'spa.nama_sumber_dana',
                'spa.pagu',
                DB::raw('COALESCE(ind.is_belanja_pengadaan, false) as is_belanja_pengadaan'),
                DB::raw('COALESCE(ind.is_rkbmd_pengadaan, false) as is_rkbmd_pengadaan'),
                DB::raw('COALESCE(ind.is_rkbmd_pemeliharaan_rehab, false) as is_rkbmd_pemeliharaan_rehab'),
                DB::raw('COALESCE(ind.is_rkbmd_pemeliharaan_rutin, false) as is_rkbmd_pemeliharaan_rutin'),
                DB::raw('COALESCE(SUM(ika.pagu), 0) as total_kebutuhan_anggaran'),
                DB::raw('(spa.pagu - COALESCE(SUM(ika.pagu), 0)) as sisa_pagu'),
            ])
            ->groupBy([
                'spa.id', 'spa.kode_rekening', 'akun.nama_akun', 'spa.kode_standar_harga',
                'sh.nama_standar_harga', 'spa.kode_sumber_dana', 'spa.nama_sumber_dana',
                'spa.pagu', 'ind.is_belanja_pengadaan', 'ind.is_rkbmd_pengadaan',
                'ind.is_rkbmd_pemeliharaan_rehab', 'ind.is_rkbmd_pemeliharaan_rutin',
            ])
            ->orderBy('spa.kode_rekening', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'id_sipd_penetapan' => (int) $item->id_sipd_penetapan,
                    'kode_rekening' => $item->kode_rekening,
                    'nama_rekening' => $item->nama_rekening,
                    'kode_standar_harga' => $item->kode_standar_harga,
                    'nama_standar_harga' => $item->nama_standar_harga,
                    'kode_sumber_dana' => $item->kode_sumber_dana,
                    'nama_sumber_dana' => $item->nama_sumber_dana,
                    'is_belanja_pengadaan' => (bool) $item->is_belanja_pengadaan,
                    'is_rkbmd_pengadaan' => (bool) $item->is_rkbmd_pengadaan,
                    'is_rkbmd_pemeliharaan_rehab' => (bool) $item->is_rkbmd_pemeliharaan_rehab,
                    'is_rkbmd_pemeliharaan_rutin' => (bool) $item->is_rkbmd_pemeliharaan_rutin,
                    'pagu' => number_format((float) $item->pagu, 2, '.', ''),
                    'total_kebutuhan_anggaran' => number_format((float) $item->total_kebutuhan_anggaran, 2, '.', ''),
                    'sisa_pagu' => number_format((float) $item->sisa_pagu, 2, '.', ''),
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'skpd' => [
                    'kode_skpd' => $context->kode_skpd,
                    'nama_skpd' => $context->nama_skpd,
                    'total_pagu' => number_format($skpdTotalPagu, 2, '.', ''),
                    'total_pagu_pengadaan' => number_format($skpdPaguPengadaan, 2, '.', ''),
                    'total_pagu_non_pengadaan' => number_format($skpdPaguNonPengadaan, 2, '.', ''),
                    'total_kebutuhan_anggaran' => number_format($skpdKebutuhan, 2, '.', ''),
                    'sisa_pagu_pengadaan' => number_format($skpdPaguPengadaan - $skpdKebutuhan, 2, '.', ''),
                ],
                'sub_unit' => [
                    'kode_sub_unit' => $context->kode_sub_unit,
                    'nama_sub_unit' => $context->nama_sub_unit,
                    'total_pagu' => number_format($subUnitTotalPagu, 2, '.', ''),
                    'total_pagu_pengadaan' => number_format($subUnitPaguPengadaan, 2, '.', ''),
                    'total_pagu_non_pengadaan' => number_format($subUnitPaguNonPengadaan, 2, '.', ''),
                    'total_kebutuhan_anggaran' => number_format($subUnitKebutuhan, 2, '.', ''),
                    'sisa_pagu_pengadaan' => number_format($subUnitPaguPengadaan - $subUnitKebutuhan, 2, '.', ''),
                ],
                'program' => [
                    'kode_program' => $context->kode_program,
                    'nama_program' => $context->nama_program,
                    'total_pagu' => number_format($programTotalPagu, 2, '.', ''),
                    'total_pagu_pengadaan' => number_format($programPaguPengadaan, 2, '.', ''),
                    'total_pagu_non_pengadaan' => number_format($programPaguNonPengadaan, 2, '.', ''),
                    'total_kebutuhan_anggaran' => number_format($programKebutuhan, 2, '.', ''),
                    'sisa_pagu_pengadaan' => number_format($programPaguPengadaan - $programKebutuhan, 2, '.', ''),
                ],
                'kegiatan' => [
                    'kode_kegiatan' => $context->kode_kegiatan,
                    'nama_kegiatan' => $context->nama_kegiatan,
                    'total_pagu' => number_format($kegiatanTotalPagu, 2, '.', ''),
                    'total_pagu_pengadaan' => number_format($kegiatanPaguPengadaan, 2, '.', ''),
                    'total_pagu_non_pengadaan' => number_format($kegiatanPaguNonPengadaan, 2, '.', ''),
                    'total_kebutuhan_anggaran' => number_format($kegiatanKebutuhan, 2, '.', ''),
                    'sisa_pagu_pengadaan' => number_format($kegiatanPaguPengadaan - $kegiatanKebutuhan, 2, '.', ''),
                ],
                'sub_kegiatan' => [
                    'kode_sub_kegiatan' => $context->kode_sub_kegiatan,
                    'nama_sub_kegiatan' => $context->nama_sub_kegiatan,
                    'total_pagu' => number_format($subKegiatanTotalPagu, 2, '.', ''),
                    'total_pagu_pengadaan' => number_format($subKegiatanPaguPengadaan, 2, '.', ''),
                    'total_pagu_non_pengadaan' => number_format($subKegiatanPaguNonPengadaan, 2, '.', ''),
                    'total_kebutuhan_anggaran' => number_format($subKegiatanKebutuhan, 2, '.', ''),
                    'sisa_pagu_pengadaan' => number_format($subKegiatanPaguPengadaan - $subKegiatanKebutuhan, 2, '.', ''),
                ],
                'standar_harga' => $items,
            ],
        ]);
    }

    /**
     * Show a single SIPD penetapan APBD row.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $record = $this->scopedQuery($request)
            ->with(['subUnit.parent', 'subKegiatan.kegiatan.program', 'standarHarga', 'akun.indikator'])
            ->findOrFail($id);

        return response()->json([
            'data' => new SipdPenetapanApbdResource($record),
        ]);
    }

    /**
     * Daftar versi Penetapan APBD yang tersedia di database (dari tabel impor).
     */
    public function versions(Request $request): JsonResponse
    {
        // PENTING: kolom `versi` bertipe varchar(100) dan TIDAK selalu berisi angka —
        // form impor mengizinkan pengguna mengisi LABEL bebas (mis. "Penetapan Perubahan
        // APBD 2026"). Cast `versi::int` tanpa penjaga pernah membuat endpoint ini 500
        // (SQLSTATE[22P02]: invalid input syntax for type integer). Karena itu:
        //  - urutkan versi NUMERIK lebih dulu (terbesar), lalu label teks menurut impor terbaru;
        //  - kirim `versi` sebagai STRING supaya label tetap utuh sampai ke frontend.
        $rows = DB::table('dev.sipd_penetapan_apbd')
            ->selectRaw('versi, tahun, COUNT(*) as total_rincian, COALESCE(SUM(pagu), 0) as total_pagu, MAX(created_at) as last_import')
            ->groupBy('versi', 'tahun')
            ->orderBy('tahun', 'desc')
            ->orderByRaw("CASE WHEN versi ~ '^[0-9]+$' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN versi ~ '^[0-9]+$' THEN versi::int END DESC NULLS LAST")
            ->orderByRaw('last_import DESC NULLS LAST')
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'versi' => (string) $r->versi,
                'nama_versi' => preg_match('/^[0-9]+$/', (string) $r->versi)
                    ? 'Versi '.$r->versi.' - Penetapan APBD '.$r->tahun
                    : (string) $r->versi,
                'tahun' => (int) $r->tahun,
                'total_rincian' => (int) $r->total_rincian,
                'total_pagu' => (float) $r->total_pagu,
                'tanggal_impor' => $r->last_import ? (string) $r->last_import : null,
                'status' => 'Aktif',
            ])->values(),
        ]);
    }
}
