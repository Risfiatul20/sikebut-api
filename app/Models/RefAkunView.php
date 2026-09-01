<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefAkunView extends Model
{
    use HasFactory;

    protected $table = 'dev.ref_akun_view';

    protected $primaryKey = 'kode_6';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_belanja_pengadaan' => 'boolean',
            'is_rkbmd_pengadaan' => 'boolean',
            'is_rkbmd_pemeliharaan_rehab' => 'boolean',
            'is_rkbmd_pemeliharaan_rutin' => 'boolean',
        ];
    }
}
