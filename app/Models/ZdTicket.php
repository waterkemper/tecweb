<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class ZdTicket extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope('not_deleted', fn (Builder $q) => $q->whereNull('zd_deleted_at'));
        static::addGlobalScope('not_merged', fn (Builder $q) => $q->where(function (Builder $q) {
            $q->whereNull('tags')
                ->orWhereRaw("NOT ((tags::jsonb) @> '\"closed_by_merged\"'::jsonb)");
        }));
    }

    protected $table = 'zd_tickets';

    protected $fillable = [
        'zd_id',
        'subject',
        'description',
        'status',
        'priority',
        'type',
        'requester_id',
        'submitter_id',
        'assignee_id',
        'group_id',
        'org_id',
        'brand_id',
        'form_id',
        'tags',
        'custom_fields',
        'via',
        'solved_at',
        'closed_at',
        'satisfaction_rating',
        'ai_needs_refresh',
        'raw_json',
        'zd_created_at',
        'zd_updated_at',
        'zd_deleted_at',
        'collaborator_ids',
    ];

    protected $casts = [
        'tags' => 'array',
        'custom_fields' => 'array',
        'via' => 'array',
        'satisfaction_rating' => 'array',
        'solved_at' => 'datetime',
        'closed_at' => 'datetime',
        'ai_needs_refresh' => 'boolean',
        'raw_json' => 'array',
        'zd_created_at' => 'datetime',
        'zd_updated_at' => 'datetime',
        'zd_deleted_at' => 'datetime',
        'collaborator_ids' => 'array',
    ];

    public function comments(): HasMany
    {
        return $this->hasMany(ZdTicketComment::class, 'ticket_id')->orderBy('created_at');
    }

    public function analysis(): HasMany
    {
        return $this->hasMany(AiTicketAnalysis::class, 'ticket_id');
    }

    public function analysisHistory(): HasMany
    {
        return $this->hasMany(AiTicketAnalysisHistory::class, 'ticket_id')->orderByDesc('snapshot_at');
    }

    public function latestAnalysis(): ?AiTicketAnalysis
    {
        return $this->analysis()->latest()->first();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(ZdUser::class, 'requester_id', 'zd_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(ZdUser::class, 'submitter_id', 'zd_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(ZdUser::class, 'assignee_id', 'zd_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(ZdOrg::class, 'org_id', 'zd_id');
    }

    /**
     * Collaborators (CC) on the ticket.
     */
    public function getCollaboratorsAttribute(): \Illuminate\Support\Collection
    {
        $ids = $this->collaborator_ids ?? [];
        if (empty($ids)) {
            return collect();
        }
        return ZdUser::whereIn('zd_id', $ids)->get();
    }

    /**
     * Age status for open/pending tickets based on days since last Zendesk update.
     * Returns: 'fresh' | 'recent' | 'old' | 'too_old'
     */
    public function getAgeStatusAttribute(): string
    {
        if (in_array($this->status ?? '', ['solved', 'closed'])) {
            return 'resolved';
        }

        $updatedAt = $this->zd_updated_at ?? $this->updated_at;
        if (! $updatedAt) {
            return 'unknown';
        }

        $days = $updatedAt->diffInDays(now(), false);
        $oldDays = config('zendesk.ticket_age_old_days', 3);
        $tooOldDays = config('zendesk.ticket_age_too_old_days', 7);

        if ($days >= $tooOldDays) {
            return 'too_old';
        }
        if ($days >= $oldDays) {
            return 'old';
        }
        if ($days >= 1) {
            return 'recent';
        }

        return 'fresh';
    }

    /**
     * Content string used for content_hash (subject + description + comments).
     * Same format as SummarizeTicketJob to ensure consistent hashing.
     */
    public function contentForHash(): string
    {
        $parts = [
            "Subject: {$this->subject}",
            "Description: {$this->description}",
        ];
        $requesterId = $this->requester_id;
        foreach ($this->comments()->orderBy('created_at')->get() as $c) {
            $author = $c->is_public
                ? ($c->author_id === $requesterId ? '[Customer]' : '[Agent]')
                : '[Agent - Internal]';
            $parts[] = "{$author} {$c->created_at}: {$c->body}";
        }

        return implode("\n\n", $parts);
    }

    /**
     * Days since last Zendesk update (for open tickets).
     */
    public function getDaysSinceUpdateAttribute(): ?int
    {
        $updatedAt = $this->zd_updated_at ?? $this->updated_at;
        $days = $updatedAt?->diffInDays(now());
        return $days !== null ? (int) $days : null;
    }

    public function embeddings(): HasMany
    {
        return $this->hasMany(AiTicketEmbedding::class, 'ticket_id');
    }

    public function ticketOrder(): HasOne
    {
        return $this->hasOne(TicketOrder::class, 'ticket_id', 'id');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (empty($term)) {
            return $query;
        }
        return $query->whereRaw(
            "to_tsvector('simple', coalesce(subject,'') || ' ' || coalesce(description,'')) @@ plainto_tsquery('simple', ?)",
            [$term]
        );
    }

    public function scopeFilterStatus($query, ?string $status)
    {
        return $status ? $query->where('status', $status) : $query;
    }

    public function scopeFilterPriority($query, ?string $priority)
    {
        return $priority ? $query->where('priority', $priority) : $query;
    }

    public function scopeFilterAssignee($query, ?int $assigneeId)
    {
        return $assigneeId ? $query->where('assignee_id', $assigneeId) : $query;
    }

    public function scopeFilterOrg($query, $orgId)
    {
        return $orgId ? $query->where('org_id', (int) $orgId) : $query;
    }

    public function scopeFilterRequester($query, $requesterId)
    {
        return $requesterId ? $query->where('zd_tickets.requester_id', (int) $requesterId) : $query;
    }

    public function scopeFilterGroup($query, ?int $groupId)
    {
        return $groupId ? $query->where('group_id', $groupId) : $query;
    }

    public function scopeFilterDateRange($query, ?string $from, ?string $to, string $column = 'zd_created_at')
    {
        $dateColumn = $column === 'zd_created_at'
            ? DB::raw('COALESCE(zd_created_at, created_at)')
            : $column;
        $fromDate = self::parseDateInput($from);
        $toDate = self::parseDateInput($to);
        if ($fromDate) {
            $query->whereDate($dateColumn, '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate($dateColumn, '<=', $toDate);
        }
        return $query;
    }

    /** Parse dd/mm/yy, dd/mm/yyyy or Y-m-d to Y-m-d. */
    public static function parseDateInput(?string $value): ?string
    {
        if (! $value || ! is_string($value)) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{2,4}$/', $value)) {
            $d = \Carbon\Carbon::createFromFormat('d/m/y', $value);
            if ($d !== false) {
                return $d->format('Y-m-d');
            }
            $d = \Carbon\Carbon::createFromFormat('d/m/Y', $value);
            return $d !== false ? $d->format('Y-m-d') : null;
        }
        return null;
    }

    public function scopeFilterTag($query, ?string $tag)
    {
        return $tag ? $query->whereJsonContains('tags', $tag) : $query;
    }

    public function scopeFilterCategory($query, ?string $category)
    {
        if (! $category) {
            return $query;
        }
        return $query->whereHas('analysis', fn ($q) => $q->where('category', $category)
            ->orWhereJsonContains('categories', $category));
    }

    public function scopeFilterSeverity($query, ?string $severity)
    {
        return $severity ? $query->whereHas('analysis', fn ($q) => $q->where('severity', $severity)) : $query;
    }

    /**
     * Scope tickets visible to the given user.
     * Admin and colaborador see all tickets; gerente (can_view_org_tickets) sees org tickets; cliente sees only requester/submitter/collaborator.
     */
    public function scopeVisibleToUser(Builder $query, User $user): Builder
    {
        if (in_array($user->role, ['admin', 'colaborador'])) {
            return $query;
        }

        $orgId = $user->zdUser?->org_id;
        if ($user->can_view_org_tickets && $orgId !== null) {
            return $query->where('zd_tickets.org_id', $orgId);
        }

        $zdId = $user->zd_id;
        if ($zdId === null) {
            return $query->whereRaw('1=0');
        }

        return $query->where(function (Builder $q) use ($zdId) {
            $q->where('zd_tickets.requester_id', $zdId)
                ->orWhere('zd_tickets.submitter_id', $zdId)
                ->orWhereRaw('(zd_tickets.collaborator_ids::jsonb) @> ?::jsonb', [json_encode([$zdId])]);
        });
    }

    /**
     * Order by priority using configured priority order (urgent first by default).
     */
    public function scopeOrderByPriority($query, string $direction = 'asc')
    {
        $order = config('zendesk.priority_order') ?: ['urgent', 'high', 'normal', 'low'];
        $bindings = [];
        $cases = [];
        foreach ($order as $i => $p) {
            $cases[] = 'WHEN priority = ? THEN ' . $i;
            $bindings[] = $p;
        }
        $caseSql = 'CASE ' . implode(' ', $cases) . ' ELSE ' . count($order) . ' END';
        return $query->orderByRaw($caseSql . ' ' . ($direction === 'desc' ? 'DESC' : 'ASC'), $bindings);
    }

    /**
     * Order by requester, then by user-defined sequence within each requester.
     */
    public function scopeOrderBySequence($query, string $direction = 'asc')
    {
        $seqDir = $direction === 'desc' ? 'DESC' : 'ASC';
        return $query->leftJoin('ticket_order', 'zd_tickets.id', '=', 'ticket_order.ticket_id')
            ->orderByRaw('COALESCE(zd_tickets.requester_id, 0) ' . $seqDir)
            ->orderByRaw('COALESCE(ticket_order.sequence, 999999) ' . $seqDir)
            ->orderByRaw('COALESCE(zd_tickets.zd_updated_at, zd_tickets.updated_at) DESC')
            ->select('zd_tickets.*');
    }
}
