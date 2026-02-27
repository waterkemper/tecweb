<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AiSimilarTicket;
use App\Models\AiTicketAnalysis;
use App\Models\ZdTicket;
use App\Services\TicketRedactionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EstimateEffortJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(
        public ZdTicket $ticket
    ) {}

    public function handle(TicketRedactionService $redaction): void
    {
        $content = $this->buildContent();
        $redacted = $redaction->redact($content);

        $similarAvgHint = $this->getSimilarTicketsAvgHint();

        $model = config('openai.chat_model', 'gpt-4o-mini');
        $userContent = $redacted;
        if ($similarAvgHint) {
            $userContent .= "\n\n[Context: Similar tickets avg resolution: {$similarAvgHint} hours]";
        }

        $client = Http::withToken(config('openai.api_key'))->timeout(60);
        if (! config('openai.ssl_verify', true)) {
            $client = $client->withOptions(['verify' => false]);
        }
        $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You estimate resolution effort for support tickets. Return ONLY valid JSON with: hours_min (number), hours_max (number), confidence (0-1), reason (string, brief explanation in Brazilian Portuguese).',
                    ],
                    [
                        'role' => 'user',
                        'content' => $userContent,
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.2,
            ]);

        if (! $response->successful()) {
            Log::error('EstimateEffortJob failed', ['ticket_id' => $this->ticket->id, 'response' => $response->body()]);
            throw new \RuntimeException('OpenAI API error: ' . $response->body());
        }

        $body = $response->json();
        $content = $body['choices'][0]['message']['content'] ?? '{}';
        $data = json_decode($content, true) ?: [];

        AiTicketAnalysis::updateOrCreate(
            ['ticket_id' => $this->ticket->id],
            [
                'model_version' => $model,
                'effort_min' => $data['hours_min'] ?? $data['effort_min'] ?? null,
                'effort_max' => $data['hours_max'] ?? $data['effort_max'] ?? null,
                'confidence' => $data['confidence'] ?? null,
                'effort_reason' => $data['reason'] ?? null,
                'last_ai_refresh_at' => now(),
            ]
        );

        $this->ticket->update(['ai_needs_refresh' => false]);
    }

    private function buildContent(): string
    {
        $parts = [
            "Subject: {$this->ticket->subject}",
            "Description: {$this->ticket->description}",
        ];

        foreach ($this->ticket->comments as $c) {
            $parts[] = ($c->is_public ? '[Public]' : '[Internal]') . " {$c->created_at}: {$c->body}";
        }

        return implode("\n\n", $parts);
    }

    private function getSimilarTicketsAvgHint(): ?string
    {
        $similarIds = AiSimilarTicket::where('ticket_id', $this->ticket->id)
            ->with('similarTicket')
            ->limit(10)
            ->get()
            ->pluck('similarTicket')
            ->filter()
            ->pluck('id');

        if ($similarIds->isEmpty()) {
            return null;
        }

        $solved = ZdTicket::whereIn('id', $similarIds)
            ->whereIn('status', ['solved', 'closed'])
            ->whereNotNull('solved_at')
            ->get();

        if ($solved->isEmpty()) {
            return null;
        }

        $totalHours = 0;
        $count = 0;
        foreach ($solved as $t) {
            if ($t->created_at && $t->solved_at) {
                $totalHours += $t->created_at->diffInSeconds($t->solved_at) / 3600;
                $count++;
            }
        }

        return $count > 0 ? number_format($totalHours / $count, 1) : null;
    }
}
