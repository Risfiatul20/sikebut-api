<?php

namespace App\Http\Controllers\Api;

use App\Exports\LaporanKebutuhanExport;
use App\Exports\LaporanRekapExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LaporanController extends Controller
{
    /**
     * Kode SKPD lingkup user (non-Admin/Verifikator hanya melihat OPD-nya sendiri).
     */
    private function scopeKodeSkpd(Request $request): ?string
    {
        $user = $request->user();
        $role = strtoupper((string) $user?->role);

        // Admin & Verifikator melihat seluruh provinsi (kode_skpd kosong).
        if (in_array($role, ['ADMIN', 'VERIFIKATOR'], true)) {
            return null;
        }

        return $user?->kode_skpd ?: null;
    }

    /**
     * Rekapitulasi berjenjang OPD → Sub Unit → Program → Kegiatan → Sub Kegiatan
     * membandingkan pagu RKA SIPD dengan hasil identifikasi kebutuhan (paket).
     *
     * GET /api/v1/laporan/rekap?tahun=2026&versi=&kode_skpd=
     */
    public function rekap(Request $request): JsonResponse
    {
        $user = $request->user();
        $tahun = (int) ($request->query('tahun') ?: date('Y'));
        $versi = $request->query('versi');
        $kodeSkpd = $request->query('kode_skpd') ?: $this->scopeKodeSkpd($request);

        // ---- 1. Agregat pagu SIPD per rantai hierarki (dari view denormal) ----
        $q = DB::table('dev.ref_sipd_view as rsv')
            ->where('rsv.tahun', $tahun)
            ->select([
                'rsv.kode_skpd',
                DB::raw('MAX(rsv.nama_skpd) as nama_skpd'),
                'rsv.kode_sub_unit',
                DB::raw('MAX(rsv.nama_sub_unit) as nama_sub_unit'),
                'rsv.kode_program',
                DB::raw('MAX(rsv.nama_program) as nama_program'),
                'rsv.kode_kegiatan',
                DB::raw('MAX(rsv.nama_kegiatan) as nama_kegiatan'),
                'rsv.kode_sub_kegiatan',
                DB::raw('MAX(rsv.nama_sub_kegiatan) as nama_sub_kegiatan'),
                DB::raw('SUM(rsv.pagu) as total_pagu'),
                DB::raw('SUM(CASE WHEN rsv.is_belanja_pengadaan = true THEN rsv.pagu ELSE 0 END) as pagu_pengadaan'),
                DB::raw('SUM(CASE WHEN rsv.is_belanja_pengadaan = true THEN 0 ELSE rsv.pagu END) as pagu_non_pengadaan'),
            ])
            ->groupBy([
                'rsv.kode_skpd', 'rsv.kode_sub_unit', 'rsv.kode_program',
                'rsv.kode_kegiatan', 'rsv.kode_sub_kegiatan',
            ]);

        if ($versi) {
            $q->where('rsv.versi', $versi);
        }
        if ($kodeSkpd) {
            $q->where('rsv.kode_skpd', $kodeSkpd);
        }

        $rows = $q->get()->keyBy(function ($r) {
            return implode('|', [$r->kode_skpd, $r->kode_sub_unit, $r->kode_program, $r->kode_kegiatan, $r->kode_sub_kegiatan]);
        });

        // ---- 2. Statistik paket identifikasi per rantai (header paket + anggaran) ----
        $pq = DB::table('dev.identifikasi_kebutuhan as i')
            ->leftJoin('dev.identifikasi_kebutuhan_anggaran as a', 'a.identifikasi_kebutuhan_id', '=', 'i.id')
            ->join('dev.ref_skpd as su', 'su.kode_skpd', '=', 'i.kode_skpd')
            ->select([
                DB::raw('COALESCE(su.parent_kode_skpd, su.kode_skpd) as kode_skpd'),
                'i.kode_skpd as kode_sub_unit',
                'i.kode_program',
                'i.kode_kegiatan',
                'i.kode_sub_kegiatan',
                'i.cara_pengadaan',
                DB::raw('COUNT(DISTINCT i.id) as paket'),
                DB::raw('COALESCE(SUM(a.pagu), 0) as pagu_paket'),
            ])
            ->groupBy([
                DB::raw('COALESCE(su.parent_kode_skpd, su.kode_skpd)'),
                'i.kode_skpd', 'i.kode_program',
                'i.kode_kegiatan', 'i.kode_sub_kegiatan', 'i.cara_pengadaan',
            ]);

        // Hanya paket yang sudah diajukan/final yang masuk laporan — Draft tidak ditampilkan.
        $pq->whereIn('i.status_review', ['Diajukan', 'Disetujui', 'Perlu Perbaikan']);

        // identifikasi header tidak menyimpan tahun; satukan ke semua paket.
        if ($kodeSkpd) {
            $pq->where(DB::raw('COALESCE(su.parent_kode_skpd, su.kode_skpd)'), $kodeSkpd);
        }

        $paketByLeaf = [];
        foreach ($pq->get() as $p) {
            $key = implode('|', [$p->kode_skpd, $p->kode_sub_unit, $p->kode_program, $p->kode_kegiatan, $p->kode_sub_kegiatan]);
            $isPenyedia = strtoupper((string) $p->cara_pengadaan) === 'PENYEDIA';
            if (! isset($paketByLeaf[$key])) {
                $paketByLeaf[$key] = [
                    'jumlah_paket' => 0,
                    'jumlah_pagu' => 0.0,
                    'penyedia_paket' => 0,
                    'penyedia_pagu' => 0.0,
                    'swakelola_paket' => 0,
                    'swakelola_pagu' => 0.0,
                ];
            }
            $paketByLeaf[$key]['jumlah_paket'] += (int) $p->paket;
            $paketByLeaf[$key]['jumlah_pagu'] += (float) $p->pagu_paket;
            if ($isPenyedia) {
                $paketByLeaf[$key]['penyedia_paket'] += (int) $p->paket;
                $paketByLeaf[$key]['penyedia_pagu'] += (float) $p->pagu_paket;
            } else {
                $paketByLeaf[$key]['swakelola_paket'] += (int) $p->paket;
                $paketByLeaf[$key]['swakelola_pagu'] += (float) $p->pagu_paket;
            }
        }

        // ---- 3. Bangun pohon hierarki ----
        // Struktur sementara: tree[opdKode] = [data..., sub_units[kode] = [.., programs[kode]=...]]
        $tree = [];

        $addStat = function (&$node, array $stat) {
            $node['stat']['pagu'] += $stat['pagu'];
            $node['stat']['pengadaan'] += $stat['pengadaan'];
            $node['stat']['nonPengadaan'] += $stat['nonPengadaan'];
        };

        $addPaket = function (&$node, array $p) {
            $node['paket']['jumlah']['paket'] += $p['jumlah_paket'];
            $node['paket']['jumlah']['pagu'] += $p['jumlah_pagu'];
            $node['paket']['penyedia']['paket'] += $p['penyedia_paket'];
            $node['paket']['penyedia']['pagu'] += $p['penyedia_pagu'];
            $node['paket']['swakelola']['paket'] += $p['swakelola_paket'];
            $node['paket']['swakelola']['pagu'] += $p['swakelola_pagu'];
        };

        $emptyStat = function () {
            return ['pagu' => 0.0, 'pengadaan' => 0.0, 'nonPengadaan' => 0.0];
        };

        $emptyPaket = function () {
            return [
                'jumlah' => ['paket' => 0, 'pagu' => 0.0],
                'penyedia' => ['paket' => 0, 'pagu' => 0.0],
                'swakelola' => ['paket' => 0, 'pagu' => 0.0],
            ];
        };

        foreach ($rows as $key => $r) {
            $opdKode = $r->kode_skpd;
            $suKode = $r->kode_sub_unit;
            $progKode = $r->kode_program;
            $kegKode = $r->kode_kegiatan;
            $subKode = $r->kode_sub_kegiatan;

            if (! isset($tree[$opdKode])) {
                $tree[$opdKode] = [
                    'kode' => $opdKode,
                    'nama' => (string) $r->nama_skpd,
                    'stat' => $emptyStat(),
                    'paket' => $emptyPaket(),
                    'sub_units' => [],
                ];
            }
            $opd = &$tree[$opdKode];

            if (! isset($opd['sub_units'][$suKode])) {
                $opd['sub_units'][$suKode] = [
                    'kode' => $suKode,
                    'nama' => (string) $r->nama_sub_unit,
                    'stat' => $emptyStat(),
                    'paket' => $emptyPaket(),
                    'programs' => [],
                ];
            }
            $su = &$opd['sub_units'][$suKode];

            if (! isset($su['programs'][$progKode])) {
                $su['programs'][$progKode] = [
                    'kode' => $progKode,
                    'nama' => (string) $r->nama_program,
                    'stat' => $emptyStat(),
                    'paket' => $emptyPaket(),
                    'kegiatans' => [],
                ];
            }
            $prog = &$su['programs'][$progKode];

            if (! isset($prog['kegiatans'][$kegKode])) {
                $prog['kegiatans'][$kegKode] = [
                    'kode' => $kegKode,
                    'nama' => (string) $r->nama_kegiatan,
                    'stat' => $emptyStat(),
                    'paket' => $emptyPaket(),
                    'sub_kegiatans' => [],
                ];
            }
            $keg = &$prog['kegiatans'][$kegKode];

            if (! isset($keg['sub_kegiatans'][$subKode])) {
                $keg['sub_kegiatans'][$subKode] = [
                    'kode' => $subKode,
                    'nama' => (string) $r->nama_sub_kegiatan,
                    'stat' => $emptyStat(),
                    'paket' => $emptyPaket(),
                ];
            }
            $sub = &$keg['sub_kegiatans'][$subKode];

            $stat = [
                'pagu' => (float) $r->total_pagu,
                'pengadaan' => (float) $r->pagu_pengadaan,
                'nonPengadaan' => (float) $r->pagu_non_pengadaan,
            ];
            $addStat($sub, $stat);
            $addStat($keg, $stat);
            $addStat($prog, $stat);
            $addStat($su, $stat);
            $addStat($opd, $stat);

            if (isset($paketByLeaf[$key])) {
                $p = $paketByLeaf[$key];
                $addPaket($sub, $p);
                $addPaket($keg, $p);
                $addPaket($prog, $p);
                $addPaket($su, $p);
                $addPaket($opd, $p);
            }
        }

        // ---- 4. Konversi ke struktur JSON final + nomor ----
        $keterisian = function (array $node): float {
            $pengadaan = $node['belanjaPengadaan'];
            $paguPaket = $node['identifikasi']['jumlah']['pagu'];
            if ($pengadaan > 0) {
                return round(min(100, ($paguPaket / $pengadaan) * 100), 2);
            }
            return 0.0;
        };

        $convert = function (array $node, int $level, string $type, string $prefixNo, int &$counter) use (&$convert, $keterisian) {
            $node['level'] = $level;
            $node['type'] = $type;
            $node['id'] = "{$type}-{$node['kode']}";
            $node['pagu'] = round($node['stat']['pagu'] ?? 0, 2);
            $node['belanjaPengadaan'] = round($node['stat']['pengadaan'] ?? 0, 2);
            $node['belanjaNonPengadaan'] = round($node['stat']['nonPengadaan'] ?? 0, 2);
            $node['identifikasi'] = $node['paket'] ?? ['jumlah' => ['paket' => 0, 'pagu' => 0], 'penyedia' => ['paket' => 0, 'pagu' => 0], 'swakelola' => ['paket' => 0, 'pagu' => 0]];
            $node['keterisian'] = $keterisian($node);
            $node['no'] = $prefixNo;

            unset($node['stat'], $node['paket']);

            if ($level === 1) {
                $children = [];
                $i = 1;
                foreach (($node['sub_units'] ?? []) as $childRaw) {
                    $counter++;
                    $children[] = $convert($childRaw, 2, 'sub_unit', $node['no'].'.'.$i, $counter);
                    $i++;
                }
                unset($node['sub_units']);
                $node['children'] = $children;
            } elseif ($level === 2) {
                $children = [];
                $i = 1;
                foreach (($node['programs'] ?? []) as $childRaw) {
                    $counter++;
                    $children[] = $convert($childRaw, 3, 'program', $node['no'].'.'.$i, $counter);
                    $i++;
                }
                unset($node['programs']);
                $node['children'] = $children;
            } elseif ($level === 3) {
                $children = [];
                $i = 1;
                foreach (($node['kegiatans'] ?? []) as $childRaw) {
                    $counter++;
                    $children[] = $convert($childRaw, 4, 'kegiatan', $node['no'].'.'.$i, $counter);
                    $i++;
                }
                unset($node['kegiatans']);
                $node['children'] = $children;
            } elseif ($level === 4) {
                $children = [];
                $i = 1;
                foreach (($node['sub_kegiatans'] ?? []) as $childRaw) {
                    $counter++;
                    $children[] = $convert($childRaw, 5, 'sub_kegiatan', $node['no'].'.'.$i, $counter);
                    $i++;
                }
                unset($node['sub_kegiatans']);
                $node['children'] = $children;
            } else {
                $node['children'] = [];
            }

            return $node;
        };

        ksort($tree);
        $treeFinal = [];
        $counter = 0;
        foreach ($tree as $opdRaw) {
            $counter++;
            $treeFinal[] = $convert($opdRaw, 1, 'opd', (string) $counter, $counter);
        }

        $totalPagu = 0.0;
        $totalPengadaan = 0.0;
        $totalNon = 0.0;
        $totalPaket = 0;
        $totalPaguPaket = 0.0;
        foreach ($treeFinal as $opd) {
            $totalPagu += $opd['pagu'];
            $totalPengadaan += $opd['belanjaPengadaan'];
            $totalNon += $opd['belanjaNonPengadaan'];
            $totalPaket += $opd['identifikasi']['jumlah']['paket'];
            $totalPaguPaket += $opd['identifikasi']['jumlah']['pagu'];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'tahun' => $tahun,
                'summary' => [
                    'total_pagu' => round($totalPagu, 2),
                    'total_pengadaan' => round($totalPengadaan, 2),
                    'total_non_pengadaan' => round($totalNon, 2),
                    'total_paket' => $totalPaket,
                    'total_pagu_paket' => round($totalPaguPaket, 2),
                ],
                'tree' => $treeFinal,
            ],
        ]);
    }

    /**
     * Rute GET /api/v1/laporan/penyedia — jembatan ke paketPerCara('Penyedia').
     * Rute di routes/api.php memanggil metode ini; tanpa jembatan ini
     * permintaan gagal 500 "Call to undefined method".
     */
    public function penyedia(Request $request): JsonResponse
    {
        return $this->paketPerCara($request, 'Penyedia');
    }

    /**
     * Rute GET /api/v1/laporan/swakelola — jembatan ke paketPerCara('Swakelola').
     */
    public function swakelola(Request $request): JsonResponse
    {
        return $this->paketPerCara($request, 'Swakelola');
    }

    /**
     * Laporan paket per cara pengadaan (Penyedia / Swakelola) — agregat per SKPD
     * + daftar paket lengkap. Basis data asli identifikasi_kebutuhan.
     *
     * GET /api/v1/laporan/penyedia  |  /api/v1/laporan/swakelola
     */
    public function paketPerCara(Request $request, string $cara): JsonResponse
    {
        $caraNormalized = strtoupper(trim($cara));
        $isPenyedia = $caraNormalized === 'PENYEDIA';
        if (! $isPenyedia && $caraNormalized !== 'SWAKELOLA') {
            return response()->json(['message' => 'Parameter cara harus Penyedia atau Swakelola.'], 422);
        }

        $kodeSkpd = $this->scopeKodeSkpd($request);
        $user = $request->user();
        $ppkCodes = $user?->ppkSubKegiatanCodes();

        $base = DB::table('dev.identifikasi_kebutuhan as ik')
            ->leftJoin('dev.identifikasi_kebutuhan_anggaran as ika', 'ika.identifikasi_kebutuhan_id', '=', 'ik.id')
            ->leftJoin('dev.ref_skpd as rs', 'rs.kode_skpd', '=', 'ik.kode_skpd')
            ->leftJoin('dev.users as u', 'u.id', '=', 'ik.user_id')
            ->where('ik.cara_pengadaan', $isPenyedia ? 'Penyedia' : 'Swakelola')
            // Laporan hanya menampilkan paket yang sudah final/diajukan — Draft tidak ditampilkan.
            ->whereIn('ik.status_review', ['Diajukan', 'Disetujui', 'Perlu Perbaikan']);

        if ($kodeSkpd) {
            $base->where('ik.kode_skpd', $kodeSkpd);
        }
        if ($ppkCodes !== null) {
            $base->whereIn('ik.kode_sub_kegiatan', $ppkCodes);
        }

        // Filter tahun anggaran (dari navbar) — kosong = semua tahun
        $tahun = (int) ($request->query('tahun') ?: 0);
        if ($tahun > 0) {
            $base->where('ik.tahun', $tahun);
        }

        // Agregat per SKPD
        $perSkpd = (clone $base)
            ->select([
                'ik.kode_skpd',
                DB::raw('MAX(rs.nama_skpd) as nama_skpd'),
                DB::raw('COUNT(DISTINCT ik.id) as jumlah_paket'),
                DB::raw('COALESCE(SUM(ika.pagu), 0) as total_pagu'),
            ])
            ->groupBy('ik.kode_skpd')
            ->orderByDesc('jumlah_paket')
            ->get();

        // Agregat per jenis pengadaan
        $perJenis = (clone $base)
            ->select(['ik.jenis_pengadaan', DB::raw('COUNT(DISTINCT ik.id) as jumlah_paket'), DB::raw('COALESCE(SUM(ika.pagu), 0) as total_pagu')])
            ->groupBy('ik.jenis_pengadaan')
            ->orderByDesc('jumlah_paket')
            ->get();

        // Agregat per status
        $perStatus = (clone $base)
            ->select(['ik.status_review', DB::raw('COUNT(DISTINCT ik.id) as jumlah_paket')])
            ->groupBy('ik.status_review')
            ->orderBy('ik.status_review')
            ->get();

        // ---- Daftar paket LENGKAP sesuai template Laporan.xlsx (sheet Penyedia/Swakelola) ----
        // Header paket + nama hierarki (program/kegiatan/sub kegiatan) + pembuat.
        $paketRows = (clone $base)
            ->select([
                'ik.id',
                'ik.nama_paket',
                'ik.jenis_pengadaan',
                'ik.status_review',
                'ik.kode_skpd',
                'ik.kode_program',
                'ik.kode_kegiatan',
                'ik.kode_sub_kegiatan',
                'ik.form_data',
                'ik.waktu_pemanfaatan_awal',
                'ik.waktu_pemanfaatan_akhir',
                'ik.waktu_pemilihan_awal',
                'ik.waktu_pemilihan_akhir',
                'ik.waktu_pelaksanaan_kontrak_awal',
                'ik.waktu_pelaksanaan_kontrak_akhir',
                'ik.waktu_pelaksanaan_pekerjaan_awal',
                'ik.waktu_pelaksanaan_pekerjaan_akhir',
                DB::raw('MAX(rs.nama_skpd) as nama_skpd'),
                'u.nama as nama_user',
                'ik.created_at',
                'ik.updated_at',
            ])
            ->groupBy(
                'ik.id', 'ik.nama_paket', 'ik.jenis_pengadaan', 'ik.status_review', 'ik.kode_skpd',
                'ik.kode_program', 'ik.kode_kegiatan', 'ik.kode_sub_kegiatan', 'ik.form_data',
                'ik.waktu_pemanfaatan_awal', 'ik.waktu_pemanfaatan_akhir',
                'ik.waktu_pemilihan_awal', 'ik.waktu_pemilihan_akhir',
                'ik.waktu_pelaksanaan_kontrak_awal', 'ik.waktu_pelaksanaan_kontrak_akhir',
                'ik.waktu_pelaksanaan_pekerjaan_awal', 'ik.waktu_pelaksanaan_pekerjaan_akhir',
                'u.nama', 'ik.created_at', 'ik.updated_at'
            )
            ->orderByDesc('ik.updated_at')
            ->get();

        // Nama hierarki dari tabel referensi SIPD.
        $progNama = DB::table('dev.ref_program')->pluck('nama_program', 'kode_program');
        $kegNama = DB::table('dev.ref_kegiatan')->pluck('nama_kegiatan', 'kode_kegiatan');
        $subNama = DB::table('dev.ref_sub_kegiatan')->pluck('nama_sub_kegiatan', 'kode_sub_kegiatan');

        // Rincian anggaran (MAK + pagu) per paket — satu query untuk semua paket.
        $ids = $paketRows->pluck('id')->all();
        $anggaranRows = $ids
            ? DB::table('dev.identifikasi_kebutuhan_anggaran as a')
                ->leftJoin('dev.sipd_penetapan_apbd as sp', 'sp.id', '=', 'a.id_sipd_penetapan')
                ->leftJoin('dev.ref_standar_harga as sh', 'sh.kode_standar_harga', '=', 'a.kode_standar_harga')
                ->whereIn('a.identifikasi_kebutuhan_id', $ids)
                ->select([
                    'a.identifikasi_kebutuhan_id',
                    'a.kode_standar_harga',
                    'a.pagu',
                    DB::raw('COALESCE(sp.kode_rekening, a.kode_standar_harga) as kode_rekening'),
                    DB::raw('COALESCE(a.kode_standar_harga, \'\') as kode_standar_harga_out'),
                    DB::raw('COALESCE(sh.nama_standar_harga, \'\') as nama_standar_harga'),
                ])
                ->orderBy('a.identifikasi_kebutuhan_id')
                ->get()
                ->groupBy('identifikasi_kebutuhan_id')
            : collect();

        // Peta SKPD — dipakai untuk menentukan OPD induk (level teratas pohon laporan)
        // karena satu OPD bisa punya beberapa sub unit (kode_skpd anak).
        $skpdMap = DB::table('dev.ref_skpd')
            ->get(['kode_skpd', 'nama_skpd', 'parent_kode_skpd'])
            ->keyBy('kode_skpd');

        $rincian = [];
        foreach ($paketRows as $p) {
            $fd = is_string($p->form_data) ? json_decode($p->form_data, true) : (array) ($p->form_data ?? []);
            $fd = is_array($fd) ? $fd : [];

            $lokasi = [];
            $lokProvinsi = [];
            $lokKabupaten = [];
            $lokDetail = [];
            foreach ((array) ($fd['lokasi'] ?? []) as $l) {
                $l = (array) $l;
                $prov = trim((string) ($l['provinsi'] ?? ''));
                $kab = trim((string) ($l['kabupaten'] ?? ''));
                $kec = trim((string) ($l['kecamatan'] ?? ''));
                $det = trim((string) ($l['detail'] ?? ''));

                if ($prov !== '') {
                    $lokProvinsi[$prov] = true;
                }
                if ($kab !== '') {
                    $lokKabupaten[$kab] = true;
                }
                // Kolom "Detil Lokasi" pada template = kecamatan + detail alamat.
                $detil = implode(', ', array_filter([$kec, $det]));
                if ($detil !== '') {
                    $lokDetail[$detil] = true;
                }

                $bagian = array_filter([$prov, $kab, $kec, $det]);
                if ($bagian) {
                    $lokasi[] = implode(', ', $bagian);
                }
            }

            // OPD induk — kalau kode_skpd paket adalah sub unit, naik ke parent-nya.
            $kodeOpd = $skpdMap[$p->kode_skpd]->parent_kode_skpd ?? null;
            $kodeOpd = $kodeOpd ?: (string) $p->kode_skpd;
            $namaOpd = (string) ($skpdMap[$kodeOpd]->nama_skpd ?? $p->nama_skpd ?? $p->kode_skpd);

            $mak = $anggaranRows->get($p->id) ?? collect();
            $totalPagu = round((float) $mak->sum('pagu'), 2);

            // Satu baris laporan = satu KODE STANDAR HARGA (arahan atasan).
            // Kolom MAK menampilkan berurutan: kode sub kegiatan -> kode rekening -> kode standar.
            // Bila satu kode standar yang sama dipakai lebih dari sekali, pagunya dijumlahkan.
            $makRingkas = [];
            foreach ($mak as $m) {
                $rek = (string) ($m->kode_rekening ?? '');
                $standar = (string) ($m->kode_standar_harga_out ?? '');
                $kunci = $rek.'|'.$standar;
                if (! isset($makRingkas[$kunci])) {
                    $makRingkas[$kunci] = [
                        'kode_rekening' => $rek,
                        'kode_standar' => $standar,
                        'nama_standar' => (string) ($m->nama_standar_harga ?? ''),
                        // Dipertahankan untuk kompatibilitas pemakai lama.
                        'nama' => $standar,
                        'pagu' => 0.0,
                    ];
                }
                $makRingkas[$kunci]['pagu'] += round((float) $m->pagu, 2);
            }

            $rincian[] = [
                'id' => (int) $p->id,
                'nama_paket' => (string) $p->nama_paket,
                'jenis_pengadaan' => $p->jenis_pengadaan,
                'status_review' => $p->status_review,
                'kode_skpd' => (string) $p->kode_skpd,
                'nama_skpd' => (string) ($p->nama_skpd ?? $p->kode_skpd),
                'kode_program' => (string) ($p->kode_program ?? ''),
                'nama_program' => (string) ($progNama[$p->kode_program] ?? ''),
                'kode_kegiatan' => (string) ($p->kode_kegiatan ?? ''),
                'nama_kegiatan' => (string) ($kegNama[$p->kode_kegiatan] ?? ''),
                'kode_sub_kegiatan' => (string) ($p->kode_sub_kegiatan ?? ''),
                'nama_sub_kegiatan' => (string) ($subNama[$p->kode_sub_kegiatan] ?? ''),
                'nama_user' => (string) ($p->nama_user ?? ''),
                // Hierarki OPD induk (untuk pengelompokan pohon di laporan)
                'kode_opd' => (string) $kodeOpd,
                'nama_opd' => $namaOpd,
                // Detail isian form (template Laporan.xlsx)
                'lokasi' => $lokasi,
                'lokasi_provinsi' => implode('; ', array_keys($lokProvinsi)),
                'lokasi_kabupaten' => implode('; ', array_keys($lokKabupaten)),
                'lokasi_detail' => implode('; ', array_keys($lokDetail)),
                'volume' => (float) ($fd['volume'] ?? 0),
                'volume_satuan' => (string) ($fd['volume_satuan'] ?? 'Unit'),
                'uraian' => (string) ($fd['uraian'] ?? ($fd['uraian_pekerjaan'] ?? '')),
                'spesifikasi' => (string) ($fd['spesifikasi'] ?? ($fd['spesifikasi_pekerjaan'] ?? '')),
                'pdn' => (string) ($fd['pdn'] ?? ''),
                'usaha_kecil' => (string) ($fd['usaha_kecil'] ?? ''),
                'spp_ekonomi' => (string) ($fd['spp_ekonomi'] ?? ''),
                'spp_sosial' => (string) ($fd['spp_sosial'] ?? ''),
                'spp_lingkungan' => (string) ($fd['spp_lingkungan'] ?? ''),
                'pra_dpa' => (string) ($fd['pra_dpa'] ?? ''),
                'metode_pengadaan' => (string) ($fd['metode_pengadaan'] ?? ''),
                'tersedia_ekatalog' => (string) ($fd['tersedia_ekatalog'] ?? ($fd['tersedia_ekatalog_produk'] ?? '')),
                'sumber_dana' => (string) ($fd['sumber_dana'] ?? ''),
                'tipe_swakelola' => (string) ($fd['tipe_swakelola'] ?? ''),
                // Jadwal (awal & akhir per fase)
                'waktu_pemanfaatan_awal' => $p->waktu_pemanfaatan_awal,
                'waktu_pemanfaatan_akhir' => $p->waktu_pemanfaatan_akhir,
                'waktu_pemilihan_awal' => $p->waktu_pemilihan_awal,
                'waktu_pemilihan_akhir' => $p->waktu_pemilihan_akhir,
                'waktu_pelaksanaan_awal' => $p->waktu_pelaksanaan_kontrak_awal ?: $p->waktu_pelaksanaan_pekerjaan_awal,
                'waktu_pelaksanaan_akhir' => $p->waktu_pelaksanaan_kontrak_akhir ?: $p->waktu_pelaksanaan_pekerjaan_akhir,
                // Anggaran — satu baris per rekening (MAK).
                'mak' => array_values($makRingkas),
                'total_pagu' => $totalPagu,
                'updated_at' => $p->updated_at,
            ];
        }

        // Ringkasan lama (kompatibilitas): dari data rincian.
        $paket = collect($rincian);

        return response()->json([
            'status' => 'success',
            'data' => [
                'tahun' => (int) ($request->query('tahun') ?: date('Y')),
                'cara_pengadaan' => $isPenyedia ? 'Penyedia' : 'Swakelola',
                'summary' => [
                    'total_paket' => $paket->count(),
                    'total_pagu' => round((float) $paket->sum('total_pagu'), 2),
                    'total_skpd' => $perSkpd->count(),
                ],
                'per_skpd' => $perSkpd,
                'per_jenis' => $perJenis,
                'per_status' => $perStatus,
                'paket' => $paket,
                'rincian' => $rincian,
            ],
        ]);
    }

    /**
     * Ekspor laporan paket per cara pengadaan (Penyedia / Swakelola) ke Excel
     * mengikuti template Laporan.xlsx.
     *
     * GET /api/v1/laporan/penyedia/export | /api/v1/laporan/swakelola/export
     */
    public function paketPerCaraExport(Request $request, string $cara): BinaryFileResponse
    {
        $json = $this->paketPerCara($request, $cara)->getData(true);
        $data = $json['data'] ?? [];
        $caraNormalized = strtolower(trim($cara));

        return Excel::download(
            new \App\Exports\LaporanPaketExport($data, $caraNormalized),
            'laporan-'.($caraNormalized === 'penyedia' ? 'penyedia' : 'swakelola').'-identifikasi-'.date('Y-m-d').'.xlsx'
        );
    }

    /**
     * Data Berita Acara Pembahasan paket (Penyedia / Swakelola) — daftar paket
     * yang sudah diajukan (status Diajukan/Disetujui/Perlu Perbaikan) untuk
     * dibahas dalam rapat pembahasan.
     *
     * GET /api/v1/laporan/ba-pembahasan-penyedia | /laporan/ba-pembahasan-swakelola
     */
    public function baPembahasan(Request $request, string $cara): JsonResponse
    {
        $caraNormalized = strtoupper(trim($cara));
        $isPenyedia = $caraNormalized === 'PENYEDIA';
        if (! $isPenyedia && $caraNormalized !== 'SWAKELOLA') {
            return response()->json(['message' => 'Parameter cara harus Penyedia atau Swakelola.'], 422);
        }

        $kodeSkpd = $this->scopeKodeSkpd($request);
        $user = $request->user();
        $ppkCodes = $user?->ppkSubKegiatanCodes();

        $q = DB::table('dev.identifikasi_kebutuhan as ik')
            ->leftJoin('dev.identifikasi_kebutuhan_anggaran as ika', 'ika.identifikasi_kebutuhan_id', '=', 'ik.id')
            ->leftJoin('dev.ref_skpd as rs', 'rs.kode_skpd', '=', 'ik.kode_skpd')
            ->leftJoin('dev.users as u', 'u.id', '=', 'ik.user_id')
            ->where('ik.cara_pengadaan', $isPenyedia ? 'Penyedia' : 'Swakelola')
            ->whereIn('ik.status_review', ['Diajukan', 'Disetujui', 'Perlu Perbaikan']);

        if ($kodeSkpd) {
            $q->where('ik.kode_skpd', $kodeSkpd);
        }
        if ($ppkCodes !== null) {
            $q->whereIn('ik.kode_sub_kegiatan', $ppkCodes);
        }

        // Filter tahun anggaran (dari navbar) — kosong = semua tahun
        $tahun = (int) ($request->query('tahun') ?: 0);
        if ($tahun > 0) {
            $q->where('ik.tahun', $tahun);
        }

        $paket = (clone $q)
            ->select([
                'ik.id',
                'ik.nama_paket',
                'ik.jenis_pengadaan',
                'ik.status_review',
                'ik.kode_skpd',
                DB::raw('MAX(rs.nama_skpd) as nama_skpd'),
                'ik.kode_program',
                'ik.kode_kegiatan',
                'ik.kode_sub_kegiatan',
                'ik.catatan_reviewer',
                'u.nama as nama_user',
                'ik.updated_at',
                DB::raw('COALESCE(SUM(ika.pagu), 0) as total_pagu'),
            ])
            ->groupBy('ik.id', 'ik.nama_paket', 'ik.jenis_pengadaan', 'ik.status_review', 'ik.kode_skpd', 'ik.kode_program', 'ik.kode_kegiatan', 'ik.kode_sub_kegiatan', 'ik.catatan_reviewer', 'u.nama', 'ik.updated_at')
            ->orderByDesc('ik.updated_at')
            ->get();

        // Nama hierarki + pagu SIPD per sub kegiatan (kolom Pagu / Non Pengadaan / Pengadaan).
        $progNama = DB::table('dev.ref_program')->pluck('nama_program', 'kode_program');
        $kegNama = DB::table('dev.ref_kegiatan')->pluck('nama_kegiatan', 'kode_kegiatan');
        $subNama = DB::table('dev.ref_sub_kegiatan')->pluck('nama_sub_kegiatan', 'kode_sub_kegiatan');

        $subKegiatanCodes = $paket->pluck('kode_sub_kegiatan')->filter()->unique()->values()->all();
        $sipdPerSub = $subKegiatanCodes
            ? DB::table('dev.ref_sipd_view')
                ->whereIn('kode_sub_kegiatan', $subKegiatanCodes)
                ->select([
                    'kode_sub_kegiatan',
                    DB::raw('SUM(pagu) as pagu'),
                    DB::raw('SUM(CASE WHEN is_belanja_pengadaan = true THEN pagu ELSE 0 END) as pengadaan'),
                ])
                ->groupBy('kode_sub_kegiatan')
                ->pluck('pagu', 'kode_sub_kegiatan')
            : collect();
        $sipdPengadaanPerSub = $subKegiatanCodes
            ? DB::table('dev.ref_sipd_view')
                ->whereIn('kode_sub_kegiatan', $subKegiatanCodes)
                ->select([
                    'kode_sub_kegiatan',
                    DB::raw('SUM(CASE WHEN is_belanja_pengadaan = true THEN pagu ELSE 0 END) as pengadaan'),
                ])
                ->groupBy('kode_sub_kegiatan')
                ->pluck('pengadaan', 'kode_sub_kegiatan')
            : collect();

        // Catatan pembahasan (Rekomendasi A): catatan_reviewer terakhir; fallback catatan
        // terakhir dari riwayat review (tetap tersedia walau paket sudah disetujui).
        $catatanRiwayat = $paket->isNotEmpty()
            ? DB::table('dev.identifikasi_kebutuhan_riwayat as r')
                ->whereIn('r.identifikasi_kebutuhan_id', $paket->pluck('id')->all())
                ->whereNotNull('r.catatan')
                ->where('r.catatan', '!=', '')
                ->select('r.identifikasi_kebutuhan_id', 'r.catatan')
                ->orderBy('r.id', 'desc')
                ->get()
                ->groupBy('identifikasi_kebutuhan_id')
                ->map(fn ($g) => (string) $g->first()->catatan)
            : collect();

        $rows = $paket->map(function ($p) use ($progNama, $kegNama, $subNama, $sipdPerSub, $sipdPengadaanPerSub, $catatanRiwayat) {
            $paguSipd = (float) ($sipdPerSub[$p->kode_sub_kegiatan] ?? 0);
            $pengadaanSipd = (float) ($sipdPengadaanPerSub[$p->kode_sub_kegiatan] ?? 0);
            $catatan = trim((string) ($p->catatan_reviewer ?? ''));
            if ($catatan === '') {
                $catatan = (string) ($catatanRiwayat[$p->id] ?? '');
            }

            return [
                'id' => (int) $p->id,
                'nama_paket' => (string) $p->nama_paket,
                'jenis_pengadaan' => $p->jenis_pengadaan,
                'status_review' => $p->status_review,
                'kode_skpd' => (string) $p->kode_skpd,
                'nama_skpd' => (string) ($p->nama_skpd ?? $p->kode_skpd),
                'kode_program' => (string) ($p->kode_program ?? ''),
                'nama_program' => (string) ($progNama[$p->kode_program] ?? ''),
                'kode_kegiatan' => (string) ($p->kode_kegiatan ?? ''),
                'nama_kegiatan' => (string) ($kegNama[$p->kode_kegiatan] ?? ''),
                'kode_sub_kegiatan' => (string) ($p->kode_sub_kegiatan ?? ''),
                'nama_sub_kegiatan' => (string) ($subNama[$p->kode_sub_kegiatan] ?? ''),
                'nama_user' => (string) ($p->nama_user ?? ''),
                'pagu' => round((float) $p->total_pagu, 2),
                'belanja_pengadaan' => round($pengadaanSipd, 2),
                'belanja_non_pengadaan' => round($paguSipd - $pengadaanSipd, 2),
                'catatan_pembahasan' => $catatan,
                'updated_at' => $p->updated_at,
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'cara_pengadaan' => $isPenyedia ? 'Penyedia' : 'Swakelola',
                'summary' => [
                    'total_paket' => $rows->count(),
                    'total_pagu' => round((float) $rows->sum('pagu'), 2),
                ],
                'paket' => $rows,
            ],
        ]);
    }

    /**
     * Ekspor Berita Acara Pembahasan (Penyedia / Swakelola) ke Excel.
     *
     * GET /api/v1/laporan/ba-pembahasan-penyedia/export | /api/v1/laporan/ba-pembahasan-swakelola/export
     */
    public function baPembahasanExport(Request $request, string $cara): BinaryFileResponse
    {
        $json = $this->baPembahasan($request, $cara)->getData(true);
        $data = $json['data'] ?? [];
        $caraNormalized = strtolower(trim($cara));

        return Excel::download(
            new \App\Exports\BaPembahasanExport($data),
            'ba-pembahasan-'.$caraNormalized.'-'.date('Y-m-d').'.xlsx'
        );
    }

    /**
     * Data Berita Acara Catatan RKBMD (pengadaan / pemeliharaan) — ringkasan
     * barang RKBMD yang akan diadakan / yang sudah dimiliki untuk dilampirkan
     * sebagai catatan rapat.
     *
     * GET /api/v1/laporan/ba-rkbmd-pengadaan | /laporan/ba-rkbmd-pemeliharaan
     */
    public function baRkbmd(Request $request, string $tipe): JsonResponse
    {
        $tipeNormalized = strtolower(trim($tipe));
        if (! in_array($tipeNormalized, ['pengadaan', 'pemeliharaan'], true)) {
            return response()->json(['message' => 'Parameter tipe harus pengadaan atau pemeliharaan.'], 422);
        }

        $kodeSkpd = $this->scopeKodeSkpd($request);
        $user = $request->user();
        $ppkCodes = $user?->ppkSubKegiatanCodes();

        // Mode jawaban RKBMD per item pagu: pengadaan -> 'rencana', pemeliharaan -> 'aset'.
        $mode = $tipeNormalized === 'pengadaan' ? 'rencana' : 'aset';

        $q = DB::table('dev.identifikasi_kebutuhan as ik')
            ->leftJoin('dev.ref_skpd as rs', 'rs.kode_skpd', '=', 'ik.kode_skpd')
            ->leftJoin('dev.users as u', 'u.id', '=', 'ik.user_id')
            ->whereIn('ik.status_review', ['Diajukan', 'Disetujui', 'Perlu Perbaikan'])
            // Paket yang punya jawaban RKBMD sesuai tipe pada rkbmd_per_anggaran.
            ->whereRaw(
                "EXISTS (SELECT 1 FROM jsonb_array_elements(ik.form_data->'rkbmd_per_anggaran') p WHERE p->>'mode' = ?)",
                [$mode]
            );

        if ($kodeSkpd) {
            $q->where('ik.kode_skpd', $kodeSkpd);
        }
        if ($ppkCodes !== null) {
            $q->whereIn('ik.kode_sub_kegiatan', $ppkCodes);
        }

        // Filter tahun anggaran (dari navbar) — kosong = semua tahun
        $tahun = (int) ($request->query('tahun') ?: 0);
        if ($tahun > 0) {
            $q->where('ik.tahun', $tahun);
        }

        $paket = $q->select([
            'ik.id',
            'ik.nama_paket',
            'ik.jenis_pengadaan',
            'ik.status_review',
            'ik.kode_skpd',
            DB::raw('MAX(rs.nama_skpd) as nama_skpd'),
            'ik.kode_program',
            'ik.kode_kegiatan',
            'ik.kode_sub_kegiatan',
            'ik.catatan_reviewer',
            'ik.form_data',
            'u.nama as nama_user',
            'ik.updated_at',
        ])
            ->groupBy('ik.id', 'ik.nama_paket', 'ik.jenis_pengadaan', 'ik.status_review', 'ik.kode_skpd', 'ik.kode_program', 'ik.kode_kegiatan', 'ik.kode_sub_kegiatan', 'ik.catatan_reviewer', 'ik.form_data', 'u.nama', 'ik.updated_at')
            ->orderByDesc('ik.updated_at')
            ->get();

        $progNama = DB::table('dev.ref_program')->pluck('nama_program', 'kode_program');
        $kegNama = DB::table('dev.ref_kegiatan')->pluck('nama_kegiatan', 'kode_kegiatan');
        $subNama = DB::table('dev.ref_sub_kegiatan')->pluck('nama_sub_kegiatan', 'kode_sub_kegiatan');

        $catatanRiwayat = $paket->isNotEmpty()
            ? DB::table('dev.identifikasi_kebutuhan_riwayat as r')
                ->whereIn('r.identifikasi_kebutuhan_id', $paket->pluck('id')->all())
                ->whereNotNull('r.catatan')
                ->where('r.catatan', '!=', '')
                ->select('r.identifikasi_kebutuhan_id', 'r.catatan')
                ->orderBy('r.id', 'desc')
                ->get()
                ->groupBy('identifikasi_kebutuhan_id')
                ->map(fn ($g) => (string) $g->first()->catatan)
            : collect();

        $rows = $paket->map(function ($p) use ($mode, $progNama, $kegNama, $subNama, $catatanRiwayat) {
            $fd = is_string($p->form_data) ? json_decode($p->form_data, true) : (array) ($p->form_data ?? []);
            $fd = is_array($fd) ? $fd : [];

            // Item barang jawaban sesuai tipe + total unit.
            $items = [];
            $totalUnit = 0;
            foreach ((array) ($fd['rkbmd_per_anggaran'] ?? []) as $per) {
                $per = (array) $per;
                if (($per['mode'] ?? '') !== $mode) {
                    continue;
                }
                foreach ((array) ($per['items'] ?? []) as $it) {
                    $it = (array) $it;
                    $items[] = [
                        'nama_barang' => (string) ($it['nama_barang'] ?? ''),
                        'jumlah' => (float) ($it['jumlah'] ?? 0),
                        'satuan' => (string) ($it['satuan'] ?? ''),
                    ];
                    $totalUnit += (float) ($it['jumlah'] ?? 0);
                }
            }

            $catatan = trim((string) ($p->catatan_reviewer ?? ''));
            if ($catatan === '') {
                $catatan = (string) ($catatanRiwayat[$p->id] ?? '');
            }

            return [
                'id' => (int) $p->id,
                'nama_paket' => (string) $p->nama_paket,
                'jenis_pengadaan' => $p->jenis_pengadaan,
                'status_review' => $p->status_review,
                'kode_skpd' => (string) $p->kode_skpd,
                'nama_skpd' => (string) ($p->nama_skpd ?? $p->kode_skpd),
                'kode_program' => (string) ($p->kode_program ?? ''),
                'nama_program' => (string) ($progNama[$p->kode_program] ?? ''),
                'kode_kegiatan' => (string) ($p->kode_kegiatan ?? ''),
                'nama_kegiatan' => (string) ($kegNama[$p->kode_kegiatan] ?? ''),
                'kode_sub_kegiatan' => (string) ($p->kode_sub_kegiatan ?? ''),
                'nama_sub_kegiatan' => (string) ($subNama[$p->kode_sub_kegiatan] ?? ''),
                'nama_user' => (string) ($p->nama_user ?? ''),
                'jumlah_item' => count($items),
                'total_unit' => round($totalUnit, 2),
                'items' => $items,
                'catatan_pembahasan' => $catatan,
                'updated_at' => $p->updated_at,
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'tipe' => $tipeNormalized,
                'summary' => [
                    'total_paket' => $rows->count(),
                    'total_barang' => $rows->sum('jumlah_item'),
                    'total_unit' => round((float) $rows->sum('total_unit'), 2),
                ],
                'paket' => $rows,
            ],
        ]);
    }

    /**
     * Ekspor Berita Acara Catatan RKBMD (pengadaan / pemeliharaan) ke Excel.
     *
     * GET /api/v1/laporan/ba-rkbmd-pengadaan/export | /api/v1/laporan/ba-rkbmd-pemeliharaan/export
     */
    public function baRkbmdExport(Request $request, string $tipe): BinaryFileResponse
    {
        $json = $this->baRkbmd($request, $tipe)->getData(true);
        $data = $json['data'] ?? [];
        $tipeNormalized = strtolower(trim($tipe));

        return Excel::download(
            new \App\Exports\BaRkbmdExport($data),
            'ba-catatan-rkbmd-'.$tipeNormalized.'-'.date('Y-m-d').'.xlsx'
        );
    }

    /**
     * Ekspor rekapitulasi (tree 5 level) ke file Excel.
     *
     * GET /api/v1/laporan/rekap/export?tahun=2026&versi=&kode_skpd=
     */
    public function rekapExport(Request $request): BinaryFileResponse
    {
        $json = $this->rekap($request)->getData(true);
        $data = $json['data'] ?? [];

        return Excel::download(
            new LaporanRekapExport($data),
            'laporan-rekap-identifikasi-'.($data['tahun'] ?? date('Y')).'.xlsx'
        );
    }

    /**
     * Ekspor ringkasan kebutuhan (per SKPD/program/sumber dana) ke file Excel.
     *
     * GET /api/v1/laporan/kebutuhan/export?tahun=2026&versi=&kode_skpd=
     */
    public function kebutuhanExport(Request $request): BinaryFileResponse
    {
        $json = $this->kebutuhan($request)->getData(true);
        $data = $json['data'] ?? [];

        return Excel::download(
            new LaporanKebutuhanExport($data),
            'laporan-kebutuhan-identifikasi-'.($data['tahun'] ?? date('Y')).'.xlsx'
        );
    }

    /**
     * Ringkasan kebutuhan pengadaan per SKPD, per program, dan per sumber dana.
     *
     * GET /api/v1/laporan/kebutuhan?tahun=2026&versi=&kode_skpd=
     */
    public function kebutuhan(Request $request): JsonResponse
    {
        $user = $request->user();
        $tahun = (int) ($request->query('tahun') ?: date('Y'));
        $versi = $request->query('versi');
        $kodeSkpd = $request->query('kode_skpd') ?: $this->scopeKodeSkpd($request);

        $q = DB::table('dev.ref_sipd_view as rsv')
            ->where('rsv.tahun', $tahun)
            ->select([
                'rsv.kode_skpd',
                DB::raw('MAX(rsv.nama_skpd) as nama_skpd'),
                'rsv.kode_sub_unit',
                'rsv.kode_program',
                DB::raw('MAX(rsv.nama_program) as nama_program'),
                'rsv.kode_sumber_dana',
                DB::raw('MAX(rsv.nama_sumber_dana) as nama_sumber_dana'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(rsv.pagu) as total_pagu'),
                DB::raw('SUM(CASE WHEN rsv.is_belanja_pengadaan = true THEN rsv.pagu ELSE 0 END) as pagu_pengadaan'),
            ])
            ->groupBy(['rsv.kode_skpd', 'rsv.kode_sub_unit', 'rsv.kode_program', 'rsv.kode_sumber_dana']);

        if ($versi) {
            $q->where('rsv.versi', $versi);
        }
        if ($kodeSkpd) {
            $q->where('rsv.kode_skpd', $kodeSkpd);
        }

        $rows = $q->get();

        $bySkpd = [];
        $byProgram = [];
        $bySumberDana = [];
        $totalPagu = 0.0;
        $totalRincian = 0;
        $totalPengadaan = 0.0;

        foreach ($rows as $r) {
            $pagu = (float) $r->total_pagu;
            $pengadaan = (float) $r->pagu_pengadaan;
            $totalPagu += $pagu;
            $totalRincian += (int) $r->count;
            $totalPengadaan += $pengadaan;

            $bySkpd[$r->kode_skpd] = [
                'kode' => $r->kode_skpd,
                'nama' => (string) $r->nama_skpd,
                'total' => ($bySkpd[$r->kode_skpd]['total'] ?? 0) + $pagu,
                'count' => ($bySkpd[$r->kode_skpd]['count'] ?? 0) + (int) $r->count,
                'pengadaan' => ($bySkpd[$r->kode_skpd]['pengadaan'] ?? 0) + $pengadaan,
            ];

            $byProgram[$r->kode_program] = [
                'kode' => $r->kode_program,
                'nama' => (string) $r->nama_program,
                'total' => ($byProgram[$r->kode_program]['total'] ?? 0) + $pagu,
                'count' => ($byProgram[$r->kode_program]['count'] ?? 0) + (int) $r->count,
            ];

            $keySd = (string) ($r->kode_sumber_dana ?: '-');
            $bySumberDana[$keySd] = [
                'kode' => $keySd,
                'nama' => (string) ($r->nama_sumber_dana ?: 'Tanpa Sumber Dana'),
                'total' => ($bySumberDana[$keySd]['total'] ?? 0) + $pagu,
                'count' => ($bySumberDana[$keySd]['count'] ?? 0) + (int) $r->count,
            ];
        }

        $sortDesc = function (array &$arr) {
            uasort($arr, fn ($a, $b) => $b['total'] <=> $a['total']);
        };
        $sortDesc($bySkpd);
        $sortDesc($byProgram);
        $sortDesc($bySumberDana);

        // Daftar versi yang tersedia untuk tahun tsb (untuk info/filter).
        $versiList = DB::table('dev.sipd_penetapan_apbd')
            ->where('tahun', $tahun)
            ->whereNotNull('versi')
            ->distinct()
            ->orderBy('versi')
            ->pluck('versi')
            ->map(fn ($v) => ['versi' => (string) $v])
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'tahun' => $tahun,
                'summary' => [
                    'total_pagu' => round($totalPagu, 2),
                    'total_pengadaan' => round($totalPengadaan, 2),
                    'total_rincian' => $totalRincian,
                    'total_skpd' => count($bySkpd),
                    'total_program' => count($byProgram),
                    'total_sumber_dana' => count($bySumberDana),
                ],
                'versi_list' => $versiList,
                'skpd' => array_values($bySkpd),
                'program' => array_values($byProgram),
                'sumber_dana' => array_values($bySumberDana),
            ],
        ]);
    }
}
