<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefSipdView extends Model
{
    use HasFactory;

    protected $table = 'dev.ref_sipd_view';

    protected $primaryKey = null; // No primary key defined in the view

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'pagu' => 'decimal:2',
            'is_belanja_pengadaan' => 'boolean',
            'is_rkbmd_pengadaan' => 'boolean',
            'is_rkbmd_pemeliharaan_rehab' => 'boolean',
            'is_rkbmd_pemeliharaan_rutin' => 'boolean',
        ];
    }
}
