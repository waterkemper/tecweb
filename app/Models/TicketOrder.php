<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketOrder extends Model
{
    protected $table = 'ticket_order';

    protected $fillable = ['ticket_id', 'sequence'];

    protected $casts = [
        'sequence' => 'integer',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ZdTicket::class, 'ticket_id');
    }
}
