<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'org_id',
        'raw_json',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(ZdOrg::class, 'org_id', 'zd_id');
    }

    protected $casts = [
        'raw_json' => 'array',
    ];
}
