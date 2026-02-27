<?php

declare(strict_types=1);

namespace App\Jobs;

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

class ClassifyTicketJob implements ShouldQueue
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

        $model = config('openai.chat_model', 'gpt-4o-mini');
        $client = Http::withToken(config('openai.api_key'))->timeout(60);
        if (! config('openai.ssl_verify', true)) {
            $client = $client->withOptions(['verify' => false]);
        }
        $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You classify support tickets. Return ALL text content in Brazilian Portuguese (pt-BR): categories (array, e.g. bug, feature, suporte, faturamento, integracao, acesso, performance, outro), modules (array, e.g. ERP, checkout, pagamento, catalogo, API), severity (critical|high|medium|low), urgency (now|soon|whenever), requires_dev (boolean), suggested_tags (array of strings in Portuguese).',
                    ],
                    [
                        'role' => 'user',
                        'content' => $redacted,
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.2,
            ]);

        if (! $response->successful()) {
            Log::error('ClassifyTicketJob failed', ['ticket_id' => $this->ticket->id, 'response' => $response->body()]);
            throw new \RuntimeException('OpenAI API error: ' . $response->body());
        }

        $body = $response->json();
        $content = $body['choices'][0]['message']['content'] ?? '{}';
        $data = json_decode($content, true) ?: [];

        $categories = $data['categories'] ?? [];
        $primaryCategory = is_array($categories) ? ($categories[0] ?? null) : $data['category'] ?? null;

        AiTicketAnalysis::updateOrCreate(
            ['ticket_id' => $this->ticket->id],
            [
                'model_version' => $model,
                'category' => $primaryCategory,
                'categories' => $categories,
                'modules' => $data['modules'] ?? [],
                'severity' => $data['severity'] ?? null,
                'urgency' => $data['urgency'] ?? null,
                'requires_dev' => $data['requires_dev'] ?? null,
                'suggested_tags' => $data['suggested_tags'] ?? [],
                'last_ai_refresh_at' => now(),
            ]
        );

        GenerateEmbeddingJob::dispatch($this->ticket);
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
}
