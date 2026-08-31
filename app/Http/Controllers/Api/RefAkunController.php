<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RefAkunResource;
use App\Models\RefAkun;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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

        if ($request->has('b')) {
            $b = filter_var($request->query('b'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($b !== null) {
                $query->whereHas('indikator', function ($q) use ($b) {
                    $q->where('is_belanja_pengadaan', $b);
                });
            }
        }

        if ($request->has('r')) {
            $r = filter_var($request->query('r'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($r !== null) {
                $query->whereHas('indikator', function ($q) use ($r) {
                    $q->where('is_rkbmd_pengadaan', $r);
                });
            }
        }

        if ($request->has('h')) {
            $h = filter_var($request->query('h'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($h !== null) {
                $query->whereHas('indikator', function ($q) use ($h) {
                    $q->where('is_rkbmd_pemeliharaan_rehab', $h);
                });
            }
        }

        if ($request->has('t')) {
            $t = filter_var($request->query('t'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($t !== null) {
                $query->whereHas('indikator', function ($q) use ($t) {
                    $q->where('is_rkbmd_pemeliharaan_rutin', $t);
                });
            }
        }

        $query->orderBy('kode_akun', 'asc');

        $akuns = $perPage > 0 ? $query->paginate($perPage) : $query->get();

        return RefAkunResource::collection($akuns);
    }
}
