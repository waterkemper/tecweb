<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ZdTicket;
use Carbon\Carbon;

class TicketTagService
{
    public function applySuggestedTags(ZdTicket $ticket, ZendeskClient $client): bool
    {
        $analysis = $ticket->analysis()->latest()->first();
        $suggested = $analysis?->suggested_tags ?? [];
        if (empty($suggested)) {
            return false;
        }

        $current = $ticket->tags ?? [];
        $merged = array_values(array_unique(array_merge($current, $suggested)));
        $client->updateTicket((int) $ticket->zd_id, ['tags' => $merged]);
        $ticket->update(['tags' => $merged, 'zd_updated_at' => now()]);

        return true;
    }

    public function parseTags(string $input): array
    {
        return array_values(array_unique(array_filter(array_map(function (string $tag) {
            $normalized = trim($tag);
            return $normalized !== '' ? preg_replace('/[^\p{L}\p{N}\-_:\/]/u', '', $normalized) : '';
        }, preg_split('/[\s,]+/', $input)))));
    }

    public function updateTags(ZdTicket $ticket, array $tags, ZendeskClient $client): void
    {
        $client->updateTicket((int) $ticket->zd_id, ['tags' => $tags]);
        $ticket->update(['tags' => $tags, 'zd_updated_at' => now()]);
    }

    public function syncTags(ZdTicket $ticket, ZendeskClient $client): bool
    {
        $raw = $client->getTicket((int) $ticket->zd_id);
        if ($raw === null) {
            return false;
        }

        $ticket->update([
            'tags' => $raw['tags'] ?? [],
            'zd_updated_at' => isset($raw['updated_at']) ? Carbon::parse($raw['updated_at']) : $ticket->zd_updated_at,
        ]);

        return true;
    }
}
