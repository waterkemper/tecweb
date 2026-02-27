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

        do {
            $data = $client->getUsersListPage($nextUrl);
            $totalProcessed += $this->processUsers($data['users'] ?? []);
            $nextUrl = $data['next_page'] ?? null;
            $endOfStream = $data['end_of_stream'] ?? true;
        } while ($nextUrl && ! $endOfStream);

        ZdSyncState::updateOrCreate(
            ['resource' => 'users'],
            ['last_timestamp' => time(), 'last_sync_at' => now()]
        );

        Log::info('SyncZendeskUsersJob completed (list API)', ['processed' => $totalProcessed]);
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
                    'raw_json' => $user,
                ]
            );
        }

        return count($users);
    }
}
