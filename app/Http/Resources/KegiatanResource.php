<?php

namespace App\Http\Resources;

use App\Models\RefKegiatan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RefKegiatan
 */
class KegiatanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'kode_kegiatan' => $this->kode_kegiatan,
            'nama_kegiatan' => $this->nama_kegiatan,
            'kode_program' => $this->kode_program,
        ];
    }
}
