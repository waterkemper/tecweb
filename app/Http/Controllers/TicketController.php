<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Tickets\ReorderTicketsRequest;
use App\Http\Requests\Tickets\StoreTicketRequest;
use App\Http\Requests\Tickets\StoreTicketCommentRequest;
use App\Http\Requests\Tickets\UpdateDeadlineRequest;
use App\Http\Requests\Tickets\UpdatePendingActionRequest;
use App\Http\Requests\Tickets\UpdateStatusRequest;
use App\Http\Requests\Tickets\UpdateTagsRequest;
use App\Jobs\FetchTicketCommentsJob;
use App\Jobs\SummarizeTicketJob;
use App\Jobs\SyncZendeskTicketsJob;
use Illuminate\Support\Facades\Bus;
use App\Models\AiSimilarTicket;
use App\Models\ZdTicket;
use App\Services\TicketCommentService;
use App\Services\TicketTagService;
use App\Services\TicketWorkflowService;
use App\Services\ZendeskClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $statusFilter = $request->get('status');
        $filters = $request->only(['q', 'status', 'priority', 'category', 'severity', 'tag', 'from', 'to', 'org', 'requester', 'mine', 'overdue', 'without_deadline', 'due_from', 'due_to']);
        if ($request->user()?->role === 'cliente' && ! $request->has('mine')) {
            $filters['mine'] = 1;
        }
        $sort = $request->get('sort', 'sequence');
        $dir = strtolower($request->get('dir', 'asc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['zd_id', 'subject', 'status', 'priority', 'zd_created_at', 'zd_updated_at', 'sequence', 'due_at'];

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

        foreach (['from', 'to', 'due_from', 'due_to'] as $key) {
            $parsed = ZdTicket::parseDateInput($request->get($key));
            $filters[$key] = $parsed ?: ($filters[$key] ?? '');
        }
        if (! in_array($request->user()?->role, ['admin', 'colaborador'])) {
            unset($filters['org']);
        }

        $visibleTicketsQuery = ZdTicket::query()->visibleToUser($request->user());
        $organizations = in_array($request->user()?->role, ['admin', 'colaborador'])
            ? \App\Models\ZdOrg::query()
                ->whereIn('zd_id', (clone $visibleTicketsQuery)->select('org_id')->whereNotNull('org_id')->distinct())
                ->orderBy('name')
                ->get()
            : collect();
        $requesters = \App\Models\ZdUser::query()
            ->whereIn('zd_id', (clone $visibleTicketsQuery)->select('requester_id')->whereNotNull('requester_id')->distinct())
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
        $useMine = ($user?->role === 'cliente' && ! $request->has('mine')) || $request->boolean('mine');
        $requesterFilter = $useMine && $user?->zd_id ? $user->zd_id : $request->get('requester');
        $query = ZdTicket::query()
            ->visibleToUser($user)
            ->with(['analysis' => fn ($q) => $q->latest()->limit(1), 'ticketOrder', 'requester', 'organization'])
            ->search($request->get('q'))
            ->filterOrg(in_array($user?->role, ['admin', 'colaborador']) ? $request->get('org') : null)
            ->filterRequester($requesterFilter)
            ->filterPriority($request->get('priority'))
            ->filterCategory($request->get('category'))
            ->filterSeverity($request->get('severity'))
            ->filterTag($request->get('tag'))
            ->filterDateRange($request->get('from'), $request->get('to'));

        if ($request->boolean('overdue')) {
            $query->filterOverdue();
        }
        if ($request->boolean('without_deadline')) {
            $query->filterWithoutDeadline();
        }
        $query->filterDueDateRange($request->get('due_from'), $request->get('due_to'));

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
            } elseif ($sort === 'due_at') {
                $query->orderByRaw('due_at IS NULL ' . ($dir === 'asc' ? 'ASC' : 'DESC'))
                    ->orderBy('due_at', $dir);
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
        $useMine = ($user?->role === 'cliente' && ! $request->has('mine')) || $request->boolean('mine');
        $requesterFilter = $useMine && $user?->zd_id ? $user->zd_id : $request->get('requester');
        $query = ZdTicket::query()
            ->visibleToUser($user)
            ->with(['analysis' => fn ($q) => $q->latest()->limit(1), 'requester', 'organization'])
            ->search($request->get('q'))
            ->filterOrg(in_array($user?->role, ['admin', 'colaborador']) ? $request->get('org') : null)
            ->filterRequester($requesterFilter)
            ->filterPriority($request->get('priority'))
            ->filterCategory($request->get('category'))
            ->filterSeverity($request->get('severity'))
            ->filterTag($request->get('tag'))
            ->filterDateRange($request->get('from'), $request->get('to'))
            ->filterDueDateRange($request->get('due_from'), $request->get('due_to'))
            ->whereIn('status', ['solved', 'closed']);

        if ($statusFilter === 'solved') {
            $query->where('status', 'solved');
        } elseif ($statusFilter === 'closed') {
            $query->where('status', 'closed');
        }

        return $query->orderByRaw('COALESCE(zd_updated_at, updated_at) DESC')->paginate(100, ['*'], 'resolved_page')->withQueryString();
    }


    public function create(): View
    {
        if (auth()->user()?->role !== 'cliente') {
            abort(403, 'Apenas clientes podem abrir novos tickets por este portal.');
        }

        return view('tickets.create');
    }

    public function store(StoreTicketRequest $request, ZendeskClient $client, TicketCommentService $commentService): RedirectResponse
    {
        if ($request->user()?->role !== 'cliente') {
            abort(403, 'Apenas clientes podem abrir novos tickets por este portal.');
        }

        $authorId = $request->user()?->zd_id;
        if ($authorId === null) {
            return redirect()->route('tickets.create')
                ->withInput()
                ->with('error', 'Seu usuário não está vinculado ao Zendesk. Entre em contato com o administrador.');
        }

        $validated = $request->validated();

        $files = $request->file('attachments', []);
        if (! is_array($files)) {
            $files = $files ? [$files] : [];
        }

        try {
            $uploadTokens = $commentService->uploadAttachments($files, $client);
        } catch (\RuntimeException $e) {
            return redirect()->route('tickets.create')->withInput()->with('error', $e->getMessage());
        }

        $ticketPayload = [
            'subject' => trim((string) $validated['subject']),
            'comment' => [
                'body' => trim((string) $validated['description']),
                'public' => true,
                'author_id' => $authorId,
            ],
            'requester_id' => $authorId,
            'submitter_id' => $authorId,
        ];

        if (! empty($validated['priority'])) {
            $ticketPayload['priority'] = $validated['priority'];
        }

        if (! empty($uploadTokens)) {
            $ticketPayload['comment']['uploads'] = $uploadTokens;
        }

        $dueAt = null;
        if (! empty($validated['due_at'])) {
            $dueAt = \Carbon\Carbon::parse($validated['due_at'])->startOfDay();
        }

        try {
            $created = $client->createTicket($ticketPayload);
        } catch (\Throwable $e) {
            Log::warning('TicketController create ticket failed', [
                'user_id' => $request->user()?->id,
                'zd_user_id' => $authorId,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('tickets.create')
                ->withInput()
                ->with('error', 'Não foi possível criar o ticket no Zendesk. Tente novamente em instantes.');
        }

        $zdTicketId = (int) ($created['id'] ?? 0);
        if ($zdTicketId <= 0) {
            return redirect()->route('tickets.index')
                ->with('success', 'Ticket criado com sucesso! A sincronização ocorrerá em instantes.');
        }

        SyncZendeskTicketsJob::dispatch(now()->subMinutes(5)->timestamp, true);

        $localTicket = ZdTicket::withoutGlobalScope('not_merged')->where('zd_id', $zdTicketId)->first();

        if ($localTicket && $dueAt !== null) {
            $localTicket->update(['due_at' => $dueAt]);
        }

        if ($localTicket) {
            return redirect()->route('tickets.show', $localTicket)
                ->with('success', 'Ticket criado com sucesso!');
        }

        return redirect()->route('tickets.index')
            ->with('success', 'Ticket criado com sucesso! A sincronização ocorrerá em instantes.');
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

    public function updateInternalEffort(Request $request, ZdTicket $ticket): JsonResponse|RedirectResponse
    {
        $this->authorize('view', $ticket);

        if (! in_array(auth()->user()?->role, ['admin', 'colaborador'])) {
            abort(403, 'Apenas administradores e colaboradores podem alterar a previsão de horas.');
        }

        $analysis = $ticket->analysis()->latest()->first();
        if (! $analysis) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => 'Nenhuma análise IA encontrada.'], 422)
                : redirect()->route('tickets.show', $ticket)->with('error', 'Nenhuma análise IA encontrada.');
        }

        $min = $request->input('internal_effort_min');
        $max = $request->input('internal_effort_max');

        $analysis->update([
            'internal_effort_min' => $min !== '' && $min !== null ? (float) $min : null,
            'internal_effort_max' => $max !== '' && $max !== null ? (float) $max : null,
        ]);

        return $request->expectsJson()
            ? response()->json(['success' => true])
            : redirect()->route('tickets.show', $ticket)->with('success', 'Previsão interna atualizada.');
    }

    public function reorder(ReorderTicketsRequest $request, TicketWorkflowService $workflow): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();
        $workflow->reorderTickets($request->user(), $validated['ticket_ids']);

        return $request->expectsJson()
            ? response()->json(['success' => true])
            : redirect()->route('tickets.index', array_merge(
                $request->only(['q', 'status', 'priority', 'category', 'severity', 'tag', 'from', 'to', 'overdue', 'without_deadline', 'due_from', 'due_to']),
                ['sort' => 'sequence', 'dir' => 'asc']
            ))->with('success', 'Ordem atualizada.');
    }

    public function applySuggestedTags(ZdTicket $ticket, ZendeskClient $client, TicketTagService $tagsService): RedirectResponse
    {
        $this->authorize('view', $ticket);

        if (! $this->isStaffUser()) {
            abort(403, 'Apenas administradores e colaboradores podem aplicar tags.');
        }

        try {
            if (! $tagsService->applySuggestedTags($ticket, $client)) {
                return redirect()->route('tickets.show', $ticket)
                    ->with('error', 'Nenhuma tag sugerida.');
            }
        } catch (\Throwable $e) {
            return $this->redirectWithZendeskError($ticket, $e, 'Erro ao aplicar tags no Zendesk.');
        }

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Tags aplicadas.');
    }

    public function updateTags(UpdateTagsRequest $request, ZdTicket $ticket, ZendeskClient $client, TicketTagService $tagsService): RedirectResponse
    {
        $this->authorize('view', $ticket);

        $input = (string) $request->validated('tags', '');
        $tags = $tagsService->parseTags($input);

        if (strlen(implode('', $tags)) > 5096) {
            return redirect()->route('tickets.show', $ticket)
                ->with('error', 'Tags excedem o limite de 5.096 caracteres.');
        }

        try {
            $tagsService->updateTags($ticket, $tags, $client);
        } catch (\Throwable $e) {
            return $this->redirectWithZendeskError($ticket, $e, 'Erro ao atualizar tags no Zendesk.');
        }

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Tags atualizadas.');
    }

    public function syncTags(ZdTicket $ticket, ZendeskClient $client, TicketTagService $tagsService): RedirectResponse
    {
        $this->authorize('view', $ticket);

        if (! $this->isStaffUser()) {
            abort(403, 'Apenas administradores e colaboradores podem sincronizar tags.');
        }

        try {
            $synced = $tagsService->syncTags($ticket, $client);
        } catch (\Throwable $e) {
            return $this->redirectWithZendeskError($ticket, $e, 'Erro ao buscar ticket no Zendesk.');
        }

        if (! $synced) {
            return redirect()->route('tickets.show', $ticket)
                ->with('error', 'Ticket não encontrado no Zendesk.');
        }

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Tags sincronizadas do Zendesk.');
    }

    public function storeComment(StoreTicketCommentRequest $request, ZdTicket $ticket, ZendeskClient $client, TicketCommentService $commentService): RedirectResponse
    {
        $this->authorize('view', $ticket);

        $body = trim((string) $request->validated('body'));
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

        $files = $request->file('attachments', []);
        if (! is_array($files)) {
            $files = $files ? [$files] : [];
        }

        try {
            $uploadTokens = $commentService->uploadAttachments($files, $client);
        } catch (\RuntimeException $e) {
            return redirect()->route('tickets.show', $ticket)->with('error', $e->getMessage());
        }

        $statusToSet = null;
        if ($request->boolean('close_with_comment') && $canAddInternal && ! in_array($ticket->status ?? '', ['closed'])) {
            $statusToSet = in_array($ticket->status ?? '', ['solved']) ? 'closed' : 'solved';
        }
        $alsoUpdateStatus = $request->validated('also_update_status');
        if ($alsoUpdateStatus && $canAddInternal && ! in_array($ticket->status ?? '', ['closed'])) {
            $statusToSet = $alsoUpdateStatus;
        }

        try {
            $commentService->addComment($ticket, $body, $isPublic, $uploadTokens, $authorId, $client, $statusToSet);
        } catch (\Throwable $e) {
            return $this->redirectWithZendeskError($ticket, $e, 'Erro ao enviar comentário.');
        }

        if ($statusToSet !== null) {
            $ticket->update([
                'status' => $statusToSet,
                'zd_updated_at' => now(),
            ]);
        }

        FetchTicketCommentsJob::dispatchSync($ticket);

        $message = $statusToSet === 'closed'
            ? 'Comentário enviado e ticket fechado.'
            : ($statusToSet === 'solved'
                ? 'Comentário enviado e ticket marcado como resolvido.'
                : 'Comentário enviado com sucesso.');

        return redirect()->route('tickets.show', $ticket)
            ->with('success', $message);
    }

    public function updateStatus(UpdateStatusRequest $request, ZdTicket $ticket, ZendeskClient $client, TicketWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('view', $ticket);

        $validated = $request->validated();

        try {
            $workflow->updateTicketStatus($ticket, $validated['status'], $client);
        } catch (\Throwable $e) {
            return $this->redirectWithZendeskError($ticket, $e, 'Erro ao alterar status.');
        }

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Status atualizado.');
    }

    public function updateDeadline(UpdateDeadlineRequest $request, ZdTicket $ticket, ZendeskClient $client): JsonResponse|RedirectResponse
    {
        $this->authorize('view', $ticket);

        $dueAt = $request->boolean('clear')
            ? null
            : (isset($request->validated()['due_at']) && $request->validated()['due_at'] !== ''
                ? \Carbon\Carbon::parse($request->validated()['due_at'])->startOfDay()
                : null);

        $ticket->update(['due_at' => $dueAt]);

        if ($ticket->type === 'task' && $client) {
            try {
                $payload = ['due_at' => $dueAt ? $dueAt->toIso8601String() : null];
                $client->updateTicket((int) $ticket->zd_id, $payload);
            } catch (\Throwable $e) {
                Log::warning('TicketController: failed to sync due_at to Zendesk', [
                    'ticket_id' => $ticket->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $request->expectsJson()
            ? response()->json(['success' => true])
            : redirect()->route('tickets.show', $ticket)
                ->with('success', $dueAt ? 'Prazo de entrega atualizado.' : 'Prazo de entrega removido.');
    }

    public function updatePendingAction(UpdatePendingActionRequest $request, ZdTicket $ticket, TicketWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('view', $ticket);

        $validated = $request->validated();

        if (! $workflow->updatePendingAction($ticket, $validated['pending_action'] ?? null)) {
            return redirect()->route('tickets.show', $ticket)
                ->with('error', 'Nenhuma análise IA encontrada.');
        }

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Pendente por atualizado.');
    }

    private function isStaffUser(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'colaborador'], true);
    }

    private function redirectWithZendeskError(ZdTicket $ticket, \Throwable $error, string $message): RedirectResponse
    {
        Log::warning('TicketController Zendesk operation failed', [
            'ticket_id' => $ticket->id,
            'zd_ticket_id' => $ticket->zd_id,
            'error' => $error->getMessage(),
        ]);

        return redirect()->route('tickets.show', $ticket)->with('error', $message);
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
