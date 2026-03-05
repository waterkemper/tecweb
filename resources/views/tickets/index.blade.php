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
    $isCliente = auth()->user()?->role === 'cliente';
@endphp
<div class="mb-6 rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden" id="filters-panel">
    <form method="GET" id="tickets-filters-form">
        @if (!empty($sort))
            <input type="hidden" name="sort" value="{{ $sort }}">
        @endif
        @if (!empty($dir))
            <input type="hidden" name="dir" value="{{ $dir }}">
        @endif
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 bg-slate-50 border-b border-slate-200">
            <div class="flex items-center gap-3 min-h-[44px]">
                <span class="font-medium text-slate-700">Filtros</span>
                <button type="button" id="filters-toggle" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-200 rounded-md transition-colors min-h-[44px]" aria-expanded="true" aria-controls="filters-panel-body">
                    <span id="filters-toggle-text">Ocultar filtros</span>
                    <svg id="filters-chevron" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
            </div>
            <button type="submit" class="px-4 py-2 min-h-[44px] bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors shadow-sm">Filtrar</button>
        </div>
        <div id="filters-panel-body" class="p-4 bg-white overflow-hidden transition-[max-height] duration-300 ease-in-out" style="max-height: 1200px;">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4 mb-4">
                <div class="min-w-0 sm:col-span-2 xl:col-span-1">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Buscar</label>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Assunto, descrição..." class="w-full min-w-0 px-3 py-2 border border-slate-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="min-w-0">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                    <select name="status" class="w-full min-w-0 px-3 py-2 border border-slate-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
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
                <div class="min-w-0">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Organização</label>
                    <select name="org" class="w-full min-w-0 px-3 py-2 border border-slate-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todas</option>
                        @foreach ($organizations ?? [] as $org)
                            <option value="{{ $org->zd_id }}" {{ ($filters['org'] ?? '') == $org->zd_id ? 'selected' : '' }}>{{ $org->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                @if (auth()->user() && ($requesters ?? collect())->isNotEmpty())
                <div class="min-w-0">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Solicitante</label>
                    <select name="requester" class="w-full min-w-0 px-3 py-2 border border-slate-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todos</option>
                        @foreach ($requesters ?? [] as $req)
                            <option value="{{ $req->zd_id }}" {{ ($filters['requester'] ?? '') == $req->zd_id ? 'selected' : '' }}>{{ $req->name ?? $req->email ?? "#{$req->zd_id}" }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="min-w-0">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Prioridade</label>
                    <select name="priority" class="w-full min-w-0 px-3 py-2 border border-slate-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todos</option>
                        <option value="urgent" {{ ($filters['priority'] ?? '') === 'urgent' ? 'selected' : '' }}>Urgente</option>
                        <option value="high" {{ ($filters['priority'] ?? '') === 'high' ? 'selected' : '' }}>Alta</option>
                        <option value="normal" {{ ($filters['priority'] ?? '') === 'normal' ? 'selected' : '' }}>Normal</option>
                        <option value="low" {{ ($filters['priority'] ?? '') === 'low' ? 'selected' : '' }}>Baixa</option>
                    </select>
                </div>
            </div>
            <div class="flex flex-wrap gap-x-6 gap-y-3 items-center mb-4">
                @if (auth()->user()?->zd_id)
                <label class="inline-flex items-center gap-2 min-h-[44px] px-3 py-2 cursor-pointer rounded-md hover:bg-slate-50">
                    <input type="checkbox" name="mine" value="1" {{ ($filters['mine'] ?? '') ? 'checked' : '' }}
                        class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="text-sm font-medium text-slate-600">Somente criados por mim</span>
                </label>
                @endif
                @if (auth()->user() && in_array(auth()->user()->role, ['admin', 'colaborador']))
                <label class="inline-flex items-center gap-2 min-h-[44px] px-3 py-2 cursor-pointer rounded-md hover:bg-slate-50">
                    <input type="checkbox" name="overdue" value="1" {{ ($filters['overdue'] ?? '') ? 'checked' : '' }}
                        class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="text-sm font-medium text-slate-600">Atrasados</span>
                </label>
                <label class="inline-flex items-center gap-2 min-h-[44px] px-3 py-2 cursor-pointer rounded-md hover:bg-slate-50">
                    <input type="checkbox" name="without_deadline" value="1" {{ ($filters['without_deadline'] ?? '') ? 'checked' : '' }}
                        class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="text-sm font-medium text-slate-600">Sem prazo</span>
                </label>
                @endif
            </div>
            <div class="flex flex-wrap gap-4 items-end">
                @if (auth()->user() && in_array(auth()->user()->role, ['admin', 'colaborador']))
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-0.5">Prazo de</label>
                    <input type="date" name="due_from" value="{{ $filters['due_from'] ?? '' }}" class="min-w-[7.5rem] px-2 py-2 text-sm border border-slate-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-0.5">Prazo até</label>
                    <input type="date" name="due_to" value="{{ $filters['due_to'] ?? '' }}" class="min-w-[7.5rem] px-2 py-2 text-sm border border-slate-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                @endif
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-0.5">De</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="min-w-[7.5rem] px-2 py-2 text-sm border border-slate-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-0.5">Até</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="min-w-[7.5rem] px-2 py-2 text-sm border border-slate-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
        </div>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var STORAGE_KEY = 'ticketsFiltersVisible';
    var panel = document.getElementById('filters-panel-body');
    var toggle = document.getElementById('filters-toggle');
    var toggleText = document.getElementById('filters-toggle-text');
    var chevron = document.getElementById('filters-chevron');
    if (!panel || !toggle) return;

    function isMobile() { return window.innerWidth < 768; }
    function getStored() {
        var stored = localStorage.getItem(STORAGE_KEY);
        if (stored !== null) return stored === 'true';
        return !isMobile();
    }
    function setStored(val) { localStorage.setItem(STORAGE_KEY, val ? 'true' : 'false'); }

    function applyState(visible) {
        panel.style.maxHeight = visible ? '1200px' : '0';
        panel.style.overflow = 'hidden';
        panel.style.padding = visible ? '' : '0';
        toggle.setAttribute('aria-expanded', visible ? 'true' : 'false');
        toggleText.textContent = visible ? 'Ocultar filtros' : 'Mostrar filtros';
        chevron.style.transform = visible ? 'rotate(0deg)' : 'rotate(-90deg)';
    }

    var visible = getStored();
    applyState(visible);

    toggle.addEventListener('click', function() {
        visible = !visible;
        setStored(visible);
        applyState(visible);
    });
});
</script>

@if ($tickets->isNotEmpty())
<h2 class="text-lg font-semibold mb-3">Tickets ativos</h2>
@php $showOrderBadge = ($sort ?? '') === 'sequence' && auth()->user(); @endphp
@if ($showOrderBadge)
<div class="mb-3 px-3 py-2 bg-blue-100 text-blue-800 rounded-md text-sm font-medium">
    Modo ordenação: arraste as linhas ou use os botões ↑↓
</div>
@endif
<div class="overflow-x-auto -mx-4 sm:mx-0 px-4 sm:px-0">
<table class="min-w-[900px] w-full">
    <thead>
        <tr>
            <th class="w-8"></th>
            <th class="min-w-[6.5rem]"><a href="{{ $sortUrl('sequence') }}" class="text-blue-600 hover:underline">Ordem{{ $sortIcon('sequence') }}</a></th>
            <th><a href="{{ $sortUrl('zd_id') }}" class="text-blue-600 hover:underline">ID{{ $sortIcon('zd_id') }}</a></th>
            <th>Solicitante</th>
            <th>Organização</th>
            <th><a href="{{ $sortUrl('subject') }}" class="text-blue-600 hover:underline">Assunto{{ $sortIcon('subject') }}</a></th>
            <th><a href="{{ $sortUrl('status') }}" class="text-blue-600 hover:underline">Status{{ $sortIcon('status') }}</a></th>
            @if (!$isCliente)
            <th>Categoria</th>
            @endif
            <th>Gravidade</th>
            @if (!$isCliente)
            <th>Pendente</th>
            @endif
            <th><a href="{{ $sortUrl('zd_created_at') }}" class="text-blue-600 hover:underline">Criado{{ $sortIcon('zd_created_at') }}</a></th>
            <th><a href="{{ $sortUrl('zd_updated_at') }}" class="text-blue-600 hover:underline">Atualizado{{ $sortIcon('zd_updated_at') }}</a></th>
            <th><a href="{{ $sortUrl('due_at') }}" class="text-blue-600 hover:underline">Prazo{{ $sortIcon('due_at') }}</a></th>
            <th>Horas (máx)</th>
            <th>Idade</th>
        </tr>
    </thead>
    <tbody>
        @php $totalHours = 0; @endphp
        @foreach ($tickets as $ticket)
        @php
            $age = $ticket->age_status;
            $rowClass = in_array($age, ['old', 'too_old']) ? 'bg-amber-50' : '';
            $analysis = $ticket->analysis->first();
            $hoursMax = $analysis ? (float) ($analysis->internal_effort_max ?? $analysis->internal_effort_min ?? 0) : 0;
            if ($hoursMax > 0) { $totalHours += $hoursMax; }
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
        @php
            $reqId = $ticket->requester_id ?? 0;
            $items = $tickets->items();
            $prevReq = $loop->index > 0 ? ($items[$loop->index - 1]->requester_id ?? 0) : null;
            $nextReq = isset($items[$loop->index + 1]) ? ($items[$loop->index + 1]->requester_id ?? 0) : null;
            $isFirstInRequester = $prevReq === null || $prevReq !== $reqId;
            $isLastInRequester = $nextReq === null || $nextReq !== $reqId;
            $showOrderBtns = auth()->user() && ($sort ?? '') === 'sequence';
        @endphp
        <tr class="{{ $rowClass }} @if ($showOrderBtns) sortable-row cursor-grab @endif ticket-row" data-ticket-id="{{ $ticket->id }}" data-requester-id="{{ $reqId }}" data-ai-preview="{{ e($aiPreview) }}">
            <td class="w-8 text-gray-400" title="{{ $showOrderBtns ? 'Arraste para reordenar' : '' }}">⋮⋮</td>
            <td class="min-w-[6.5rem] text-sm text-gray-600 {{ $showOrderBtns ? 'bg-blue-50' : '' }}">
                <span class="inline-flex items-center gap-1.5 flex-wrap">
                    @if ($showOrderBtns && $ticket->ticketOrder?->sequence !== null)
                    <span class="editable-sequence cursor-pointer" title="Duplo clique para alterar posição">{{ $ticket->ticketOrder->sequence + 1 }}</span>
                    @else
                    <span>{{ $ticket->ticketOrder?->sequence !== null ? ($ticket->ticketOrder->sequence + 1) : '-' }}</span>
                    @endif
                    @if ($showOrderBtns)
                    <span class="inline-flex gap-0.5" role="group">
                        @if (!$isFirstInRequester)
                        <button type="button" class="order-btn order-topo p-1.5 rounded border border-slate-300 bg-white text-slate-600 hover:bg-slate-50 hover:border-slate-400 transition-colors" title="Mover para topo" data-action="topo"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg></button>
                        @endif
                        @if (!$isLastInRequester)
                        <button type="button" class="order-btn order-fundo p-1.5 rounded border border-slate-300 bg-white text-slate-600 hover:bg-slate-50 hover:border-slate-400 transition-colors" title="Mover para fim da lista" data-action="fundo"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button>
                        @endif
                    </span>
                    @endif
                </span>
            </td>
            <td><a href="{{ route('tickets.show', $ticket) }}" class="text-blue-600 hover:underline">#{{ $ticket->zd_id }}</a></td>
            <td class="text-sm">{{ $ticket->requester?->name ?? $ticket->requester?->email ?? '-' }}</td>
            <td class="text-sm">{{ $ticket->organization?->name ?? '-' }}</td>
            <td>{{ Str::limit($ticket->subject, 60) }}</td>
            <td><span class="badge badge-{{ $ticket->status }}">{{ $statusLabels[$ticket->status] ?? $ticket->status }}</span></td>
            @if (!$isCliente)
            <td>{{ $ticket->analysis->first()?->category ?? '-' }}</td>
            @endif
            <td>
                @if ($ticket->analysis->first()?->severity)
                    <span class="badge badge-{{ $ticket->analysis->first()->severity }}">{{ $severityLabels[$ticket->analysis->first()->severity] ?? $ticket->analysis->first()->severity }}</span>
                @else
                    -
                @endif
            </td>
            @if (!$isCliente)
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
            @endif
            <td class="text-sm">{{ ($ticket->zd_created_at ?? $ticket->created_at)?->format('d/m/y H:i') ?? '-' }}</td>
            <td class="text-sm">{{ ($ticket->zd_updated_at ?? $ticket->updated_at)?->format('d/m/y H:i') ?? '-' }}</td>
            <td class="text-sm">
                @if (!$isCliente)
                <span class="editable-deadline cursor-pointer" title="Duplo clique para editar"
                    data-ticket-id="{{ $ticket->id }}"
                    data-due-at="{{ $ticket->due_at?->format('Y-m-d') ?? '' }}"
                    data-min-date="{{ ($ticket->zd_created_at ?? $ticket->created_at)?->format('Y-m-d') ?? now()->format('Y-m-d') }}"
                    data-url="{{ route('tickets.deadline.update', $ticket) }}">
                    @if ($ticket->due_at)
                        @if ($ticket->is_overdue)
                            <span class="badge" style="background:#fee2e2;color:#991b1b;" title="{{ $ticket->days_overdue }} dias de atraso">{{ $ticket->due_at->format('d/m/y') }} (atrasado)</span>
                        @elseif ($ticket->days_overdue !== null && $ticket->days_overdue >= -2 && $ticket->days_overdue < 0)
                            <span class="badge" style="background:#fef3c7;color:#92400e;" title="Faltam {{ abs($ticket->days_overdue) }} dias">{{ $ticket->due_at->format('d/m/y') }}</span>
                        @else
                            {{ $ticket->due_at->format('d/m/y') }}
                        @endif
                    @else
                        —
                    @endif
                </span>
                @else
                    @if ($ticket->due_at)
                        @if ($ticket->is_overdue)
                            <span class="badge" style="background:#fee2e2;color:#991b1b;" title="{{ $ticket->days_overdue }} dias de atraso">{{ $ticket->due_at->format('d/m/y') }} (atrasado)</span>
                        @elseif ($ticket->days_overdue !== null && $ticket->days_overdue >= -2 && $ticket->days_overdue < 0)
                            <span class="badge" style="background:#fef3c7;color:#92400e;" title="Faltam {{ abs($ticket->days_overdue) }} dias">{{ $ticket->due_at->format('d/m/y') }}</span>
                        @else
                            {{ $ticket->due_at->format('d/m/y') }}
                        @endif
                    @else
                        —
                    @endif
                @endif
            </td>
            <td class="text-sm text-right">
                @if (!$isCliente && $analysis)
                <span class="editable-hours cursor-pointer" title="Duplo clique para editar"
                    data-ticket-id="{{ $ticket->id }}"
                    data-hours-max="{{ $hoursMax > 0 ? number_format($hoursMax, 1, '.', '') : '' }}"
                    data-hours-min="{{ $analysis->internal_effort_min !== null ? number_format((float)$analysis->internal_effort_min, 1, '.', '') : '' }}"
                    data-url="{{ route('tickets.internal-effort', $ticket) }}">
                    @if ($hoursMax > 0)
                        {{ number_format($hoursMax, 1) }}h
                    @else
                        —
                    @endif
                </span>
                @else
                    @if ($hoursMax > 0)
                        {{ number_format($hoursMax, 1) }}h
                    @else
                        —
                    @endif
                @endif
            </td>
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
</div>
<div class="mt-2 py-2 px-3 bg-slate-100 rounded-b text-sm font-medium text-slate-700" id="total-hours-footer">
    Total previsão (página atual): {{ number_format($totalHours, 1) }}h
</div>

<div class="mt-4">
    {{ $tickets->links() }}
</div>
@else
<p class="text-gray-500 py-4">Nenhum ticket ativo.</p>
@endif

@if ($resolvedTickets)
<div class="mt-10 pt-8 border-t border-gray-200">
    <h2 class="text-lg font-semibold mb-3 text-gray-600">Resolvidos / Fechados</h2>
    <div class="overflow-x-auto -mx-4 sm:mx-0 px-4 sm:px-0">
    <table class="opacity-90 min-w-[700px] w-full">
        <thead>
            <tr>
                <th>ID</th>
                <th>Solicitante</th>
                <th>Organização</th>
                <th>Assunto</th>
                <th>Status</th>
                @if (!$isCliente)
                <th>Categoria</th>
                @endif
                <th>Criado (ZD)</th>
                <th>Atualizado (ZD)</th>
                <th>Prazo</th>
                <th>Horas (máx)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($resolvedTickets as $ticket)
            @php
                $resolvedAnalysis = $ticket->analysis->first();
                $resolvedHoursMax = $resolvedAnalysis ? (float) ($resolvedAnalysis->internal_effort_max ?? $resolvedAnalysis->internal_effort_min ?? 0) : 0;
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
                @if (!$isCliente)
                <td>{{ $ticket->analysis->first()?->category ?? '-' }}</td>
                @endif
                <td class="text-sm">{{ ($ticket->zd_created_at ?? $ticket->created_at)?->format('d/m/y H:i') ?? '-' }}</td>
                <td class="text-sm">{{ ($ticket->zd_updated_at ?? $ticket->updated_at)?->format('d/m/y H:i') ?? '-' }}</td>
                <td class="text-sm">{{ $ticket->due_at?->format('d/m/y') ?? '—' }}</td>
                <td class="text-sm text-right">
                    @if ($resolvedHoursMax > 0)
                        {{ number_format($resolvedHoursMax, 1) }}h
                    @else
                        —
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="{{ $isCliente ? 9 : 10 }}" class="text-center text-gray-500 py-4">Nenhum ticket resolvido/fechado.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    </div>

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
        if (window.inlineEditActive) return;
        clearTimeout(hideTimeout);
        clearTimeout(showTimeout);
        showTimeout = setTimeout(function() {
            if (window.inlineEditActive) return;
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
    window.hideAiPreviewTooltip = function() {
        clearTimeout(showTimeout);
        clearTimeout(hideTimeout);
        tooltip.style.display = 'none';
        tooltip.style.visibility = '';
        tooltip.style.opacity = '';
    };

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
            if (window.inlineEditActive) return;
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

@if ($tickets->isNotEmpty() && !$isCliente)
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function formatDateBr(ymd) {
        if (!ymd) return '—';
        const p = ymd.split('-');
        return p[2] + '/' + p[1] + '/' + p[0].slice(-2);
    }

    function getDaysOverdue(ymd) {
        if (!ymd) return null;
        const d = new Date(ymd + 'T12:00:00');
        const now = new Date();
        now.setHours(12, 0, 0, 0);
        return Math.round((now - d) / 86400000);
    }

    function renderDeadlineDisplay(ymd) {
        if (!ymd) return '—';
        const days = getDaysOverdue(ymd);
        const fmt = formatDateBr(ymd);
        if (days > 0) return '<span class="badge" style="background:#fee2e2;color:#991b1b;" title="' + days + ' dias de atraso">' + fmt + ' (atrasado)</span>';
        if (days >= -2 && days <= 0) return '<span class="badge" style="background:#fef3c7;color:#92400e;" title="Faltam ' + Math.abs(days) + ' dias">' + fmt + '</span>';
        return fmt;
    }

    function updateTotalHours() {
        const footer = document.getElementById('total-hours-footer');
        if (!footer) return;
        let total = 0;
        document.querySelectorAll('.editable-hours').forEach(function(el) {
            const v = parseFloat(el.getAttribute('data-hours-max') || 0);
            if (!isNaN(v)) total += v;
        });
        footer.textContent = 'Total previsão (página atual): ' + total.toFixed(1) + 'h';
    }

    document.querySelectorAll('.editable-deadline').forEach(function(span) {
        span.addEventListener('dblclick', function(evt) {
            if (evt.target.tagName === 'INPUT') return;
            evt.preventDefault();
            evt.stopPropagation();
            const ticketId = this.getAttribute('data-ticket-id');
            const dueAt = this.getAttribute('data-due-at') || '';
            const minDate = this.getAttribute('data-min-date') || '';
            const url = this.getAttribute('data-url');
            const oldHtml = this.innerHTML;

            const input = document.createElement('input');
            input.type = 'date';
            input.value = dueAt;
            input.min = minDate;
            input.className = 'w-28 px-2 py-1 text-sm border border-slate-300 rounded';
            input.style.fontSize = 'inherit';

            this.innerHTML = '';
            this.appendChild(input);
            input.focus();
            window.inlineEditActive = true;
            if (window.hideAiPreviewTooltip) window.hideAiPreviewTooltip();

            function restore() {
                span.innerHTML = oldHtml;
                span.addEventListener('click', arguments.callee);
            }

            function save() {
                const val = input.value.trim();
                const formData = new FormData();
                formData.append('_token', csrfToken);
                if (val) formData.append('due_at', val);
                else formData.append('clear', '1');

                fetch(url, {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function(r) { return r.json();                 }).then(function(data) {
                    window.inlineEditActive = false;
                    if (data.success) {
                        span.setAttribute('data-due-at', val);
                        span.innerHTML = renderDeadlineDisplay(val || null);
                    } else {
                        span.innerHTML = oldHtml;
                        if (data.message) alert(data.message);
                    }
                }).catch(function() {
                    window.inlineEditActive = false;
                    span.innerHTML = oldHtml;
                });
            }

            input.addEventListener('blur', function() { setTimeout(save, 10); });
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') { input.blur(); }
                if (e.key === 'Escape') { window.inlineEditActive = false; span.innerHTML = oldHtml; }
            });
        });
    });

    document.querySelectorAll('.editable-hours').forEach(function(span) {
        span.addEventListener('dblclick', function(evt) {
            if (evt.target.tagName === 'INPUT') return;
            evt.preventDefault();
            evt.stopPropagation();
            const ticketId = this.getAttribute('data-ticket-id');
            const hoursMax = this.getAttribute('data-hours-max') || '';
            const hoursMin = this.getAttribute('data-hours-min') || '';
            const url = this.getAttribute('data-url');
            const oldHtml = this.innerHTML;

            const input = document.createElement('input');
            input.type = 'number';
            input.step = '0.5';
            input.min = '0';
            input.value = hoursMax;
            input.placeholder = '—';
            input.className = 'w-16 px-2 py-1 text-sm text-right border border-slate-300 rounded';
            input.style.fontSize = 'inherit';

            this.innerHTML = '';
            this.appendChild(input);
            input.focus();
            window.inlineEditActive = true;
            if (window.hideAiPreviewTooltip) window.hideAiPreviewTooltip();

            function save() {
                const val = input.value.trim();
                const formData = new FormData();
                formData.append('_token', csrfToken);
                formData.append('internal_effort_min', hoursMin);
                formData.append('internal_effort_max', val || '');

                fetch(url, {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function(r) { return r.json(); }).then(function(data) {
                    window.inlineEditActive = false;
                    if (data.success) {
                        const num = val ? parseFloat(val) : 0;
                        span.setAttribute('data-hours-max', val);
                        span.textContent = num > 0 ? num.toFixed(1) + 'h' : '—';
                        updateTotalHours();
                    } else {
                        span.innerHTML = oldHtml;
                        if (data.message) alert(data.message);
                    }
                }).catch(function() {
                    window.inlineEditActive = false;
                    span.innerHTML = oldHtml;
                });
            }

            input.addEventListener('blur', function() { setTimeout(save, 10); });
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') { input.blur(); }
                if (e.key === 'Escape') { window.inlineEditActive = false; span.innerHTML = oldHtml; }
            });
        });
    });
});
</script>
@endif

@if ($tickets->isNotEmpty() && ($sort ?? '') === 'sequence' && auth()->user())
<meta name="reorder-url" content="{{ route('tickets.reorder') }}">
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tbody = document.querySelector('table tbody');
    if (!tbody) return;
    const reorderUrl = document.querySelector('meta[name="reorder-url"]')?.content || '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function getRows() {
        return Array.from(tbody.querySelectorAll('tr.sortable-row'));
    }

    function getTicketIds() {
        return getRows().map(r => r.dataset.ticketId);
    }

    function updateSequenceNumbers() {
        const rows = getRows();
        const seqByRequester = {};
        rows.forEach(function(row) {
            const reqId = row.dataset.requesterId || '0';
            seqByRequester[reqId] = (seqByRequester[reqId] || 0) + 1;
            const ordemCell = row.querySelector('td:nth-child(2)');
            if (ordemCell) {
                const numberSpan = ordemCell.querySelector('.editable-sequence') || ordemCell.querySelector('span.inline-flex > span:first-child');
                if (numberSpan) numberSpan.textContent = seqByRequester[reqId];
            }
        });
    }

    function sendReorder(ids) {
        fetch(reorderUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ ticket_ids: ids })
        }).then(r => r.json()).then(function(data) {
            if (data.success) {
                ids.forEach(function(id) {
                    const row = tbody.querySelector('tr.sortable-row[data-ticket-id="' + id + '"]');
                    if (row) tbody.appendChild(row);
                });
                updateSequenceNumbers();
            }
        });
    }

    tbody.addEventListener('click', function(evt) {
        const btn = evt.target.closest('.order-btn');
        if (!btn) return;
        evt.preventDefault();
        evt.stopPropagation();
        const row = btn.closest('tr');
        const action = btn.dataset.action;
        const rows = getRows();
        const requesterId = row.dataset.requesterId || '0';
        const sameRequester = rows.filter(function(r) { return (r.dataset.requesterId || '0') === requesterId; });
        const posInGroup = sameRequester.indexOf(row);

        let newIds;
        if (action === 'topo') {
            const others = sameRequester.filter(function(_, i) { return i !== posInGroup; });
            const reordered = [row].concat(others);
            const before = rows.slice(0, rows.indexOf(sameRequester[0]));
            const after = rows.slice(rows.indexOf(sameRequester[sameRequester.length - 1]) + 1);
            newIds = before.map(function(r) { return r.dataset.ticketId; }).concat(reordered.map(function(r) { return r.dataset.ticketId; }), after.map(function(r) { return r.dataset.ticketId; }));
        } else if (action === 'fundo') {
            const others = sameRequester.filter(function(_, i) { return i !== posInGroup; });
            const reordered = others.concat([row]);
            const before = rows.slice(0, rows.indexOf(sameRequester[0]));
            const after = rows.slice(rows.indexOf(sameRequester[sameRequester.length - 1]) + 1);
            newIds = before.map(function(r) { return r.dataset.ticketId; }).concat(reordered.map(function(r) { return r.dataset.ticketId; }), after.map(function(r) { return r.dataset.ticketId; }));
        } else {
            return;
        }
        sendReorder(newIds);
    });

    tbody.addEventListener('dblclick', function(evt) {
        const span = evt.target.closest('.editable-sequence');
        if (!span) return;
        evt.preventDefault();
        evt.stopPropagation();
        const row = span.closest('tr');
        const rows = getRows();
        const requesterId = row.dataset.requesterId || '0';
        const sameRequester = rows.filter(function(r) { return (r.dataset.requesterId || '0') === requesterId; });
        const maxPos = sameRequester.length;
        const currentVal = span.textContent.trim();

        const input = document.createElement('input');
        input.type = 'number';
        input.min = 1;
        input.max = maxPos;
        input.value = currentVal;
        input.className = 'w-12 px-1 py-0.5 text-sm border border-slate-300 rounded';
        span.replaceWith(input);
        input.focus();
        input.select();

        let submitted = false;
        function restore() {
            if (submitted || !input.parentNode) return;
            const newSpan = document.createElement('span');
            newSpan.className = 'editable-sequence cursor-pointer';
            newSpan.title = 'Duplo clique para alterar posição';
            newSpan.textContent = currentVal;
            input.replaceWith(newSpan);
        }

        function submit() {
            const targetPos = parseInt(input.value, 10);
            if (isNaN(targetPos) || targetPos < 1 || targetPos > maxPos) {
                alert('Digite um número entre 1 e ' + maxPos + '.');
                restore();
                return;
            }
            const posInGroup = sameRequester.indexOf(row);
            if (targetPos === posInGroup + 1) {
                restore();
                return;
            }
            const others = sameRequester.filter(function(_, i) { return i !== posInGroup; });
            const reordered = others.slice(0, targetPos - 1).concat([row]).concat(others.slice(targetPos - 1));
            const before = rows.slice(0, rows.indexOf(sameRequester[0]));
            const after = rows.slice(rows.indexOf(sameRequester[sameRequester.length - 1]) + 1);
            const newIds = before.map(function(r) { return r.dataset.ticketId; }).concat(reordered.map(function(r) { return r.dataset.ticketId; }), after.map(function(r) { return r.dataset.ticketId; }));
            submitted = true;
            restore();
            sendReorder(newIds);
        }

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); submit(); }
            if (e.key === 'Escape') { submitted = true; restore(); }
        });
        input.addEventListener('blur', function() { setTimeout(function() { if (!submitted) restore(); }, 100); });
    });

    new Sortable(tbody, {
        animation: 150,
        filter: 'a, input, select, button',
        preventOnFilter: true,
        onEnd: function() {
            sendReorder(getTicketIds());
        }
    });
});
</script>
@endif
@endsection
