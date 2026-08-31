<?php

namespace App\Http\Resources;

use App\Models\RefSkpd;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RefSkpd
 */
class RefSkpdResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isSubUnit = ! empty($this->parent_kode_skpd);

        return [
            'kode_skpd' => $this->kode_skpd,
            'nama_skpd' => $this->nama_skpd,
            'parent_kode_skpd' => $this->parent_kode_skpd,
            'is_sub_unit' => $isSubUnit,
            'parent' => $this->when($isSubUnit && $this->relationLoaded('parent') && $this->parent, function () {
                return [
                    'kode_skpd' => $this->parent?->kode_skpd,
                    'nama_skpd' => $this->parent?->nama_skpd,
                ];
            }),
        ];
    }
}
