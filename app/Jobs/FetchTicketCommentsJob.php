<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ZdTicket;
use App\Models\ZdTicketComment;
use App\Services\ZendeskClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchTicketCommentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public ZdTicket $ticket
    ) {}

    public function handle(ZendeskClient $client): void
    {
        $zdId = $this->ticket->zd_id;
        $comments = $client->getTicketComments($zdId);

        foreach ($comments as $comment) {
            $attachments = [];
            foreach ($comment['attachments'] ?? [] as $att) {
                $attachments[] = [
                    'id' => $att['id'] ?? null,
                    'filename' => $att['file_name'] ?? $att['filename'] ?? null,
                    'content_type' => $att['content_type'] ?? null,
                    'size' => $att['size'] ?? null,
                    'url' => $att['url'] ?? null,
                    'content_url' => $att['content_url'] ?? null,
                ];
            }

            ZdTicketComment::updateOrCreate(
                [
                    'ticket_id' => $this->ticket->id,
                    'zd_comment_id' => $comment['id'],
                ],
                [
                    'author_id' => $comment['author_id'] ?? null,
                    'created_at' => isset($comment['created_at']) ? \Carbon\Carbon::parse($comment['created_at']) : null,
                    'body' => $comment['body'] ?? null,
                    'html_body' => $comment['html_body'] ?? null,
                    'is_public' => $comment['public'] ?? true,
                    'attachments_json' => $attachments,
                ]
            );
        }

        $this->ticket->update(['ai_needs_refresh' => true]);
        Log::debug('FetchTicketCommentsJob completed', ['ticket_id' => $this->ticket->id]);
    }
}
