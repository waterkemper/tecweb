<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiTicketAnalysisHistory extends Model
{
    protected $table = 'ai_ticket_analysis_history';

    public $timestamps = false;

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
        'confidence',
        'effort_reason',
        'snapshot_at',
    ];

    protected $casts = [
        'bullets' => 'array',
        'categories' => 'array',
        'modules' => 'array',
        'open_questions_list' => 'array',
        'actions_needed_list' => 'array',
        'effort_min' => 'float',
        'effort_max' => 'float',
        'confidence' => 'float',
        'requires_dev' => 'boolean',
        'snapshot_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ZdTicket::class, 'ticket_id');
    }
}
