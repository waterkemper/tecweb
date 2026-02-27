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
        $filters = $request->only(['q', 'status', 'priority', 'category', 'severity', 'tag', 'from', 'to']);
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

        return view('tickets.index', [
            'tickets' => $tickets,
            'resolvedTickets' => $resolvedTickets,
            'filters' => $filters,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    private function buildTicketsQuery(Request $request, ?string $statusFilter, array $filters, string $sort, string $dir, array $allowedSort)
    {
        $query = ZdTicket::query()
            ->visibleToUser($request->user())
            ->with(['analysis' => fn ($q) => $q->latest()->limit(1), 'ticketOrder', 'requester', 'organization'])
            ->search($request->get('q'))
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
        $query = ZdTicket::query()
            ->visibleToUser($request->user())
            ->with(['analysis' => fn ($q) => $q->latest()->limit(1), 'requester', 'organization'])
            ->search($request->get('q'))
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

    public function show(ZdTicket $ticket): View
    {
        $this->authorize('view', $ticket);

        $ticket->load([
            'comments',
            'analysis' => fn ($q) => $q->latest()->limit(1),
            'analysisHistory' => fn ($q) => $q->limit(10),
            'requester',
            'submitter',
            'assignee',
            'organization',
        ]);
        $latestAnalysis = $ticket->analysis()->latest()->first();
        $similarTickets = AiSimilarTicket::where('ticket_id', $ticket->id)
            ->with('similarTicket')
            ->orderByDesc('score')
            ->limit(5)
            ->get();

        $similarTicketsAvgHours = $this->computeSimilarTicketsAvgHours($ticket);

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

        Bus::chain([
            new FetchTicketCommentsJob($ticket),
            new SummarizeTicketJob($ticket, refreshOnly: true),
        ])->dispatch();

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Fetching comments and AI refresh queued. Open questions and actions will update; effort and severity are preserved. Reload in a few seconds.');
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
                ->with('error', 'Could not load attachment.');
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

        $analysis = $ticket->analysis()->latest()->first();
        if (! $analysis) {
            return redirect()->route('tickets.show', $ticket)
                ->with('error', 'No AI analysis found.');
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
        $ids = $request->input('ticket_ids', []);
        if (! is_array($ids) || empty($ids)) {
            return $request->expectsJson()
                ? response()->json(['error' => 'Nenhum ticket para ordenar.'], 422)
                : redirect()->route('tickets.index')->with('error', 'Nenhum ticket para ordenar.');
        }

        $ticketIds = array_map('intval', array_values($ids));
        $validIds = ZdTicket::visibleToUser($request->user())->whereIn('id', $ticketIds)->pluck('id')->toArray();
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 100);
        $offset = ($page - 1) * $perPage;

        foreach ($ticketIds as $i => $ticketId) {
            $seq = $offset + $i;
            if (in_array($ticketId, $validIds)) {
                TicketOrder::updateOrCreate(
                    ['ticket_id' => $ticketId],
                    ['sequence' => $seq]
                );
            }
        }

        return $request->expectsJson()
            ? response()->json(['success' => true])
            : redirect()->route('tickets.index', array_merge(
                $request->only(['q', 'status', 'priority', 'category', 'severity', 'tag', 'from', 'to']),
                ['sort' => 'sequence', 'dir' => 'asc']
            ))->with('success', 'Ordem atualizada.');
    }

    public function applySuggestedTags(ZdTicket $ticket): RedirectResponse
    {
        $this->authorize('view', $ticket);

        $analysis = $ticket->analysis()->latest()->first();
        $suggested = $analysis?->suggested_tags ?? [];

        if (empty($suggested)) {
            return redirect()->route('tickets.show', $ticket)
                ->with('error', 'No suggested tags.');
        }

        $current = $ticket->tags ?? [];
        $merged = array_values(array_unique(array_merge($current, $suggested)));
        $ticket->update(['tags' => $merged]);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Tags applied.');
    }

    private function computeSimilarTicketsAvgHours(ZdTicket $ticket): ?float
    {
        $similarIds = AiSimilarTicket::where('ticket_id', $ticket->id)
            ->pluck('similar_ticket_id');

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
