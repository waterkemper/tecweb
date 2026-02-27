<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ZdOrg;
use App\Models\ZdSyncState;
use App\Services\ZendeskClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncZendeskOrgsJob implements ShouldQueue
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
                Log::warning('SyncZendeskOrgsJob: incremental export failed ({status}), falling back to list API', [
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
        $startTime = $this->startTime ?? ZdSyncState::firstWhere('resource', 'orgs')?->last_timestamp
            ?? (time() - 365 * 24 * 60 * 60);

        $totalProcessed = 0;

        do {
            $data = $client->getIncrementalOrganizations($startTime);
            $totalProcessed += $this->processOrgs($data['organizations'] ?? []);

            $startTime = $data['end_time'] ?? null;
            $endOfStream = $data['end_of_stream'] ?? true;

            if ($startTime) {
                ZdSyncState::updateOrCreate(
                    ['resource' => 'orgs'],
                    ['last_timestamp' => $startTime, 'last_sync_at' => now()]
                );
            }
        } while ($startTime && ! $endOfStream);

        Log::info('SyncZendeskOrgsJob completed (incremental)', ['processed' => $totalProcessed]);
    }

    private function syncFromList(ZendeskClient $client): void
    {
        $totalProcessed = 0;
        $nextUrl = null;

        do {
            $data = $client->getOrganizationsListPage($nextUrl);
            $totalProcessed += $this->processOrgs($data['organizations'] ?? []);
            $nextUrl = $data['next_page'] ?? null;
            $endOfStream = $data['end_of_stream'] ?? true;
        } while ($nextUrl && ! $endOfStream);

        ZdSyncState::updateOrCreate(
            ['resource' => 'orgs'],
            ['last_timestamp' => time(), 'last_sync_at' => now()]
        );

        Log::info('SyncZendeskOrgsJob completed (list API)', ['processed' => $totalProcessed]);
    }

    private function processOrgs(array $orgs): int
    {
        foreach ($orgs as $org) {
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

        return count($orgs);
    }
}
