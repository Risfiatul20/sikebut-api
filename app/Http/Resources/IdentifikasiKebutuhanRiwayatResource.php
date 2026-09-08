<?php

namespace App\Http\Resources;

use App\Models\IdentifikasiKebutuhanRiwayat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin IdentifikasiKebutuhanRiwayat
 */
class IdentifikasiKebutuhanRiwayatResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'identifikasi_kebutuhan_id' => $this->identifikasi_kebutuhan_id,
            'status_dari' => $this->status_dari,
            'status_ke' => $this->status_ke,
            'catatan' => $this->catatan,
            'user_id' => $this->user_id,
            'pembuat' => $this->whenLoaded('pembuat', fn () => [
                'id' => $this->pembuat?->id,
                'nama' => $this->pembuat?->nama,
                'username' => $this->pembuat?->username,
                'role' => $this->pembuat?->role,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}