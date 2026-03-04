<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ZdSyncState;
use App\Models\ZdUser;
use App\Services\ZendeskClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncZendeskUsersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public ?int $startTime = null
    ) {}

    public function handle(ZendeskClient $client): void
    {
        $filterEmails = config('zendesk.filter_requester_emails', []);
        $filterZdIds = config('zendesk.filter_requester_zd_ids', []);

        if (! empty($filterEmails) || ! empty($filterZdIds)) {
            Log::info('SyncZendeskUsersJob: filtro ativo - ZD IDs primeiro, depois List API');
            $this->ensureFilteredUsersExist($client, $filterEmails);
            $this->syncFromList($client);
            return;
        }

        try {
            $this->syncIncremental($client);
        } catch (RequestException $e) {
            $status = $e->response?->status();
            if (in_array($status, [400, 403], true)) {
                Log::warning('SyncZendeskUsersJob: incremental export failed ({status}), falling back to list API', [
                    'status' => $status,
                ]);
                $this->syncFromList($client);
            } else {
                throw $e;
            }
        }
    }

    private function syncIncremental(ZendeskClient $client): void
    {
        $startTime = $this->startTime ?? ZdSyncState::firstWhere('resource', 'users')?->last_timestamp
            ?? (time() - 365 * 24 * 60 * 60);

        $totalProcessed = 0;

        do {
            $data = $client->getIncrementalUsers($startTime);
            $totalProcessed += $this->processUsers($data['users'] ?? []);

            $startTime = $data['end_time'] ?? null;
            $endOfStream = $data['end_of_stream'] ?? true;

            if ($startTime) {
                ZdSyncState::updateOrCreate(
                    ['resource' => 'users'],
                    ['last_timestamp' => $startTime, 'last_sync_at' => now()]
                );
            }
        } while ($startTime && ! $endOfStream);

        Log::info('SyncZendeskUsersJob completed (incremental)', ['processed' => $totalProcessed]);
    }

    private function syncFromList(ZendeskClient $client): void
    {
        $totalProcessed = 0;
        $nextUrl = null;
        $pageNum = 0;
        $maxPages = 200;

        do {
            $pageNum++;
            $data = $client->getUsersListPage($nextUrl);
            $users = $data['users'] ?? [];
            $totalProcessed += $this->processUsers($users);
            $nextUrl = $data['next_page'] ?? null;
            $endOfStream = $data['end_of_stream'] ?? true;

            if ($pageNum % 5 === 0 || $endOfStream) {
                Log::info('SyncZendeskUsersJob: página', ['page' => $pageNum, 'total' => $totalProcessed]);
            }
        } while ($nextUrl && ! $endOfStream && $pageNum < $maxPages);

        ZdSyncState::updateOrCreate(
            ['resource' => 'users'],
            ['last_timestamp' => time(), 'last_sync_at' => now()]
        );

        Log::info('SyncZendeskUsersJob completed (list API)', ['processed' => $totalProcessed, 'pages' => $pageNum]);
    }

    private function processUsers(array $users): int
    {
        foreach ($users as $user) {
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

        return count($users);
    }

    /**
     * Search for filtered users by email and ensure they exist in zd_users.
     * Also supports ZENDESK_FILTER_REQUESTER_ZD_IDS for direct ID fetch (mais confiável).
     */
    private function ensureFilteredUsersExist(ZendeskClient $client, array $filterEmails): void
    {
        $filterZdIds = config('zendesk.filter_requester_zd_ids', []);
        foreach ($filterZdIds as $zdId) {
            if ($zdId <= 0) {
                continue;
            }
            $exists = ZdUser::where('zd_id', $zdId)->exists();
            if ($exists) {
                continue;
            }
            try {
                $user = $client->getUserById($zdId);
                if ($user !== null) {
                    $this->processUsers([$user]);
                    Log::info('SyncZendeskUsersJob: usuário adicionado via ZENDESK_FILTER_REQUESTER_ZD_IDS', ['zd_id' => $zdId]);
                }
            } catch (\Throwable $e) {
                Log::warning('SyncZendeskUsersJob: falha ao buscar usuário por ID', ['zd_id' => $zdId, 'error' => $e->getMessage()]);
            }
        }

        foreach ($filterEmails as $email) {
            $email = trim($email);
            if ($email === '') {
                continue;
            }
            $found = ZdUser::whereRaw('LOWER(TRIM(email)) = ?', [strtolower($email)])->exists();
            if ($found) {
                continue;
            }
            Log::warning('SyncZendeskUsersJob: usuário não encontrado. Use ZENDESK_FILTER_REQUESTER_ZD_IDS com o ID do Zendesk (Admin > Pessoas > usuário > ID na URL).', ['email' => $email]);
        }
    }
}
