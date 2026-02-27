<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AiTicketEmbedding;
use App\Models\ZdTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Pgvector\Laravel\Vector;

class GenerateEmbeddingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(
        public ZdTicket $ticket
    ) {}

    public function handle(): void
    {
        $text = $this->ticket->subject . "\n" . ($this->ticket->description ?? '');
        foreach ($this->ticket->comments as $c) {
            $text .= "\n" . $c->body;
        }
        $text = mb_substr($text, 0, 8000);

        $model = config('openai.embedding_model', 'text-embedding-3-small');
        $client = Http::withToken(config('openai.api_key'))->timeout(30);
        if (! config('openai.ssl_verify', true)) {
            $client = $client->withOptions(['verify' => false]);
        }
        $response = $client->post('https://api.openai.com/v1/embeddings', [
                'model' => $model,
                'input' => $text,
            ]);

        if (! $response->successful()) {
            Log::error('GenerateEmbeddingJob failed', ['ticket_id' => $this->ticket->id]);
            throw new \RuntimeException('OpenAI embeddings API error');
        }

        $embedding = $response->json('data.0.embedding');
        if (! is_array($embedding)) {
            throw new \RuntimeException('Invalid embedding response');
        }

        AiTicketEmbedding::updateOrCreate(
            ['ticket_id' => $this->ticket->id],
            ['embedding' => new Vector($embedding)]
        );

        FindSimilarTicketsJob::dispatch($this->ticket);
    }
}
