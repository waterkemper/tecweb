<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AiSimilarTicket;
use App\Models\AiTicketEmbedding;
use App\Models\ZdTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class FindSimilarTicketsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public ZdTicket $ticket
    ) {}

    public function handle(): void
    {
        $embedding = AiTicketEmbedding::where('ticket_id', $this->ticket->id)->first();
        if (! $embedding) {
            EstimateEffortJob::dispatch($this->ticket);
            return;
        }

        AiSimilarTicket::where('ticket_id', $this->ticket->id)->delete();

        $vectorStr = (string) $embedding->embedding;
        $rows = DB::select(
            'SELECT ticket_id, 1 - (embedding <=> ?::vector) as score FROM ai_ticket_embeddings WHERE ticket_id != ? ORDER BY embedding <=> ?::vector LIMIT 10',
            [$vectorStr, $this->ticket->id, $vectorStr]
        );

        $requesterId = $this->ticket->requester_id;
        $createdAt = $this->ticket->created_at;

        foreach ($rows as $row) {
            $similarTicket = ZdTicket::find($row->ticket_id);
            if (! $similarTicket) {
                continue;
            }

            $score = (float) $row->score;
            $isDuplicate = false;
            $rationale = 'Semantic similarity';

            if ($requesterId && $similarTicket->requester_id == $requesterId && $createdAt->diffInDays($similarTicket->created_at) <= 7) {
                $score = min(1.0, $score * 1.2);
                $isDuplicate = $score > 0.85;
                $rationale = 'Same requester, similar topic, within 7 days';
            }

            AiSimilarTicket::create([
                'ticket_id' => $this->ticket->id,
                'similar_ticket_id' => $similarTicket->id,
                'score' => $score,
                'rationale' => $rationale,
                'is_duplicate_candidate' => $isDuplicate,
            ]);
        }

        EstimateEffortJob::dispatch($this->ticket);
    }
}
