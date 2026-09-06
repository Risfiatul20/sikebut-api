<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RefAkunResource;
use App\Http\Resources\RefAkunViewResource;
use App\Models\RefAkun;
use App\Models\RefAkunView;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RefAkunController extends Controller
{
    /**
     * Display a listing of hierarchical Ref Akun with RKBMD indicators.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User|null $currentUser */
        $currentUser = $request->user();

        $search = $request->query('search');
        $level = $request->query('level');
        $parent = $request->query('parent');
        $kodeSubUnit = $request->query('kode_sub_unit') ?? $currentUser?->kode_skpd;
        $perPage = (int) $request->query('per_page', 0);

        $query = RefAkun::with('indikator');

        if ($kodeSubUnit) {
            $distinctAccounts = DB::table('dev.sipd_penetapan_apbd')
                ->where('kode_sub_unit', $kodeSubUnit)
                ->whereNotNull('kode_rekening')
                ->distinct()
                ->pluck('kode_rekening');

            $allAllowedCodes = [];
            foreach ($distinctAccounts as $rek) {
                $parts = explode('.', (string) $rek);
                $prefix = '';
                foreach ($parts as $part) {
                    $prefix = $prefix === '' ? $part : $prefix.'.'.$part;
                    $allAllowedCodes[$prefix] = true;
                }
            }

            $query->whereIn('kode_akun', array_keys($allAllowedCodes));
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('kode_akun', 'ilike', "%{$search}%")
                    ->orWhere('nama_akun', 'ilike', "%{$search}%");
            });
        }

        if ($level !== null && $level !== '') {
            $query->where('level_akun', (int) $level);
        }

        if ($parent !== null && $parent !== '') {
            $query->where('parent_kode_akun', $parent);
        }

        $belanjaPengadaan = $request->query('belanja_pengadaan') ?? $request->query('is_belanja_pengadaan') ?? $request->query('b');
        if ($belanjaPengadaan !== null) {
            $val = filter_var($belanjaPengadaan, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($val !== null) {
                $query->whereHas('indikator', function ($q) use ($val) {
                    $q->where('is_belanja_pengadaan', $val);
                });
            }
        }

        $rkbmdPengadaan = $request->query('rkbmd_pengadaan') ?? $request->query('is_rkbmd_pengadaan') ?? $request->query('r');
        if ($rkbmdPengadaan !== null) {
            $val = filter_var($rkbmdPengadaan, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($val !== null) {
                $query->whereHas('indikator', function ($q) use ($val) {
                    $q->where('is_rkbmd_pengadaan', $val);
                });
            }
        }

        $pemeliharaanRehab = $request->query('pemeliharaan_rehab') ?? $request->query('is_rkbmd_pemeliharaan_rehab') ?? $request->query('h');
        if ($pemeliharaanRehab !== null) {
            $val = filter_var($pemeliharaanRehab, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($val !== null) {
                $query->whereHas('indikator', function ($q) use ($val) {
                    $q->where('is_rkbmd_pemeliharaan_rehab', $val);
                });
            }
        }

        $pemeliharaanRutin = $request->query('pemeliharaan_rutin') ?? $request->query('is_rkbmd_pemeliharaan_rutin') ?? $request->query('t');
        if ($pemeliharaanRutin !== null) {
            $val = filter_var($pemeliharaanRutin, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($val !== null) {
                $query->whereHas('indikator', function ($q) use ($val) {
                    $q->where('is_rkbmd_pemeliharaan_rutin', $val);
                });
            }
        }

        $query->orderBy('kode_akun', 'asc');

        $akuns = $perPage > 0 ? $query->paginate($perPage) : $query->get();

        return RefAkunResource::collection($akuns);
    }

    /**
     * Display a flattened table listing from dev.ref_akun_view.
     */
    public function view(Request $request): AnonymousResourceCollection
    {
        /** @var User|null $currentUser */
        $currentUser = $request->user();

        $search = $request->query('search');
        $kode2 = $request->query('kode_2');
        $kode3 = $request->query('kode_3');
        $kode4 = $request->query('kode_4');
        $kode5 = $request->query('kode_5');
        $kodeSubUnit = $request->query('kode_sub_unit') ?? $currentUser?->kode_skpd;
        $perPage = (int) $request->query('per_page', 0);

        $belanjaPengadaan = $request->query('belanja_pengadaan') ?? $request->query('is_belanja_pengadaan') ?? $request->query('b');
        $rkbmdPengadaan = $request->query('rkbmd_pengadaan') ?? $request->query('is_rkbmd_pengadaan') ?? $request->query('r');
        $pemeliharaanRehab = $request->query('pemeliharaan_rehab') ?? $request->query('is_rkbmd_pemeliharaan_rehab') ?? $request->query('h');
        $pemeliharaanRutin = $request->query('pemeliharaan_rutin') ?? $request->query('is_rkbmd_pemeliharaan_rutin') ?? $request->query('t');

        $cacheKey = "ref-akun-view:{$currentUser?->id}:{$kodeSubUnit}:{$search}:{$kode2}:{$kode3}:{$kode4}:{$kode5}:{$belanjaPengadaan}:{$rkbmdPengadaan}:{$pemeliharaanRehab}:{$pemeliharaanRutin}";

        $items = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($kodeSubUnit, $search, $kode2, $kode3, $kode4, $kode5, $belanjaPengadaan, $rkbmdPengadaan, $pemeliharaanRehab, $pemeliharaanRutin) {
            $query = RefAkunView::query();

            if ($kodeSubUnit) {
                $distinctAccounts = DB::table('dev.sipd_penetapan_apbd')
                    ->where('kode_sub_unit', $kodeSubUnit)
                    ->whereNotNull('kode_rekening')
                    ->distinct()
                    ->pluck('kode_rekening');

                $query->whereIn('kode_6', $distinctAccounts);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('kode_6', 'ilike', "%{$search}%")
                        ->orWhere('nama_6', 'ilike', "%{$search}%")
                        ->orWhere('nama_5', 'ilike', "%{$search}%")
                        ->orWhere('nama_4', 'ilike', "%{$search}%")
                        ->orWhere('nama_3', 'ilike', "%{$search}%")
                        ->orWhere('nama_2', 'ilike', "%{$search}%");
                });
            }

            if ($kode2) {
                $query->where('kode_2', $kode2);
            }
            if ($kode3) {
                $query->where('kode_3', $kode3);
            }
            if ($kode4) {
                $query->where('kode_4', $kode4);
            }
            if ($kode5) {
                $query->where('kode_5', $kode5);
            }

            if ($belanjaPengadaan !== null) {
                $val = filter_var($belanjaPengadaan, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($val !== null) {
                    $query->where('is_belanja_pengadaan', $val);
                }
            }

            if ($rkbmdPengadaan !== null) {
                $val = filter_var($rkbmdPengadaan, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($val !== null) {
                    $query->where('is_rkbmd_pengadaan', $val);
                }
            }

            if ($pemeliharaanRehab !== null) {
                $val = filter_var($pemeliharaanRehab, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($val !== null) {
                    $query->where('is_rkbmd_pemeliharaan_rehab', $val);
                }
            }

            if ($pemeliharaanRutin !== null) {
                $val = filter_var($pemeliharaanRutin, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($val !== null) {
                    $query->where('is_rkbmd_pemeliharaan_rutin', $val);
                }
            }

            return $query->orderBy('kode_6', 'asc')->get();
        });

        $collection = new Collection($items->all());

        if ($perPage > 0) {
            $paginator = new LengthAwarePaginator(
                $collection->forPage(1, $perPage),
                $collection->count(),
                $perPage,
                1
            );

            return RefAkunViewResource::collection($paginator);
        }

        return RefAkunViewResource::collection($collection);
    }
}
