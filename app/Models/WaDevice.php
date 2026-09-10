<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaDevice extends Model
{
    use HasFactory;

    protected $table = 'dev.wa_devices';

    public $timestamps = false;

    protected $fillable = [
        'device_id',
        'nama',
        'nomor',
        'status',
        'is_active',
        'priority',
        'last_heartbeat',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'priority' => 'integer',
            'last_heartbeat' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}