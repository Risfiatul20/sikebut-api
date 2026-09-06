<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RkbmdPengadaan extends Model
{
    use HasFactory;

    // Nama tabel dan skema PostgreSQL
    protected $table = 'dev.rkbmd_pengadaan';

    // Primary key
    protected $primaryKey = 'id_pengadaan';

    public $incrementing = false; // Set false jika id_pengadaan bukan auto-increment serial

    // Mengaktifkan hanya created_at (tanpa updated_at)
    const UPDATED_AT = null;

    // Kolom yang dapat diisi secara massal (mass assignable)
    protected $fillable = [
        'id_pengadaan',
        'id_instansi',
        'id_renja',
        'kode_fikasi',
        'nama_barang',
        'jumlah_barang',
        'satuan',
        'jumlah_maksimum',
        'keterangan',
        'id_status',
        'periode',
        'nm_status',
        'cara_pemenuhan',
        'target',
        'nama_giat_nama_giat',
        'nama_sub_giat_nama_sub_giat',
        'id_kebutuhan',
        'id_sub',
        'nomekelatur',
        'outputbaru',
        'id_status_kebutuhan',
        'catatan_notulen',
        'kode_program',
        'kode_giat',
        'kode_sub_giat',
        'status_barang_ds',
        'status_barang_pp',
        'nama_program',
        'nama_skpd',
        'nama_sub_skpd',
        'kode_skpd',
        'kode_sub_skpd',
    ];

    // Casting tipe data
    protected $casts = [
        'id_pengadaan' => 'integer',
        'id_instansi' => 'integer',
        'id_renja' => 'integer',
        'jumlah_barang' => 'integer',
        'jumlah_maksimum' => 'integer',
        'id_status' => 'integer',
        'periode' => 'integer',
        'id_kebutuhan' => 'integer',
        'id_sub' => 'integer',
        'id_status_kebutuhan' => 'integer',
        'status_barang_ds' => 'integer',
        'status_barang_pp' => 'integer',
        'created_at' => 'datetime',
    ];
}
