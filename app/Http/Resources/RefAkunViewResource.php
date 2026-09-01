<?php

namespace App\Http\Resources;

use App\Models\RefAkunView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RefAkunView
 */
class RefAkunViewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'kode_2' => $this->kode_2,
            'nama_2' => $this->nama_2,
            'kode_3' => $this->kode_3,
            'nama_3' => $this->nama_3,
            'kode_4' => $this->kode_4,
            'nama_4' => $this->nama_4,
            'kode_5' => $this->kode_5,
            'nama_5' => $this->nama_5,
            'kode_6' => $this->kode_6,
            'nama_6' => $this->nama_6,
            'b' => (bool) $this->is_belanja_pengadaan,
            'r' => (bool) $this->is_rkbmd_pengadaan,
            'h' => (bool) $this->is_rkbmd_pemeliharaan_rehab,
            't' => (bool) $this->is_rkbmd_pemeliharaan_rutin,
        ];
    }
}
