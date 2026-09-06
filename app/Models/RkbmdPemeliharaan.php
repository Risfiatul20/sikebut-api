<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RkbmdPemeliharaan extends Model
{
    use HasFactory;

    // Nama tabel dan skema PostgreSQL
    protected $table = 'dev.rkbmd_pemeliharaan';

    // Primary key
    protected $primaryKey = 'id_pemeliharaan';

    public $incrementing = false; // Set false jika id_pemeliharaan bukan auto-increment serial

    // Mengaktifkan hanya created_at (tanpa updated_at)
    const UPDATED_AT = null;

    // Kolom yang dapat diisi secara massal (mass assignable)
    protected $fillable = [
        'id_pemeliharaan',
        'id_instansi',
        'id_renja',
        'kode_fikasi',
        'nama_barang',
        'jumlah_barang',
        'status_barang',
        'satuan',
        'kondisi_b',
        'kondisi_rr',
        'kondisi_rb',
        'nama_pemeliharaan',
        'jumlah_pemeliharaan',
        'satuan_pemeliharaan',
        'keterangan',
        'id_status',
        'periode',
        'nm_status',
        'target',
        'nama_giat_nama_giat',
        'id_kebutuhan',
        'id_status_kebutuhan',
        'catatan_notulen',
        'kode_program',
        'kode_kegiatan',
        'kode_sub_kegiatan',
        'id_sub_update',
        'nomekelatur_update',
        'nama_sub_giat_nama_sub_giat',
        'status_barang_ds',
        'status_barang_pp',
        'nama_program',
        'nama_skpd',
        'kode_skpd',
        'nama_sub_skpd',
        'kode_sub_skpd',
    ];

    // Casting tipe data
    protected $casts = [
        'id_pemeliharaan' => 'integer',
        'id_instansi' => 'integer',
        'id_renja' => 'integer',
        'jumlah_barang' => 'integer',
        'status_barang' => 'integer',
        'kondisi_b' => 'integer',
        'kondisi_rr' => 'integer',
        'kondisi_rb' => 'integer',
        'jumlah_pemeliharaan' => 'integer',
        'id_status' => 'integer',
        'periode' => 'integer',
        'id_kebutuhan' => 'integer',
        'id_status_kebutuhan' => 'integer',
        'id_sub_update' => 'integer',
        'status_barang_ds' => 'integer',
        'status_barang_pp' => 'integer',
        'created_at' => 'datetime',
    ];
}
