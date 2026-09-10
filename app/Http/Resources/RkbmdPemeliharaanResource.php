<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RkbmdPemeliharaanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_pemeliharaan' => $this->id_pemeliharaan,
            'id_instansi' => $this->id_instansi,
            'id_renja' => $this->id_renja,
            'kode_fikasi' => $this->kode_fikasi,
            'nama_barang' => $this->nama_barang,
            'jumlah_barang' => $this->jumlah_barang,
            'status_barang' => $this->status_barang,
            'satuan' => $this->satuan,
            'sudah_diisi' => $this->sudah_diisi ?? 0,
            'kondisi_b' => $this->kondisi_b,
            'kondisi_rr' => $this->kondisi_rr,
            'kondisi_rb' => $this->kondisi_rb,
            'nama_pemeliharaan' => $this->nama_pemeliharaan,
            'jumlah_pemeliharaan' => $this->jumlah_pemeliharaan,
            'satuan_pemeliharaan' => $this->satuan_pemeliharaan,
            'keterangan' => $this->keterangan,
            'id_status' => $this->id_status,
            'periode' => $this->periode,
            'nm_status' => $this->nm_status,
            'target' => $this->target,
            'nama_giat_nama_giat' => $this->nama_giat_nama_giat,
            'id_kebutuhan' => $this->id_kebutuhan,
            'id_status_kebutuhan' => $this->id_status_kebutuhan,
            'catatan_notulen' => $this->catatan_notulen,
            'kode_program' => $this->kode_program,
            'kode_kegiatan' => $this->kode_kegiatan,
            'kode_sub_kegiatan' => $this->kode_sub_kegiatan,
            'id_sub_update' => $this->id_sub_update,
            'nomekelatur_update' => $this->nomekelatur_update,
            'nama_sub_giat_nama_sub_giat' => $this->nama_sub_giat_nama_sub_giat,
            'status_barang_ds' => $this->status_barang_ds,
            'status_barang_pp' => $this->status_barang_pp,
            'nama_program' => $this->nama_program,
            'nama_skpd' => $this->nama_skpd,
            'kode_skpd' => $this->kode_skpd,
            'nama_sub_skpd' => $this->nama_sub_skpd,
            'kode_sub_skpd' => $this->kode_sub_skpd,
            'created_at' => $this->created_at,
        ];
    }
}
