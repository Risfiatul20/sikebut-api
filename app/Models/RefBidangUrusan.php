<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefBidangUrusan extends Model
{
    use HasFactory;

    protected $table = 'dev.ref_bidang_urusan';

    protected $primaryKey = 'kode_bidang_urusan';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'kode_bidang_urusan',
        'kode_urusan',
        'nama_bidang_urusan',
    ];

    /**
     * @return BelongsTo<RefUrusan, $this>
     */
    public function urusan(): BelongsTo
    {
        return $this->belongsTo(RefUrusan::class, 'kode_urusan', 'kode_urusan');
    }
}
