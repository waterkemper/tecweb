<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZdSyncState extends Model
{
    protected $table = 'zd_sync_state';

    protected $fillable = [
        'resource',
        'cursor',
        'last_timestamp',
        'last_sync_at',
    ];

    protected $casts = [
        'last_sync_at' => 'datetime',
    ];
}
