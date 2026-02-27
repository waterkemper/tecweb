<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZdTicketComment extends Model
{
    protected $table = 'zd_ticket_comments';

    public $timestamps = true;

    const UPDATED_AT = null;

    protected $fillable = [
        'ticket_id',
        'zd_comment_id',
        'author_id',
        'created_at',
        'body',
        'html_body',
        'is_public',
        'attachments_json',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'is_public' => 'boolean',
        'attachments_json' => 'array',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ZdTicket::class, 'ticket_id');
    }
}
