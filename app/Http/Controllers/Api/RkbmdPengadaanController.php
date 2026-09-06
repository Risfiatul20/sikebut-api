<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RkbmdPengadaanResource;
use App\Models\RkbmdPengadaan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RkbmdPengadaanController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = RkbmdPengadaan::query();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_barang', 'ilike', "%{$search}%")
                    ->orWhere('kode_fikasi', 'ilike', "%{$search}%")
                    ->orWhere('nama_skpd', 'ilike', "%{$search}%");
            });
        }

        if ($kodeSkpd = $request->query('kode_skpd')) {
            $query->where('kode_skpd', $kodeSkpd);
        }

        if ($periode = $request->query('periode')) {
            $query->where('periode', (int) $periode);
        }

        $perPage = (int) $request->query('per_page', 15);

        return RkbmdPengadaanResource::collection($query->paginate($perPage));
    }

    public function show(int $id): RkbmdPengadaanResource
    {
        $data = RkbmdPengadaan::findOrFail($id);

        return new RkbmdPengadaanResource($data);
    }
}
