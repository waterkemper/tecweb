<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\FetchTicketCommentsJob;
use App\Jobs\SummarizeTicketJob;
use Illuminate\Support\Facades\Bus;
use App\Models\AiSimilarTicket;
use App\Models\TicketOrder;
use App\Models\ZdTicket;
use App\Services\ZendeskClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $statusFilter = $request->get('status');
        $filters = $request->only(['q', 'status', 'priority', 'category', 'severity', 'tag', 'from', 'to', 'org', 'requester']);
        $sort = $request->get('sort', 'sequence');
        $dir = strtolower($request->get('dir', 'asc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['zd_id', 'subject', 'status', 'priority', 'zd_created_at', 'zd_updated_at', 'sequence'];

        $showResolved = in_array($statusFilter, ['', 'solved', 'closed'])
            || $statusFilter === null;
        $showActive = in_array($statusFilter, ['', 'new', 'open', 'pending', 'hold'])
            || $statusFilter === null;

        $tickets = $this->buildTicketsQuery(
            $request,
            $showActive ? $statusFilter : 'none',
            $filters,
            $sort,
            $dir,
            $allowedSort
        );

        $resolvedTickets = $showResolved
            ? $this->buildResolvedQuery($request, $statusFilter, $filters)
            : null;

        foreach (['from', 'to'] as $key) {
            $parsed = ZdTicket::parseDateInput($request->get($key));
            $filters[$key] = $parsed ?: ($filters[$key] ?? '');
        }
        if (! in_array($request->user()?->role, ['admin', 'colaborador'])) {
            unset($filters['org']);
        }

        $baseQuery = ZdTicket::query()->visibleToUser($request->user());
        $organizations = in_array($request->user()?->role, ['admin', 'colaborador'])
            ? \App\Models\ZdOrg::whereIn('zd_id', $baseQuery->clone()->pluck('org_id')->filter()->unique()->values())->orderBy('name')->get()
            : collect();
        $requesters = \App\Models\ZdUser::whereIn('zd_id', $baseQuery->clone()->pluck('requester_id')->filter()->unique()->values())
            ->orderBy('name')
            ->get();

        return view('tickets.index', [
            'tickets' => $tickets,
            'resolvedTickets' => $resolvedTickets,
            'filters' => $filters,
            'sort' => $sort,
            'dir' => $dir,
            'organizations' => $organizations,
            'requesters' => $requesters,
        ]);
    }

    private function buildTicketsQuery(Request $request, ?string $statusFilter, array $filters, string $sort, string $dir, array $allowedSort)
    {
        $user = $request->user();
        $query = ZdTicket::query()
            ->visibleToUser($user)
            ->with(['analysis' => fn ($q) => $q->latest()->limit(1), 'ticketOrder', 'requester', 'organization'])
            ->search($request->get('q'))
            ->filterOrg(in_array($user?->role, ['admin', 'colaborador']) ? $request->get('org') : null)
            ->filterRequester($request->get('requester'))
            ->filterPriority($request->get('priority'))
            ->filterCategory($request->get('category'))
            ->filterSeverity($request->get('severity'))
            ->filterTag($request->get('tag'))
            ->filterDateRange($request->get('from'), $request->get('to'));

        if ($statusFilter === 'none') {
            return $query->whereRaw('1=0')->paginate(100)->withQueryString();
        }
        if ($statusFilter) {
            $query->filterStatus($statusFilter);
        } else {
            $query->whereNotIn('status', ['solved', 'closed']);
        }

        if (in_array($sort, $allowedSort)) {
            if ($sort === 'priority') {
                $query->orderByPriority($dir);
            } elseif ($sort === 'sequence') {
                $query->orderBySequence($dir);
            } else {
                $query->orderBy($sort, $dir);
            }
        } else {
            $query->orderByRaw('COALESCE(zd_updated_at, updated_at) DESC');
        }

        return $query->paginate(100)->withQueryString();
    }

    private function buildResolvedQuery(Request $request, ?string $statusFilter, array $filters)
    {
        $user = $request->user();
        $query = ZdTicket::query()
            ->visibleToUser($user)
            ->with(['analysis' => fn ($q) => $q->latest()->limit(1), 'requester', 'organization'])
            ->search($request->get('q'))
            ->filterOrg(in_array($user?->role, ['admin', 'colaborador']) ? $request->get('org') : null)
            ->filterRequester($request->get('requester'))
            ->filterPriority($request->get('priority'))
            ->filterCategory($request->get('category'))
            ->filterSeverity($request->get('severity'))
            ->filterTag($request->get('tag'))
            ->filterDateRange($request->get('from'), $request->get('to'))
            ->whereIn('status', ['solved', 'closed']);

        if ($statusFilter === 'solved') {
            $query->where('status', 'solved');
        } elseif ($statusFilter === 'closed') {
            $query->where('status', 'closed');
        }

        return $query->orderByRaw('COALESCE(zd_updated_at, updated_at) DESC')->paginate(100, ['*'], 'resolved_page')->withQueryString();
    }

    public function show(Request $request, ZdTicket $ticket): View
    {
        $this->authorize('view', $ticket);

        $ticket->load([
            'comments.author',
            'analysis' => fn ($q) => $q->latest()->limit(1),
            'analysisHistory' => fn ($q) => $q->limit(10),
            'requester',
            'submitter',
            'assignee',
            'organization',
        ]);
        $latestAnalysis = $ticket->analysis()->latest()->first();
        $similarTickets = AiSimilarTicket::where('ticket_id', $ticket->id)
            ->with(['similarTicket.organization', 'similarTicket.requester'])
            ->orderByDesc('score')
            ->limit(5)
            ->get();

        $user = $request->user();
        if ($user && ! in_array($user->role, ['admin', 'colaborador'])) {
            $visibleSimilarIds = ZdTicket::visibleToUser($user)
                ->whereIn('id', $similarTickets->pluck('similar_ticket_id'))
                ->pluck('id')
                ->toArray();
            $similarTickets = $similarTickets->filter(fn ($s) => in_array($s->similar_ticket_id, $visibleSimilarIds, true));
        }

        $similarTicketsAvgHours = $this->computeSimilarTicketsAvgHours($ticket, $similarTickets->pluck('similar_ticket_id')->toArray());

        return view('tickets.show', [
            'ticket' => $ticket,
            'analysis' => $latestAnalysis,
            'similarTickets' => $similarTickets,
            'similarTicketsAvgHours' => $similarTicketsAvgHours,
        ]);
    }

    public function refreshAi(ZdTicket $ticket): RedirectResponse
    {
        $this->authorize('view', $ticket);

        if (! auth()->user()?->isAdmin()) {
            abort(403, 'Apenas administradores podem atualizar a análise de IA.');
        }

        Bus::chain([
            new FetchTicketCommentsJob($ticket),
            new SummarizeTicketJob($ticket, refreshOnly: true),
        ])->dispatch();

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Busca de comentários e atualização de IA na fila. Recarregue em alguns segundos.');
    }

    public function attachment(ZdTicket $ticket, int $commentId, int $index, ZendeskClient $client): Response|RedirectResponse
    {
        $this->authorize('view', $ticket);

        $comment = $ticket->comments()->where('id', $commentId)->first();
        if (! $comment || ! is_array($comment->attachments_json)) {
            abort(404);
        }
        $attachments = $comment->attachments_json;
        if (! isset($attachments[$index])) {
            abort(404);
        }
        $att = $attachments[$index];
        $contentUrl = $att['content_url'] ?? null;
        if (! $contentUrl) {
            abort(404);
        }
        $httpResponse = $client->fetchAttachment($contentUrl);
        if (! $httpResponse) {
            return redirect()->route('tickets.show', $ticket)
                ->with('error', 'Não foi possível carregar o anexo.');
        }
        $filename = $att['filename'] ?? 'attachment';
        $contentType = $att['content_type'] ?? 'application/octet-stream';
        $isImage = str_starts_with($contentType, 'image/');
        return response($httpResponse->body(), 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => $isImage ? 'inline' : 'attachment; filename="' . addslashes($filename) . '"',
        ]);
    }

    public function updateInternalEffort(Request $request, ZdTicket $ticket): RedirectResponse
    {
        $this->authorize('view', $ticket);

        if (! in_array(auth()->user()?->role, ['admin', 'colaborador'])) {
            abort(403, 'Apenas administradores e colaboradores podem alterar a previsão de horas.');
        }

        $analysis = $ticket->analysis()->latest()->first();
        if (! $analysis) {
            return redirect()->route('tickets.show', $ticket)
                ->with('error', 'Nenhuma análise IA encontrada.');
        }

        $min = $request->input('internal_effort_min');
        $max = $request->input('internal_effort_max');

        $analysis->update([
            'internal_effort_min' => $min !== '' && $min !== null ? (float) $min : null,
            'internal_effort_max' => $max !== '' && $max !== null ? (float) $max : null,
        ]);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Previsão interna atualizada.');
    }

    public function reorder(Request $request): JsonResponse|RedirectResponse
    {
        if (! in_array($request->user()?->role, ['admin', 'colaborador'])) {
            abort(403, 'Apenas administradores e colaboradores podem reordenar tickets.');
        }

        $ids = $request->input('ticket_ids', []);
        if (! is_array($ids) || empty($ids)) {
            return $request->expectsJson()
                ? response()->json(['error' => 'Nenhum ticket para ordenar.'], 422)
                : redirect()->route('tickets.index')->with('error', 'Nenhum ticket para ordenar.');
        }

        $ticketIds = array_map('intval', array_values($ids));
        $tickets = ZdTicket::visibleToUser($request->user())
            ->whereIn('id', $ticketIds)
            ->get(['id', 'requester_id'])
            ->keyBy('id');

        $validIds = $tickets->pluck('id')->toArray();

        $seqByRequester = [];
        foreach ($ticketIds as $ticketId) {
            $ticket = $tickets->get($ticketId);
            if (! $ticket || ! in_array($ticketId, $validIds)) {
                continue;
            }
            $reqId = $ticket->requester_id ?? 0;
            if (! isset($seqByRequester[$reqId])) {
                $seqByRequester[$reqId] = 0;
            }
            $seq = $seqByRequester[$reqId]++;
            TicketOrder::updateOrCreate(
                ['ticket_id' => $ticketId],
                ['requester_id' => $ticket->requester_id, 'sequence' => $seq]
            );
        }

        return $request->expectsJson()
            ? response()->json(['success' => true])
            : redirect()->route('tickets.index', array_merge(
                $request->only(['q', 'status', 'priority', 'category', 'severity', 'tag', 'from', 'to']),
                ['sort' => 'sequence', 'dir' => 'asc']
            ))->with('success', 'Ordem atualizada.');
    }

    public function applySuggestedTags(ZdTicket $ticket, ZendeskClient $client): RedirectResponse
    {
        $this->authorize('view', $ticket);

        if (! in_array(auth()->user()?->role, ['admin', 'colaborador'])) {
            abort(403, 'Apenas administradores e colaboradores podem aplicar tags.');
        }

        $analysis = $ticket->analysis()->latest()->first();
        $suggested = $analysis?->suggested_tags ?? [];

        if (empty($suggested)) {
            return redirect()->route('tickets.show', $ticket)
                ->with('error', 'Nenhuma tag sugerida.');
        }

        $current = $ticket->tags ?? [];
        $merged = array_values(array_unique(array_merge($current, $suggested)));

        try {
            $client->updateTicket((int) $ticket->zd_id, ['tags' => $merged]);
        } catch (\Throwable $e) {
            return redirect()->route('tickets.show', $ticket)
                ->with('error', 'Erro ao aplicar tags no Zendesk: ' . $e->getMessage());
        }

        $ticket->update(['tags' => $merged, 'zd_updated_at' => now()]);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Tags aplicadas.');
    }

    public function updateTags(Request $request, ZdTicket $ticket, ZendeskClient $client): RedirectResponse
    {
        $this->authorize('view', $ticket);

        if (! in_array(auth()->user()?->role, ['admin', 'colaborador'])) {
            abort(403, 'Apenas administradores e colaboradores podem alterar tags.');
        }

        $input = (string) $request->input('tags', '');
        $tags = array_values(array_unique(array_filter(array_map(function (string $t) {
            $s = trim($t);
            return $s !== '' ? preg_replace('/[^\p{L}\p{N}\-_:\/]/u', '', $s) : '';
        }, preg_split('/[\s,]+/', $input)))));

        if (strlen(implode('', $tags)) > 5096) {
            return redirect()->route('tickets.show', $ticket)
                ->with('error', 'Tags excedem o limite de 5.096 caracteres.');
        }

        try {
            $client->updateTicket((int) $ticket->zd_id, ['tags' => $tags]);
        } catch (\Throwable $e) {
            return redirect()->route('tickets.show', $ticket)
                ->with('error', 'Erro ao atualizar tags no Zendesk: ' . $e->getMessage());
        }

        $ticket->update(['tags' => $tags, 'zd_updated_at' => now()]);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Tags atualizadas.');
    }

    public function syncTags(ZdTicket $ticket, ZendeskClient $client): RedirectResponse
    {
        $this->authorize('view', $ticket);

        if (! in_array(auth()->user()?->role, ['admin', 'colaborador'])) {
            abort(403, 'Apenas administradores e colaboradores podem sincronizar tags.');
        }

        try {
            $raw = $client->getTicket((int) $ticket->zd_id);
        } catch (\Throwable $e) {
            return redirect()->route('tickets.show', $ticket)
                ->with('error', 'Erro ao buscar ticket no Zendesk: ' . $e->getMessage());
        }

        if ($raw === null) {
            return redirect()->route('tickets.show', $ticket)
                ->with('error', 'Ticket não encontrado no Zendesk.');
        }

        $tags = $raw['tags'] ?? [];
        $ticket->update(['tags' => $tags, 'zd_updated_at' => isset($raw['updated_at']) ? \Carbon\Carbon::parse($raw['updated_at']) : $ticket->zd_updated_at]);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Tags sincronizadas do Zendesk.');
    }

    public function storeComment(Request $request, ZdTicket $ticket, ZendeskClient $client): RedirectResponse
    {
        $this->authorize('view', $ticket);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:65535'],
            'is_internal' => ['nullable', 'boolean'],
        ]);

        $body = trim($validated['body']);
        if ($body === '') {
            return redirect()->route('tickets.show', $ticket)
                ->with('error', 'O comentário não pode estar vazio.');
        }

        $canAddInternal = in_array(auth()->user()?->role, ['admin', 'colaborador']);
        $isPublic = $canAddInternal ? ! $request->boolean('is_internal') : true;

        $authorId = auth()->user()?->zd_id;
        if ($authorId === null && ! $canAddInternal) {
            return redirect()->route('tickets.show', $ticket)
                ->with('error', 'Seu usuário não está vinculado ao Zendesk. Entre em contato com o administrador.');
        }

        $uploadTokens = [];

        $maxFileSize = 50 * 1024 * 1024; // 50 MB
        $files = $request->file('attachments', []);
        if (! is_array($files)) {
            $files = $files ? [$files] : [];
        }

        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }
            if ($file->getSize() > $maxFileSize) {
                return redirect()->route('tickets.show', $ticket)
                    ->with('error', 'O arquivo "' . $file->getClientOriginalName() . '" excede o limite de 50 MB.');
            }
            $content = $file->get();
            $filename = $file->getClientOriginalName() ?: 'attachment';
            $contentType = $file->getMimeType() ?: 'application/octet-stream';
            $token = $client->uploadFile($filename, $content, $contentType);
            if ($token !== null) {
                $uploadTokens[] = $token;
            }
        }

        try {
            $client->addTicketComment(
                (int) $ticket->zd_id,
                $body,
                $isPublic,
                $uploadTokens,
                $authorId
            );
        } catch (\Throwable $e) {
            return redirect()->route('tickets.show', $ticket)
                ->with('error', 'Erro ao enviar comentário: ' . $e->getMessage());
        }

        FetchTicketCommentsJob::dispatch($ticket);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Comentário enviado com sucesso.');
    }

    public function updateStatus(Request $request, ZdTicket $ticket, ZendeskClient $client): RedirectResponse
    {
        $this->authorize('view', $ticket);

        if (! in_array(auth()->user()?->role, ['admin', 'colaborador'])) {
            abort(403, 'Apenas administradores e colaboradores podem alterar o status.');
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:new,open,pending,hold,solved,closed'],
        ]);

        try {
            $client->updateTicket((int) $ticket->zd_id, ['status' => $validated['status']]);
        } catch (\Throwable $e) {
            return redirect()->route('tickets.show', $ticket)
                ->with('error', 'Erro ao alterar status: ' . $e->getMessage());
        }

        $ticket->update([
            'status' => $validated['status'],
            'zd_updated_at' => now(),
        ]);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Status atualizado.');
    }

    public function updatePendingAction(Request $request, ZdTicket $ticket): RedirectResponse
    {
        $this->authorize('view', $ticket);

        if (! in_array(auth()->user()?->role, ['admin', 'colaborador'])) {
            abort(403, 'Apenas administradores e colaboradores podem alterar o campo pendente por.');
        }

        $validated = $request->validate([
            'pending_action' => ['nullable', 'string', 'in:our_side,customer_side,can_close'],
        ]);

        $analysis = $ticket->analysis()->latest()->first();
        if (! $analysis) {
            return redirect()->route('tickets.show', $ticket)
                ->with('error', 'Nenhuma análise IA encontrada.');
        }

        $analysis->update([
            'pending_action' => $validated['pending_action'] ?: null,
        ]);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Pendente por atualizado.');
    }

    private function computeSimilarTicketsAvgHours(ZdTicket $ticket, ?array $filterSimilarIds = null): ?float
    {
        $similarIds = $filterSimilarIds !== null
            ? collect($filterSimilarIds)
            : AiSimilarTicket::where('ticket_id', $ticket->id)->pluck('similar_ticket_id');

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
        foreach ($solved as $similar) {
            $createdAt = $similar->zd_created_at ?? $similar->created_at;
            if ($createdAt && $similar->solved_at) {
                $totalHours += $createdAt->diffInSeconds($similar->solved_at) / 3600;
                $count++;
            }
        }

        return $count > 0 ? round($totalHours / $count, 1) : null;
    }
}
