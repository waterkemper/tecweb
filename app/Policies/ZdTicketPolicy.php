<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\ZdTicket;

class ZdTicketPolicy
{
    public function view(User $user, ZdTicket $ticket): bool
    {
        if (in_array($user->role, ['admin', 'colaborador'])) {
            return true;
        }

        if ($user->can_view_org_tickets && $user->zdUser?->org_id !== null && $ticket->org_id === $user->zdUser->org_id) {
            return true;
        }

        $zdId = $user->zd_id;
        if ($zdId === null) {
            return false;
        }

        if ($ticket->requester_id === $zdId || $ticket->submitter_id === $zdId) {
            return true;
        }

        $collaboratorIds = $ticket->collaborator_ids ?? [];
        return in_array($zdId, $collaboratorIds, true);
    }
}
