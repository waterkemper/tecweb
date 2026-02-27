<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSimilarTicket extends Model
{
    protected $table = 'ai_similar_tickets';

    protected $fillable = [
        'ticket_id',
        'similar_ticket_id',
        'score',
        'rationale',
        'is_duplicate_candidate',
    ];

    protected $casts = [
        'score' => 'float',
        'is_duplicate_candidate' => 'boolean',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ZdTicket::class, 'ticket_id');
    }

    public function similarTicket(): BelongsTo
    {
        return $this->belongsTo(ZdTicket::class, 'similar_ticket_id');
    }
}
