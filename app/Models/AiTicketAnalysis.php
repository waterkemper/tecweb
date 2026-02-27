<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiTicketAnalysis extends Model
{
    protected $table = 'ai_ticket_analysis';

    protected $fillable = [
        'ticket_id',
        'model_version',
        'summary',
        'bullets',
        'what_reported',
        'what_tried',
        'current_status',
        'open_questions',
        'open_questions_list',
        'next_action',
        'actions_needed_list',
        'pending_action',
        'category',
        'categories',
        'modules',
        'severity',
        'urgency',
        'requires_dev',
        'effort_min',
        'effort_max',
        'internal_effort_min',
        'internal_effort_max',
        'confidence',
        'effort_reason',
        'extracted_json',
        'next_actions',
        'suggested_tags',
        'suggested_owner',
        'last_ai_refresh_at',
    ];

    protected $casts = [
        'extracted_json' => 'array',
        'next_actions' => 'array',
        'bullets' => 'array',
        'categories' => 'array',
        'modules' => 'array',
        'suggested_tags' => 'array',
        'open_questions_list' => 'array',
        'actions_needed_list' => 'array',
        'effort_min' => 'float',
        'effort_max' => 'float',
        'internal_effort_min' => 'float',
        'internal_effort_max' => 'float',
        'confidence' => 'float',
        'requires_dev' => 'boolean',
        'last_ai_refresh_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ZdTicket::class, 'ticket_id');
    }
}
