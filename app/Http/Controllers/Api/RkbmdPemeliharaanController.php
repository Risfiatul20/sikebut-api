<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RkbmdPemeliharaanResource;
use App\Models\RkbmdPemeliharaan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RkbmdPemeliharaanController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = RkbmdPemeliharaan::query();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_barang', 'ilike', "%{$search}%")
                    ->orWhere('kode_fikasi', 'ilike', "%{$search}%")
                    ->orWhere('nama_skpd', 'ilike', "%{$search}%")
                    ->orWhere('nama_pemeliharaan', 'ilike', "%{$search}%");
            });
        }

        if ($kodeSkpd = $request->query('kode_skpd')) {
            $query->where('kode_skpd', $kodeSkpd);
        }

        if ($periode = $request->query('periode')) {
            $query->where('periode', (int) $periode);
        }

        $perPage = (int) $request->query('per_page', 15);

        return RkbmdPemeliharaanResource::collection($query->paginate($perPage));
    }

    public function show(int $id): RkbmdPemeliharaanResource
    {
        $data = RkbmdPemeliharaan::findOrFail($id);

        return new RkbmdPemeliharaanResource($data);
    }
}
