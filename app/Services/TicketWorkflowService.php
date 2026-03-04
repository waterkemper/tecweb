<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TicketOrder;
use App\Models\User;
use App\Models\ZdTicket;

class TicketWorkflowService
{
    public function reorderTickets(User $user, array $ids): void
    {
        $ticketIds = array_map('intval', array_values($ids));
        $tickets = ZdTicket::visibleToUser($user)
            ->whereIn('id', $ticketIds)
            ->get(['id', 'requester_id'])
            ->keyBy('id');

        $seqByRequester = [];
        foreach ($ticketIds as $ticketId) {
            $ticket = $tickets->get($ticketId);
            if (! $ticket) {
                continue;
            }

            $requesterId = $ticket->requester_id ?? 0;
            if (! isset($seqByRequester[$requesterId])) {
                $seqByRequester[$requesterId] = 0;
            }

            TicketOrder::updateOrCreate(
                ['ticket_id' => $ticketId],
                [
                    'requester_id' => $ticket->requester_id,
                    'sequence' => $seqByRequester[$requesterId]++,
                ]
            );
        }
    }

    public function updateTicketStatus(ZdTicket $ticket, string $status, ZendeskClient $client): void
    {
        $client->updateTicket((int) $ticket->zd_id, ['status' => $status]);

        $ticket->update([
            'status' => $status,
            'zd_updated_at' => now(),
        ]);
    }

    public function updatePendingAction(ZdTicket $ticket, ?string $pendingAction): bool
    {
        $analysis = $ticket->analysis()->latest()->first();
        if (! $analysis) {
            return false;
        }

        $analysis->update([
            'pending_action' => $pendingAction ?: null,
        ]);

        return true;
    }
}
