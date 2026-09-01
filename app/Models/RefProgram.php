<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefProgram extends Model
{
    use HasFactory;

    protected $table = 'dev.ref_program';

    protected $primaryKey = 'kode_program';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'kode_program',
        'kode_bidang_urusan',
        'nama_program',
    ];

    /**
     * @return BelongsTo<RefBidangUrusan, $this>
     */
    public function bidangUrusan(): BelongsTo
    {
        return $this->belongsTo(RefBidangUrusan::class, 'kode_bidang_urusan', 'kode_bidang_urusan');
    }
}
