<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RefStandarHarga extends Model
{
    use HasFactory;

    protected $table = 'dev.ref_standar_harga';

    protected $primaryKey = 'kode_standar_harga';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'kode_standar_harga',
        'nama_standar_harga',
    ];

    /**
     * @return HasMany<SipdPenetapanApbd, $this>
     */
    public function sipdPenetapanApbd(): HasMany
    {
        return $this->hasMany(SipdPenetapanApbd::class, 'kode_standar_harga', 'kode_standar_harga');
    }
}
