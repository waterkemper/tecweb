<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ZdTicket;
use Illuminate\Console\Command;

class MarkTicketDeletedCommand extends Command
{
    protected $signature = 'zendesk:mark-deleted {zd_id : Zendesk ticket ID (e.g. 35839)}';

    protected $description = 'Mark a ticket as deleted (when deleted in Zendesk but sync did not pick it up)';

    public function handle(): int
    {
        $zdId = (int) $this->argument('zd_id');
        if ($zdId <= 0) {
            $this->error('Invalid zd_id. Use the Zendesk ticket ID (e.g. 35839).');
            return self::FAILURE;
        }

        $updated = ZdTicket::withoutGlobalScopes(['not_deleted', 'not_merged'])
            ->where('zd_id', $zdId)
            ->update(['zd_deleted_at' => now(), 'status' => 'deleted']);

        if ($updated > 0) {
            $this->info("Ticket #{$zdId} marked as deleted.");
            return self::SUCCESS;
        }

        $exists = ZdTicket::withoutGlobalScopes(['not_deleted', 'not_merged'])->where('zd_id', $zdId)->exists();
        if (! $exists) {
            $this->warn("Ticket #{$zdId} not found in database.");
            return self::FAILURE;
        }

        $this->info("Ticket #{$zdId} was already marked as deleted.");
        return self::SUCCESS;
    }
}
