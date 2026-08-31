<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'username' => $this->username,
            'role' => $this->role,
            'kode_skpd' => $this->kode_skpd,
            'info' => $this->info,
            'created_at' => $this->created_at?->toISOString(),
            'skpd' => $this->whenLoaded('skpd', function () {
                return [
                    'kode_skpd' => $this->skpd?->kode_skpd,
                    'nama_skpd' => $this->skpd?->nama_skpd,
                    'parent_kode_skpd' => $this->skpd?->parent_kode_skpd,
                ];
            }),
            'sub_kegiatan' => $this->whenLoaded('subKegiatan', function () {
                return $this->subKegiatan->map(function ($item) {
                    return [
                        'kode_sub_kegiatan' => $item->kode_sub_kegiatan,
                        'kode_kegiatan' => $item->kode_kegiatan,
                        'nama_sub_kegiatan' => $item->nama_sub_kegiatan,
                    ];
                });
            }),
        ];
    }
}
