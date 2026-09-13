<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RefSkpdResource;
use App\Models\RefSkpd;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RefSkpdController extends Controller
{
    /**
     * Display a listing of SKPD data.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = $request->query('search');
        $isSubUnit = $request->query('is_sub_unit');
        $parentKode = $request->query('parent_kode_skpd');
        $sortBy = $request->query('sort_by', 'kode_skpd');
        $sortDirection = strtolower($request->query('sort_direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $perPage = (int) $request->query('per_page', 0);

        $allowedSorts = ['kode_skpd', 'nama_skpd', 'parent_kode_skpd'];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'kode_skpd';
        }

        $query = RefSkpd::with('parent');

        $currentUser = $request->user();
        if ($currentUser) {
            $userRole = strtolower(trim((string) $currentUser->role));

            if ($userRole === 'kepala opd') {
                $query->where(function ($q) use ($currentUser) {
                    $q->where('kode_skpd', $currentUser->kode_skpd)
                        ->orWhere('parent_kode_skpd', $currentUser->kode_skpd);
                });
            } elseif ($userRole === 'kepala sub unit') {
                $query->where('kode_skpd', $currentUser->kode_skpd);
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('kode_skpd', 'ilike', "%{$search}%")
                    ->orWhere('nama_skpd', 'ilike', "%{$search}%");
            });
        }

        if ($isSubUnit !== null) {
            $filterBool = filter_var($isSubUnit, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($filterBool === true) {
                $query->whereNotNull('parent_kode_skpd')->where('parent_kode_skpd', '!=', '');
            } elseif ($filterBool === false) {
                $query->where(function ($q) {
                    $q->whereNull('parent_kode_skpd')->orWhere('parent_kode_skpd', '');
                });
            }
        }

        if ($parentKode) {
            $query->where('parent_kode_skpd', $parentKode);
        }

        $query->orderBy($sortBy, $sortDirection);

        $skpds = $perPage > 0 ? $query->paginate($perPage) : $query->get();

        return RefSkpdResource::collection($skpds);
    }
}
