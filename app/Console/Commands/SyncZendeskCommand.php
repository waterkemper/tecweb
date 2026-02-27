<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\FetchTicketCommentsJob;
use App\Jobs\SyncZendeskOrgsJob;
use App\Jobs\SyncZendeskTicketsJob;
use App\Jobs\SyncZendeskUsersJob;
use App\Models\ZdTicket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class SyncZendeskCommand extends Command
{
    protected $signature = 'zendesk:sync
        {--full : Full initial backfill (tickets, users, orgs)}
        {--tickets-only : Sync tickets only}
        {--users-only : Sync users only}
        {--orgs-only : Sync orgs only}
        {--comments : Fetch comments for tickets needing refresh}';

    protected $description = 'Sync Zendesk data (tickets, comments, users, orgs)';

    public function handle(): int
    {
        if ($this->option('full')) {
            $years = config('zendesk.initial_backfill_years', 2);
            $startTime = time() - ($years * 365 * 24 * 60 * 60);
            $filterEmails = config('zendesk.filter_requester_emails', []);
            if (! empty($filterEmails)) {
                Bus::chain([
                    new SyncZendeskUsersJob($startTime),
                    new SyncZendeskOrgsJob($startTime),
                    new SyncZendeskTicketsJob($startTime, true),
                ])->dispatch();
                $this->info('Dispatched full sync (users first, then orgs, then tickets).');
            } else {
                SyncZendeskTicketsJob::dispatch($startTime, true);
                SyncZendeskUsersJob::dispatch($startTime);
                SyncZendeskOrgsJob::dispatch($startTime);
                $this->info('Dispatched full sync jobs.');
            }
            return self::SUCCESS;
        }

        if ($this->option('tickets-only')) {
            SyncZendeskTicketsJob::dispatch();
            $this->info('Dispatched ticket sync job.');
            return self::SUCCESS;
        }

        if ($this->option('users-only')) {
            SyncZendeskUsersJob::dispatch();
            $this->info('Dispatched users sync job.');
            return self::SUCCESS;
        }

        if ($this->option('orgs-only')) {
            SyncZendeskOrgsJob::dispatch();
            $this->info('Dispatched orgs sync job.');
            return self::SUCCESS;
        }

        if ($this->option('comments')) {
            $tickets = ZdTicket::where('ai_needs_refresh', true)->limit(100)->get();
            foreach ($tickets as $ticket) {
                FetchTicketCommentsJob::dispatch($ticket);
            }
            $this->info("Dispatched comment fetch for {$tickets->count()} tickets.");
            return self::SUCCESS;
        }

        SyncZendeskTicketsJob::dispatch();
        SyncZendeskUsersJob::dispatch();
        SyncZendeskOrgsJob::dispatch();
        $this->info('Dispatched incremental sync jobs.');
        return self::SUCCESS;
    }
}
