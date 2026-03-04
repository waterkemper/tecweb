@extends('layouts.app')

@section('title', 'Tickets')

@section('content')
<div class="flex items-center justify-between gap-3 mb-4">
    <h1 class="text-2xl font-bold">Tickets</h1>
    @if (auth()->user()?->role === 'cliente')
        <a href="{{ route('tickets.create') }}" class="px-3 py-2 text-sm rounded-md bg-blue-600 text-white hover:bg-blue-700">Novo ticket</a>
    @endif
</div>
<p class="text-sm text-gray-600 mb-4">
    Arraste as linhas para ordenar os tickets (quais fazer primeiro). Use <a href="{{ route('tickets.index', array_merge($filters, ['sort' => 'sequence', 'dir' => 'asc'])) }}" class="text-blue-600 hover:underline">Ordenar por sequência</a> para ativar.
</p>

@php
    $sortUrl = fn ($col) => route('tickets.index', array_merge($filters, ['sort' => $col, 'dir' => ($sort ?? '') === $col && ($dir ?? 'desc') === 'asc' ? 'desc' : 'asc']));
    $sortIcon = fn ($col) => ($sort ?? '') === $col ? (($dir ?? 'desc') === 'asc' ? ' ↑' : ' ↓') : '';
    $statusLabels = ['new' => 'Novo', 'open' => 'Aberto', 'pending' => 'Pendente', 'hold' => 'Aguardando', 'solved' => 'Resolvido', 'closed' => 'Fechado'];
    $priorityLabels = ['urgent' => 'Urgente', 'high' => 'Alta', 'normal' => 'Normal', 'low' => 'Baixa'];
    $severityLabels = ['high' => 'Alta', 'medium' => 'Média', 'low' => 'Baixa'];
    $pendingLabels = ['our_side' => 'Nós', 'customer_side' => 'Cliente', 'can_close' => 'Fechar'];
    $ageLabels = ['too_old' => 'Muito antigo', 'old' => 'Antigo', 'recent' => 'recente', 'fresh' => 'Recente'];
@endphp
<form method="GET" class="mb-6 p-4 bg-slate-50 rounded-lg border border-slate-200">
    @if (!empty($sort))
        <input type="hidden" name="sort" value="{{ $sort }}">
    @endif
    @if (!empty($dir))
        <input type="hidden" name="dir" value="{{ $dir }}">
    @endif
    <div class="flex flex-wrap gap-x-5 gap-y-3 items-end">
        <div class="flex-shrink-0">
            <label class="block text-sm font-medium text-slate-600 mb-1">Buscar</label>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Assunto, descrição..." class="w-56 px-3 py-2 border border-slate-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>
        <div class="flex-shrink-0">
            <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
            <select name="status" class="w-36 px-3 py-2 border border-slate-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Todos</option>
                <option value="new" {{ ($filters['status'] ?? '') === 'new' ? 'selected' : '' }}>Novo</option>
                <option value="open" {{ ($filters['status'] ?? '') === 'open' ? 'selected' : '' }}>Aberto</option>
                <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pendente</option>
                <option value="hold" {{ ($filters['status'] ?? '') === 'hold' ? 'selected' : '' }}>Aguardando</option>
                <option value="solved" {{ ($filters['status'] ?? '') === 'solved' ? 'selected' : '' }}>Resolvido</option>
                <option value="closed" {{ ($filters['status'] ?? '') === 'closed' ? 'selected' : '' }}>Fechado</option>
            </select>
        </div>
        @if (auth()->user() && in_array(auth()->user()->role, ['admin', 'colaborador']))
        <div class="flex-shrink-0">
            <label class="block text-sm font-medium text-slate-600 mb-1">Organização</label>
            <select name="org" class="w-44 px-3 py-2 border border-slate-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Todas</option>
                @foreach ($organizations ?? [] as $org)
                    <option value="{{ $org->zd_id }}" {{ ($filters['org'] ?? '') == $org->zd_id ? 'selected' : '' }}>{{ $org->name }}</option>
                @endforeach
            </select>
        </div>
        @endif
        @if (auth()->user() && ($requesters ?? collect())->isNotEmpty())
        <div class="flex-shrink-0">
            <label class="block text-sm font-medium text-slate-600 mb-1">Solicitante</label>
            <select name="requester" class="w-44 px-3 py-2 border border-slate-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Todos</option>
                @foreach ($requesters ?? [] as $req)
                    <option value="{{ $req->zd_id }}" {{ ($filters['requester'] ?? '') == $req->zd_id ? 'selected' : '' }}>{{ $req->name ?? $req->email ?? "#{$req->zd_id}" }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="flex-shrink-0">
            <label class="block text-sm font-medium text-slate-600 mb-1">Prioridade</label>
            <select name="priority" class="w-32 px-3 py-2 border border-slate-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Todos</option>
                <option value="urgent" {{ ($filters['priority'] ?? '') === 'urgent' ? 'selected' : '' }}>Urgente</option>
                <option value="high" {{ ($filters['priority'] ?? '') === 'high' ? 'selected' : '' }}>Alta</option>
                <option value="normal" {{ ($filters['priority'] ?? '') === 'normal' ? 'selected' : '' }}>Normal</option>
                <option value="low" {{ ($filters['priority'] ?? '') === 'low' ? 'selected' : '' }}>Baixa</option>
            </select>
        </div>
        @if (auth()->user()?->zd_id)
        <div class="flex-shrink-0 flex items-end">
            <label class="inline-flex items-center gap-2 px-3 py-2 cursor-pointer">
                <input type="checkbox" name="mine" value="1" {{ ($filters['mine'] ?? '') ? 'checked' : '' }}
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="text-sm font-medium text-slate-600">Somente criados por mim</span>
            </label>
        </div>
        @endif
        <div class="flex-shrink-0 flex items-end gap-1">
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-0.5">De</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="w-[7.5rem] px-2 py-1.5 text-sm border border-slate-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-0.5">Até</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="w-[7.5rem] px-2 py-1.5 text-sm border border-slate-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors shadow-sm">Filtrar</button>
    </div>
</form>

@if ($tickets->isNotEmpty())
<h2 class="text-lg font-semibold mb-3">Tickets ativos</h2>
<table>
    <thead>
        <tr>
            <th class="w-12"></th>
            <th><a href="{{ $sortUrl('sequence') }}" class="text-blue-600 hover:underline">Ordem{{ $sortIcon('sequence') }}</a></th>
            <th><a href="{{ $sortUrl('zd_id') }}" class="text-blue-600 hover:underline">ID{{ $sortIcon('zd_id') }}</a></th>
            <th>Solicitante</th>
            <th>Organização</th>
            <th><a href="{{ $sortUrl('subject') }}" class="text-blue-600 hover:underline">Assunto{{ $sortIcon('subject') }}</a></th>
            <th><a href="{{ $sortUrl('status') }}" class="text-blue-600 hover:underline">Status{{ $sortIcon('status') }}</a></th>
            <th><a href="{{ $sortUrl('priority') }}" class="text-blue-600 hover:underline">Prioridade{{ $sortIcon('priority') }}</a></th>
            <th>Categoria</th>
            <th>Gravidade</th>
            <th>Pendente</th>
            <th><a href="{{ $sortUrl('zd_created_at') }}" class="text-blue-600 hover:underline">Criado{{ $sortIcon('zd_created_at') }}</a></th>
            <th><a href="{{ $sortUrl('zd_updated_at') }}" class="text-blue-600 hover:underline">Atualizado{{ $sortIcon('zd_updated_at') }}</a></th>
            <th>Idade</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($tickets as $ticket)
        @php
            $age = $ticket->age_status;
            $rowClass = in_array($age, ['old', 'too_old']) ? 'bg-amber-50' : '';
            $analysis = $ticket->analysis->first();
            $openQuestions = $analysis?->open_questions_list ?? [];
            if (empty($openQuestions) && $analysis?->open_questions) {
                $openQuestions = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $analysis->open_questions)));
            }
            $actionsNeeded = $analysis?->actions_needed_list ?? [];
            $summary = $analysis ? (is_array($analysis->bullets) && !empty($analysis->bullets)
                ? $analysis->bullets
                : [strip_tags($analysis->summary ?? '-')]
            ) : ['Sem análise IA'];
            $aiPreview = json_encode([
                'summary' => $summary,
                'open_questions' => array_values($openQuestions),
                'actions' => array_values($actionsNeeded),
            ]);
        @endphp
        <tr class="{{ $rowClass }} @if (auth()->user() && in_array(auth()->user()->role, ['admin', 'colaborador']) && ($sort ?? '') === 'sequence') sortable-row cursor-grab @endif ticket-row" data-ticket-id="{{ $ticket->id }}" data-ai-preview="{{ e($aiPreview) }}">
            <td class="text-gray-400">⋮⋮</td>
            <td class="text-sm text-gray-600">{{ $ticket->ticketOrder?->sequence !== null ? ($ticket->ticketOrder->sequence + 1) : '-' }}</td>
            <td><a href="{{ route('tickets.show', $ticket) }}" class="text-blue-600 hover:underline">#{{ $ticket->zd_id }}</a></td>
            <td class="text-sm">{{ $ticket->requester?->name ?? $ticket->requester?->email ?? '-' }}</td>
            <td class="text-sm">{{ $ticket->organization?->name ?? '-' }}</td>
            <td>{{ Str::limit($ticket->subject, 60) }}</td>
            <td><span class="badge badge-{{ $ticket->status }}">{{ $statusLabels[$ticket->status] ?? $ticket->status }}</span></td>
            <td>{{ $priorityLabels[$ticket->priority] ?? $ticket->priority ?? '-' }}</td>
            <td>{{ $ticket->analysis->first()?->category ?? '-' }}</td>
            <td>
                @if ($ticket->analysis->first()?->severity)
                    <span class="badge badge-{{ $ticket->analysis->first()->severity }}">{{ $severityLabels[$ticket->analysis->first()->severity] ?? $ticket->analysis->first()->severity }}</span>
                @else
                    -
                @endif
            </td>
            <td>
                @php $pa = $ticket->analysis->first()?->pending_action; @endphp
                @if ($pa === 'our_side')
                    <span class="text-xs px-1.5 py-0.5 rounded bg-amber-100 text-amber-800" title="Nossa vez">Nós</span>
                @elseif ($pa === 'customer_side')
                    <span class="text-xs px-1.5 py-0.5 rounded bg-blue-100 text-blue-800" title="Cliente">Cliente</span>
                @elseif ($pa === 'can_close')
                    <span class="text-xs px-1.5 py-0.5 rounded bg-green-100 text-green-800" title="Pode fechar">Fechar</span>
                @else
                    <span class="text-xs text-gray-400">-</span>
                @endif
            </td>
            <td class="text-sm">{{ ($ticket->zd_created_at ?? $ticket->created_at)?->format('d/m/y H:i') ?? '-' }}</td>
            <td class="text-sm">{{ ($ticket->zd_updated_at ?? $ticket->updated_at)?->format('d/m/y H:i') ?? '-' }}</td>
            <td>
                @if (!in_array($ticket->status ?? '', ['solved', 'closed']))
                    @if ($age === 'too_old')
                        <span class="badge" style="background:#fee2e2;color:#991b1b;" title="Muito antigo - {{ $ticket->days_since_update }} dias desde atualização">Muito antigo</span>
                    @elseif ($age === 'old')
                        <span class="badge" style="background:#fef3c7;color:#92400e;" title="{{ $ticket->days_since_update }} dias desde atualização">Antigo</span>
                    @elseif ($age === 'recent')
                        <span class="text-xs text-gray-600">{{ $ticket->days_since_update }}d</span>
                    @elseif ($age === 'fresh')
                        <span class="text-xs text-green-600">Recente</span>
                    @else
                        -
                    @endif
                @else
                    <span class="text-xs text-gray-400">-</span>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="mt-4">
    {{ $tickets->links() }}
</div>
@else
<p class="text-gray-500 py-4">Nenhum ticket ativo.</p>
@endif

@if ($resolvedTickets)
<div class="mt-10 pt-8 border-t border-gray-200">
    <h2 class="text-lg font-semibold mb-3 text-gray-600">Resolvidos / Fechados</h2>
    <table class="opacity-90">
        <thead>
            <tr>
                <th>ID</th>
                <th>Solicitante</th>
                <th>Organização</th>
                <th>Assunto</th>
                <th>Status</th>
                <th>Prioridade</th>
                <th>Categoria</th>
                <th>Criado (ZD)</th>
                <th>Atualizado (ZD)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($resolvedTickets as $ticket)
            @php
                $resolvedAnalysis = $ticket->analysis->first();
                $resolvedOpenQ = $resolvedAnalysis?->open_questions_list ?? [];
                if (empty($resolvedOpenQ) && $resolvedAnalysis?->open_questions) {
                    $resolvedOpenQ = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $resolvedAnalysis->open_questions)));
                }
                $resolvedActions = $resolvedAnalysis?->actions_needed_list ?? [];
                $resolvedSummary = $resolvedAnalysis ? (is_array($resolvedAnalysis->bullets) && !empty($resolvedAnalysis->bullets)
                    ? $resolvedAnalysis->bullets
                    : [strip_tags($resolvedAnalysis->summary ?? '-')]
                ) : ['Sem análise IA'];
                $resolvedAiPreview = json_encode([
                    'summary' => $resolvedSummary,
                    'open_questions' => array_values($resolvedOpenQ),
                    'actions' => array_values($resolvedActions),
                ]);
            @endphp
            <tr class="bg-gray-50 text-gray-600 ticket-row" data-ai-preview="{{ e($resolvedAiPreview) }}">
                <td><a href="{{ route('tickets.show', $ticket) }}" class="text-blue-600 hover:underline">#{{ $ticket->zd_id }}</a></td>
                <td class="text-sm">{{ $ticket->requester?->name ?? $ticket->requester?->email ?? '-' }}</td>
                <td class="text-sm">{{ $ticket->organization?->name ?? '-' }}</td>
                <td>{{ Str::limit($ticket->subject, 60) }}</td>
                <td><span class="badge badge-{{ $ticket->status }}">{{ $statusLabels[$ticket->status] ?? $ticket->status }}</span></td>
                <td>{{ $priorityLabels[$ticket->priority] ?? $ticket->priority ?? '-' }}</td>
                <td>{{ $ticket->analysis->first()?->category ?? '-' }}</td>
                <td class="text-sm">{{ ($ticket->zd_created_at ?? $ticket->created_at)?->format('d/m/y H:i') ?? '-' }}</td>
                <td class="text-sm">{{ ($ticket->zd_updated_at ?? $ticket->updated_at)?->format('d/m/y H:i') ?? '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center text-gray-500 py-4">Nenhum ticket resolvido/fechado.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">
        {{ $resolvedTickets->links() }}
    </div>
</div>
@endif

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tooltip = document.getElementById('ai-preview-tooltip');
    if (!tooltip) return;
    const rows = document.querySelectorAll('.ticket-row[data-ai-preview]');
    let hideTimeout, showTimeout;

    function esc(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    function showTooltip(evt, content) {
        clearTimeout(hideTimeout);
        clearTimeout(showTimeout);
        showTimeout = setTimeout(function() {
            tooltip.innerHTML = content;
            tooltip.style.display = 'block';
            tooltip.style.visibility = 'visible';
            tooltip.style.opacity = '1';
            positionTooltip(evt);
        }, 1000);
    }

    function positionTooltip(evt) {
        const rect = tooltip.getBoundingClientRect();
        let x = evt.clientX + 15;
        let y = evt.clientY + 15;
        if (x + rect.width > window.innerWidth) x = evt.clientX - rect.width - 15;
        if (y + rect.height > window.innerHeight) y = evt.clientY - rect.height - 15;
        if (x < 10) x = 10;
        if (y < 10) y = 10;
        tooltip.style.left = x + 'px';
        tooltip.style.top = y + 'px';
    }

    function hideTooltip() {
        clearTimeout(showTimeout);
        hideTimeout = setTimeout(function() {
            tooltip.style.display = 'none';
            tooltip.style.visibility = '';
            tooltip.style.opacity = '';
        }, 100);
    }

    function decodeHtmlEntities(str) {
        return String(str)
            .replace(/&quot;/g, '"')
            .replace(/&#039;/g, "'")
            .replace(/&amp;/g, '&')
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>');
    }

    rows.forEach(function(row) {
        row.addEventListener('mouseenter', function(evt) {
            try {
                let raw = this.getAttribute('data-ai-preview') || '{}';
                raw = decodeHtmlEntities(raw);
                const data = JSON.parse(raw);
                let html = '';
                if (data.summary && data.summary.length) {
                    html += '<div style="margin-bottom:0.75rem"><div style="font-weight:600;color:#374151;margin-bottom:0.25rem">Resumo</div><ul style="list-style:disc;padding-left:1.25rem;margin:0;color:#4b5563;line-height:1.4">';
                    data.summary.forEach(function(s) { html += '<li>' + esc(s) + '</li>'; });
                    html += '</ul></div>';
                }
                if (data.open_questions && data.open_questions.length) {
                    html += '<div style="margin-bottom:0.75rem;padding:0.5rem;background:#fffbeb;border:1px solid #fde68a;border-radius:0.25rem"><div style="font-weight:600;color:#92400e;font-size:0.75rem;margin-bottom:0.25rem">Perguntas abertas</div><ul style="margin:0;padding-left:1rem;color:#92400e;font-size:0.75rem;line-height:1.4">';
                    data.open_questions.forEach(function(q) { html += '<li>• ' + esc(q) + '</li>'; });
                    html += '</ul></div>';
                }
                if (data.actions && data.actions.length) {
                    html += '<div style="padding:0.5rem;background:#eff6ff;border:1px solid #bfdbfe;border-radius:0.25rem"><div style="font-weight:600;color:#1e40af;font-size:0.75rem;margin-bottom:0.25rem">Ações necessárias</div><ul style="margin:0;padding-left:1rem;color:#1e40af;font-size:0.75rem;line-height:1.4">';
                    data.actions.forEach(function(a) { html += '<li>→ ' + esc(a) + '</li>'; });
                    html += '</ul></div>';
                }
                if (!html) html = '<p style="color:#6b7280;font-size:0.75rem">Sem análise IA</p>';
                showTooltip(evt, html);
            } catch (e) {}
        });
        row.addEventListener('mousemove', function(evt) {
            if (tooltip.style.display === 'block') positionTooltip(evt);
        });
        row.addEventListener('mouseleave', hideTooltip);
    });
});
</script>

@if ($tickets->isNotEmpty() && ($sort ?? '') === 'sequence' && auth()->user() && in_array(auth()->user()->role, ['admin', 'colaborador']))
<meta name="reorder-url" content="{{ route('tickets.reorder') }}">
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tbody = document.querySelector('table tbody');
    if (!tbody) return;
    const reorderUrl = document.querySelector('meta[name="reorder-url"]')?.content || '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    new Sortable(tbody, {
        animation: 150,
        filter: 'a, input, select, button',
        preventOnFilter: true,
        onEnd: function() {
            const rows = tbody.querySelectorAll('tr.sortable-row');
            const ids = Array.from(rows).map(r => r.dataset.ticketId);
            fetch(reorderUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ ticket_ids: ids })
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
    });
});
</script>
@endif
@endsection
