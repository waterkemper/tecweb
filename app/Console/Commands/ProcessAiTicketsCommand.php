<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SummarizeTicketJob;
use App\Models\ZdTicket;
use Illuminate\Console\Command;

class ProcessAiTicketsCommand extends Command
{
    protected $signature = 'zendesk:process-ai
        {--limit=10 : Max tickets to process}
        {--no-limit : Process all pending tickets (no limit)}
        {--all : Process all tickets (ignores ai_needs_refresh)}';

    protected $description = 'Run AI pipeline (summary, classification, effort, embeddings) for tickets that need refresh';

    public function handle(): int
    {
        $limit = $this->option('no-limit') ? null : (int) $this->option('limit');
        $query = $this->option('all')
            ? ZdTicket::query()
            : ZdTicket::where('ai_needs_refresh', true);
        $query = $limit !== null ? $query->limit($limit) : $query;
        $tickets = $query->get();

        foreach ($tickets as $ticket) {
            SummarizeTicketJob::dispatch($ticket);
            $this->info("Dispatched AI for ticket #{$ticket->zd_id}");
        }

        $this->info("Dispatched {$tickets->count()} tickets.");
        return self::SUCCESS;
    }
}
