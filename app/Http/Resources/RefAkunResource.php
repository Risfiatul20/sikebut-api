<?php

namespace App\Http\Resources;

use App\Models\RefAkun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RefAkun
 */
class RefAkunResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'kode' => $this->kode_akun,
            'nama' => $this->nama_akun,
            'level' => (int) $this->level_akun,
            'parent' => $this->parent_kode_akun,
            'b' => (bool) ($this->indikator?->is_belanja_pengadaan ?? false),
            'r' => (bool) ($this->indikator?->is_rkbmd_pengadaan ?? false),
            'h' => (bool) ($this->indikator?->is_rkbmd_pemeliharaan_rehab ?? false),
            't' => (bool) ($this->indikator?->is_rkbmd_pemeliharaan_rutin ?? false),
        ];
    }
}
