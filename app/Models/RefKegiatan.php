<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefKegiatan extends Model
{
    use HasFactory;

    protected $table = 'dev.ref_kegiatan';

    protected $primaryKey = 'kode_kegiatan';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'kode_kegiatan',
        'kode_program',
        'nama_kegiatan',
    ];

    /**
     * @return BelongsTo<RefProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(RefProgram::class, 'kode_program', 'kode_program');
    }
}
