<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\TicketOrder;
use App\Models\ZdOrg;
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
        $filterEmails = config('zendesk.filter_requester_emails', []);
        $filterZdIds = config('zendesk.filter_requester_zd_ids', []);
        $hasFilter = ! empty($filterEmails) || ! empty($filterZdIds);

        if ($hasFilter) {
            $this->syncFromSearch($client);
            return;
        }

        $this->syncIncremental($client);
    }

    /**
     * Sync tickets via Search API by requester email/ID.
     * Cria usuários e organizações envolvidos nos tickets sob demanda.
     * Emails são resolvidos para zd_id primeiro (requester:ID é mais confiável na Search API).
     */
    private function syncFromSearch(ZendeskClient $client): void
    {
        $filterEmails = config('zendesk.filter_requester_emails', []);
        $filterZdIds = config('zendesk.filter_requester_zd_ids', []);
        $excludeStatuses = config('zendesk.exclude_statuses', ['closed']);
        $totalProcessed = 0;
        $seenIds = [];

        $requesterIds = array_filter(array_map('intval', $filterZdIds));

        foreach ($filterEmails as $email) {
            $email = trim($email);
            if ($email === '') {
                continue;
            }
            $found = \App\Models\ZdUser::whereRaw('LOWER(TRIM(email)) = ?', [strtolower($email)])->first();
            if ($found) {
                $requesterIds[] = (int) $found->zd_id;
                continue;
            }
            try {
                $users = $client->searchUsersByEmail($email);
                foreach ($users as $u) {
                    $e = $u['email'] ?? null;
                    if ($e !== null && strtolower(trim($e)) === strtolower($email)) {
                        $requesterIds[] = (int) ($u['id'] ?? 0);
                        break;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('SyncZendeskTicketsJob: falha ao buscar usuário por email', ['email' => $email, 'error' => $e->getMessage()]);
            }
        }

        $requesterIds = array_unique(array_filter($requesterIds));

        if (empty($requesterIds)) {
            Log::warning('SyncZendeskTicketsJob: nenhum requester encontrado. Use ZENDESK_FILTER_REQUESTER_ZD_IDS com o ID do Zendesk (Admin > Pessoas > usuário > ID na URL).', [
                'emails' => $filterEmails,
                'zd_ids' => $filterZdIds,
            ]);
            return;
        }

        Log::info('SyncZendeskTicketsJob: buscando tickets para requesters', ['zd_ids' => array_values($requesterIds)]);

        foreach ($requesterIds as $zdId) {
            foreach ($client->searchTicketsByRequester($zdId) as $ticket) {
                $ticketId = $ticket['id'] ?? null;
                if ($ticketId === null || isset($seenIds[$ticketId])) {
                    continue;
                }
                $seenIds[$ticketId] = true;

                if (($ticket['status'] ?? '') === 'deleted') {
                    ZdTicket::withoutGlobalScope('not_deleted')
                        ->where('zd_id', $ticketId)
                        ->update(['zd_deleted_at' => now(), 'status' => 'deleted']);
                    $totalProcessed++;
                    continue;
                }

                $status = $ticket['status'] ?? '';
                $isExcludedStatus = in_array($status, $excludeStatuses);
                $exists = ZdTicket::where('zd_id', $ticketId)->exists();
                if ($isExcludedStatus && ! $exists) {
                    continue;
                }

                $this->ensureReferencedUsersAndOrgExist($client, $ticket);

                [$model, $needsProcessing] = $this->upsertTicket($ticket);
                $totalProcessed++;

                if ($model->wasRecentlyCreated) {
                    $this->assignNextSequenceForRequester($model);
                }
                if ($needsProcessing) {
                    FetchTicketCommentsJob::dispatch($model);
                }
            }
        }

        Log::info('SyncZendeskTicketsJob completed (search by requester)', ['processed' => $totalProcessed]);
    }

    private function syncIncremental(ZendeskClient $client): void
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

                $this->ensureReferencedUsersAndOrgExist($client, $ticket);

                [$model, $needsProcessing] = $this->upsertTicket($ticket);
                $totalProcessed++;

                if ($model->wasRecentlyCreated) {
                    $this->assignNextSequenceForRequester($model);
                }
                if ($needsProcessing) {
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

        Log::info('SyncZendeskTicketsJob completed (incremental)', ['processed' => $totalProcessed]);
    }

    /**
     * @return array{ZdTicket, bool} [model, needsProcessing] — needsProcessing = true only when ticket is new or content changed
     */
    private function upsertTicket(array $raw): array
    {
        $solvedAt = null;
        $closedAt = null;
        if (($raw['status'] ?? '') === 'solved') {
            $solvedAt = $raw['updated_at'] ?? null;
        }
        if (($raw['status'] ?? '') === 'closed') {
            $closedAt = $raw['updated_at'] ?? null;
        }

        $newUpdatedAt = isset($raw['updated_at']) ? \Carbon\Carbon::parse($raw['updated_at']) : null;
        $existing = ZdTicket::withoutGlobalScope('not_merged')->where('zd_id', $raw['id'])->first();
        $contentChanged = $existing === null
            || $existing->zd_updated_at?->format('Y-m-d H:i:s') !== $newUpdatedAt?->format('Y-m-d H:i:s');

        $attrs = [
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
            'collaborator_ids' => $raw['collaborator_ids'] ?? [],
            'raw_json' => $raw,
            'zd_created_at' => isset($raw['created_at']) ? \Carbon\Carbon::parse($raw['created_at']) : null,
            'zd_updated_at' => $newUpdatedAt,
        ];
        if ($contentChanged) {
            $attrs['ai_needs_refresh'] = true;
        }

        $model = ZdTicket::withoutGlobalScope('not_merged')->updateOrCreate(
            ['zd_id' => $raw['id']],
            $attrs
        );

        return [$model, $contentChanged];
    }

    private function assignNextSequenceForRequester(ZdTicket $ticket): void
    {
        $requesterId = $ticket->requester_id;
        $query = TicketOrder::query();
        if ($requesterId === null) {
            $query->whereNull('requester_id');
        } else {
            $query->where('requester_id', $requesterId);
        }
        $maxSeq = $query->max('sequence');
        $nextSeq = $maxSeq === null ? 0 : (int) $maxSeq + 1;
        TicketOrder::create([
            'ticket_id' => $ticket->id,
            'requester_id' => $requesterId,
            'sequence' => $nextSeq,
        ]);
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

    /**
     * Ensure requester, submitter, assignee, collaborators (BCC) and organization exist in zd_users/zd_orgs.
     * Fetches from Zendesk API and creates if missing.
     */
    private function ensureReferencedUsersAndOrgExist(ZendeskClient $client, array $ticket): void
    {
        $userIds = array_filter(array_unique(array_merge(
            [$ticket['requester_id'] ?? null],
            [$ticket['submitter_id'] ?? null],
            [$ticket['assignee_id'] ?? null],
            $ticket['collaborator_ids'] ?? []
        )));
        $orgId = $ticket['organization_id'] ?? null;

        foreach ($userIds as $zdId) {
            $zdId = (int) $zdId;
            if ($zdId <= 0) {
                continue;
            }
            if (ZdUser::where('zd_id', $zdId)->exists()) {
                continue;
            }
            $user = $client->getUserById($zdId);
            if ($user !== null) {
                ZdUser::updateOrCreate(
                    ['zd_id' => $user['id']],
                    [
                        'name' => $user['name'] ?? null,
                        'email' => $user['email'] ?? null,
                        'role' => $user['role'] ?? null,
                        'external_id' => $user['external_id'] ?? null,
                        'locale' => $user['locale'] ?? null,
                        'timezone' => $user['timezone'] ?? null,
                        'org_id' => $user['organization_id'] ?? null,
                        'raw_json' => $user,
                    ]
                );
            }
        }

        if ($orgId !== null && $orgId > 0) {
            $orgId = (int) $orgId;
            if (! ZdOrg::where('zd_id', $orgId)->exists()) {
                $org = $client->getOrganizationById($orgId);
                if ($org !== null) {
                    ZdOrg::updateOrCreate(
                        ['zd_id' => $org['id']],
                        [
                            'name' => $org['name'] ?? null,
                            'domain_names' => $org['domain_names'] ?? [],
                            'tags' => $org['tags'] ?? [],
                            'custom_fields' => $org['organization_fields'] ?? $org['custom_fields'] ?? [],
                            'raw_json' => $org,
                        ]
                    );
                }
            }
        }
    }

}
