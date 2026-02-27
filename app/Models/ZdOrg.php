<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZdOrg extends Model
{
    protected $table = 'zd_orgs';

    protected $fillable = [
        'zd_id',
        'name',
        'domain_names',
        'tags',
        'custom_fields',
        'raw_json',
    ];

    protected $casts = [
        'domain_names' => 'array',
        'tags' => 'array',
        'custom_fields' => 'array',
        'raw_json' => 'array',
    ];
}
