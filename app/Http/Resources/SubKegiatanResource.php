<?php

namespace App\Http\Resources;

use App\Models\RefSubKegiatan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RefSubKegiatan
 */
class SubKegiatanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'kode_sub_kegiatan' => $this->kode_sub_kegiatan,
            'nama_sub_kegiatan' => $this->nama_sub_kegiatan,
            'kode_kegiatan' => $this->kode_kegiatan,
        ];
    }
}
