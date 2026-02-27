<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tickets = DB::table('zd_tickets')->whereNotNull('raw_json')->get();

        foreach ($tickets as $ticket) {
            $raw = json_decode($ticket->raw_json, true);
            if (! is_array($raw)) {
                continue;
            }
            $collaboratorIds = $raw['collaborator_ids'] ?? [];
            if (empty($collaboratorIds)) {
                continue;
            }
            DB::table('zd_tickets')
                ->where('id', $ticket->id)
                ->update(['collaborator_ids' => json_encode($collaboratorIds)]);
        }
    }

    public function down(): void
    {
        // No-op: we don't clear collaborator_ids on rollback
    }
};
