<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefUrusan extends Model
{
    use HasFactory;

    protected $table = 'dev.ref_urusan';

    protected $primaryKey = 'kode_urusan';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'kode_urusan',
        'nama_urusan',
    ];
}
