<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZdUser extends Model
{
    protected $table = 'zd_users';

    protected $fillable = [
        'zd_id',
        'name',
        'email',
        'role',
        'external_id',
        'locale',
        'timezone',
        'raw_json',
    ];

    protected $casts = [
        'raw_json' => 'array',
    ];
}
