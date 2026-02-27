<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ZdSyncState;
use App\Models\ZdTicket;
use App\Models\ZdUser;
use App\Services\ZendeskClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncZendeskTicketsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public ?int $startTime = null,
        public bool $isInitialBackfill = false
    ) {}

    public function handle(ZendeskClient $client): void
    {
        $cursor = null;
        $startTime = $this->startTime;

        if (! $this->isInitialBackfill) {
            $state = ZdSyncState::firstWhere('resource', 'tickets');
            if ($state?->cursor) {
                $cursor = $state->cursor;
            }
        }

        if (! $cursor && ! $startTime) {
            $years = config('zendesk.initial_backfill_years', 2);
            $startTime = $startTime ?? time() - ($years * 365 * 24 * 60 * 60);
        }

        $excludeStatuses = config('zendesk.exclude_statuses', ['closed']);
        $filterEmails = config('zendesk.filter_requester_emails', []);
        $requesterIdToEmail = $filterEmails ? ZdUser::pluck('email', 'zd_id')->toArray() : [];

        $totalProcessed = 0;

        do {
            $data = $client->getIncrementalTickets($cursor, $startTime);
            $tickets = $data['tickets'] ?? [];
            $startTime = null;

            foreach ($tickets as $ticket) {
                if (($ticket['status'] ?? '') === 'deleted') {
                    ZdTicket::withoutGlobalScope('not_deleted')
                        ->where('zd_id', $ticket['id'])
                        ->update(['zd_deleted_at' => now(), 'status' => 'deleted']);
                    $totalProcessed++;
                    continue;
                }

                $status = $ticket['status'] ?? '';
                $isExcludedStatus = in_array($status, $excludeStatuses);
                $exists = ZdTicket::where('zd_id', $ticket['id'])->exists();

                if ($isExcludedStatus && ! $exists) {
                    continue;
                }

                if (! empty($filterEmails)) {
                    $requesterId = $ticket['requester_id'] ?? null;
                    $requesterEmail = $requesterIdToEmail[$requesterId] ?? null;
                    if ($requesterEmail === null || ! in_array(strtolower($requesterEmail), array_map('strtolower', $filterEmails))) {
                        continue;
                    }
                }

                $model = $this->upsertTicket($ticket);
                $totalProcessed++;

                if ($model) {
                    FetchTicketCommentsJob::dispatch($model);
                }
            }

            $cursor = $data['after_cursor'] ?? null;
            $endOfStream = $data['end_of_stream'] ?? true;

            if ($cursor && ! $endOfStream) {
                $this->updateSyncState($cursor);
            }
        } while ($cursor && ! ($data['end_of_stream'] ?? true));

        if ($cursor) {
            $this->updateSyncState($cursor);
        }

        Log::info('SyncZendeskTicketsJob completed', ['processed' => $totalProcessed]);
    }

    private function upsertTicket(array $raw): ?ZdTicket
    {
        $solvedAt = null;
        $closedAt = null;
        if (($raw['status'] ?? '') === 'solved') {
            $solvedAt = $raw['updated_at'] ?? null;
        }
        if (($raw['status'] ?? '') === 'closed') {
            $closedAt = $raw['updated_at'] ?? null;
        }

        return ZdTicket::withoutGlobalScope('not_merged')->updateOrCreate(
            ['zd_id' => $raw['id']],
            [
                'subject' => $raw['subject'] ?? null,
                'description' => $raw['description'] ?? null,
                'status' => $raw['status'] ?? null,
                'priority' => $raw['priority'] ?? null,
                'type' => $raw['type'] ?? null,
                'requester_id' => $raw['requester_id'] ?? null,
                'submitter_id' => $raw['submitter_id'] ?? null,
                'assignee_id' => $raw['assignee_id'] ?? null,
                'group_id' => $raw['group_id'] ?? null,
                'org_id' => $raw['organization_id'] ?? null,
                'brand_id' => $raw['brand_id'] ?? null,
                'form_id' => $raw['ticket_form_id'] ?? null,
                'tags' => $raw['tags'] ?? [],
                'custom_fields' => $this->normalizeCustomFields($raw['custom_fields'] ?? []),
                'via' => $raw['via'] ?? null,
                'solved_at' => $solvedAt ? \Carbon\Carbon::parse($solvedAt) : null,
                'closed_at' => $closedAt ? \Carbon\Carbon::parse($closedAt) : null,
                'satisfaction_rating' => $raw['satisfaction_rating'] ?? null,
                'ai_needs_refresh' => true,
                'collaborator_ids' => $raw['collaborator_ids'] ?? [],
                'raw_json' => $raw,
                'zd_created_at' => isset($raw['created_at']) ? \Carbon\Carbon::parse($raw['created_at']) : null,
                'zd_updated_at' => isset($raw['updated_at']) ? \Carbon\Carbon::parse($raw['updated_at']) : null,
            ]
        );
    }

    private function normalizeCustomFields(array $fields): array
    {
        $out = [];
        foreach ($fields as $f) {
            $out[$f['id'] ?? $f['field_id'] ?? ''] = $f['value'] ?? null;
        }
        return $out;
    }

    private function updateSyncState(string $cursor): void
    {
        ZdSyncState::updateOrCreate(
            ['resource' => 'tickets'],
            [
                'cursor' => $cursor,
                'last_sync_at' => now(),
            ]
        );
    }
}
