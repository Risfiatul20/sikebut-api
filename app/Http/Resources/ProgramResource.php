<?php

namespace App\Http\Resources;

use App\Models\RefProgram;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RefProgram
 */
class ProgramResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'kode_program' => $this->kode_program,
            'nama_program' => $this->nama_program,
            'kode_bidang_urusan' => $this->kode_bidang_urusan,
            'nama_bidang_urusan' => $this->whenLoaded('bidangUrusan', fn () => $this->bidangUrusan->nama_bidang_urusan),
        ];
    }
}
