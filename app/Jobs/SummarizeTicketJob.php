<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AiTicketAnalysis;
use App\Models\AiTicketAnalysisHistory;
use App\Models\ZdTicket;
use App\Services\TicketRedactionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SummarizeTicketJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(
        public ZdTicket $ticket,
        public bool $refreshOnly = false
    ) {}

    public function handle(TicketRedactionService $redaction): void
    {
        $content = $this->buildContent();
        $hash = hash('sha256', $content);

        $analysis = AiTicketAnalysis::firstWhere('ticket_id', $this->ticket->id);
        if ($analysis && $analysis->content_hash === $hash) {
            $this->ticket->update(['ai_needs_refresh' => false]);
            Log::debug('SummarizeTicketJob: content unchanged, skipped', ['ticket_id' => $this->ticket->id]);

            return;
        }

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
                        'content' => 'You analyze support tickets and produce a Gmail-style conversation summary. Return ALL text content in Brazilian Portuguese (pt-BR). Analyze the ENTIRE conversation. Messages are labeled [Customer] or [Agent]. Return ONLY valid JSON with: bullets (array of 3-6 strings), what_reported, what_tried, current_status, open_questions_list, actions_needed_list, next_action, pending_action (our_side|customer_side|can_close).

OPEN_QUESTIONS_LIST: Only questions with NO agent answer. If the agent gave dimensions (e.g. 1920 x 640), said vamos habilitar or we will enable, ja ajustamos, confirmed a location — that question is ANSWERED, do NOT include it. When the agent says se quiser passar informacoes podemos incluir (if you want to pass info we can include it) — that is an optional offer, NOT an open question; leave it out.

ACTIONS_NEEDED_LIST: Only what we STILL need to do. If the agent said they did it, or vamos habilitar / we will do it, remove from the list. Keep only concrete pending work (e.g. Enable SEO fields for branch registration when we promised to do it but have not yet). When the agent offered optional customer input ("if you want to pass info we can include it"), the action is still on us (develop the feature); optionally we may receive customer text — that does not add a new action.

NEXT_ACTION: Passo concreto e acionavel. Quando pending_action=customer_side e oferecemos input opcional, next_action pode ser: "Abrir feature request; cliente pode enviar texto para inclusao temporaria" ou "Aguardar cliente enviar texto para SEO se tiver pressa, ou prosseguir com desenvolvimento". Quando actions_needed tem itens, sugerir abrir feature request ou ticket de dev. Nunca diga que nao ha nada pendente quando houver itens.

When in doubt, if an [Agent] message clearly addresses a customer question or request, consider it resolved. Return empty arrays when nothing remains.',
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
            Log::error('SummarizeTicketJob failed', ['ticket_id' => $this->ticket->id, 'response' => $response->body()]);
            throw new \RuntimeException('OpenAI API error: ' . $response->body());
        }

        $body = $response->json();
        $content = $body['choices'][0]['message']['content'] ?? '{}';
        $data = json_decode($content, true) ?: [];

        $bullets = $data['bullets'] ?? [];
        $summary = is_array($bullets)
            ? implode("\n", array_map(fn($b) => '• ' . $b, $bullets))
            : $this->ensureString($data['summary'] ?? null);

        $summaryFields = [
            'model_version' => $model,
            'summary' => $this->ensureString($summary),
            'bullets' => is_array($bullets) ? $bullets : [],
            'what_reported' => $this->ensureString($data['what_reported'] ?? null),
            'what_tried' => $this->ensureString($data['what_tried'] ?? null),
            'current_status' => $this->ensureString($data['current_status'] ?? null),
            'open_questions' => $this->ensureString($data['open_questions'] ?? null),
            'open_questions_list' => $this->normalizeStringArray($data['open_questions_list'] ?? []),
            'actions_needed_list' => $this->normalizeStringArray($data['actions_needed_list'] ?? []),
            'next_action' => $this->ensureString($data['next_action'] ?? null),
            'pending_action' => $this->normalizePendingAction($data['pending_action'] ?? null),
            'last_ai_refresh_at' => now(),
            'content_hash' => $hash,
        ];

        if ($this->refreshOnly) {
            $analysis = AiTicketAnalysis::firstWhere('ticket_id', $this->ticket->id);
            if ($analysis) {
                $this->saveToHistory($analysis);
                $analysis->update($summaryFields);
            } else {
                AiTicketAnalysis::create(array_merge(['ticket_id' => $this->ticket->id], $summaryFields));
            }
            $this->ticket->update(['ai_needs_refresh' => false]);
        } else {
            AiTicketAnalysis::updateOrCreate(
                ['ticket_id' => $this->ticket->id],
                $summaryFields
            );

            $this->ticket->update(['ai_needs_refresh' => false]);

            $pendingAction = $this->normalizePendingAction($data['pending_action'] ?? null);
            $openQuestions = $this->normalizeStringArray($data['open_questions_list'] ?? []);
            $actionsNeeded = $this->normalizeStringArray($data['actions_needed_list'] ?? []);

            $isResolved = $pendingAction === 'can_close'
                && empty($openQuestions)
                && empty($actionsNeeded);

            if (! $isResolved) {
                ClassifyTicketJob::dispatch($this->ticket);
            } else {
                Log::debug('SummarizeTicketJob: ticket resolved, skipped classification/effort', ['ticket_id' => $this->ticket->id]);
            }
        }
    }

    private function buildContent(): string
    {
        return $this->ticket->contentForHash();
    }

    private function ensureString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return (string) $value;
    }

    private function normalizePendingAction(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        $v = strtolower(trim($value));
        return in_array($v, ['our_side', 'customer_side', 'can_close']) ? $v : null;
    }

    private function normalizeStringArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $items = array_filter(array_map(fn ($v) => is_string($v) ? trim($v) : null, $value));
        return array_values(array_filter($items));
    }

    private function saveToHistory(AiTicketAnalysis $analysis): void
    {
        $attrs = $analysis->only([
            'ticket_id', 'model_version', 'summary', 'bullets', 'what_reported', 'what_tried',
            'current_status', 'open_questions', 'open_questions_list', 'next_action',
            'actions_needed_list', 'pending_action', 'category', 'categories', 'modules',
            'severity', 'urgency', 'requires_dev', 'effort_min', 'effort_max',
            'confidence', 'effort_reason',
        ]);
        $attrs['snapshot_at'] = now();
        AiTicketAnalysisHistory::create($attrs);
    }
}
