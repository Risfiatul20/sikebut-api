<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RkbmdPengadaanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_pengadaan' => $this->id_pengadaan,
            'id_instansi' => $this->id_instansi,
            'id_renja' => $this->id_renja,
            'kode_fikasi' => $this->kode_fikasi,
            'nama_barang' => $this->nama_barang,
            'jumlah_barang' => $this->jumlah_barang,
            'satuan' => $this->satuan,
            'jumlah_maksimum' => $this->jumlah_maksimum,
            'keterangan' => $this->keterangan,
            'id_status' => $this->id_status,
            'periode' => $this->periode,
            'nm_status' => $this->nm_status,
            'cara_pemenuhan' => $this->cara_pemenuhan,
            'target' => $this->target,
            'nama_giat_nama_giat' => $this->nama_giat_nama_giat,
            'nama_sub_giat_nama_sub_giat' => $this->nama_sub_giat_nama_sub_giat,
            'id_kebutuhan' => $this->id_kebutuhan,
            'id_sub' => $this->id_sub,
            'nomekelatur' => $this->nomekelatur,
            'outputbaru' => $this->outputbaru,
            'id_status_kebutuhan' => $this->id_status_kebutuhan,
            'catatan_notulen' => $this->catatan_notulen,
            'kode_program' => $this->kode_program,
            'kode_giat' => $this->kode_giat,
            'kode_sub_giat' => $this->kode_sub_giat,
            'status_barang_ds' => $this->status_barang_ds,
            'status_barang_pp' => $this->status_barang_pp,
            'nama_program' => $this->nama_program,
            'nama_skpd' => $this->nama_skpd,
            'nama_sub_skpd' => $this->nama_sub_skpd,
            'kode_skpd' => $this->kode_skpd,
            'kode_sub_skpd' => $this->kode_sub_skpd,
            'created_at' => $this->created_at,
        ];
    }
}
