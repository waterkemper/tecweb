@extends('layouts.app')

@section('title', 'Ticket #' . $ticket->zd_id . ' - ' . Str::limit($ticket->subject, 50))

@section('content')
@php
    $statusLabels = ['new' => 'Novo', 'open' => 'Aberto', 'pending' => 'Pendente', 'hold' => 'Aguardando', 'solved' => 'Resolvido', 'closed' => 'Fechado'];
@endphp
<div class="mb-6">
    <div class="flex justify-between items-start">
        <h1 class="text-2xl font-bold">Ticket #{{ $ticket->zd_id }} — <span class="font-normal text-gray-700">{{ $ticket->subject }}</span></h1>
        <div class="flex items-center gap-3">
            @if (auth()->user() && in_array(auth()->user()->role, ['admin', 'colaborador']))
                @php
                    $currentStatus = $ticket->status ?? 'open';
                    $isClosed = $currentStatus === 'closed';
                    $allowedStatuses = $isClosed
                        ? []
                        : (in_array($currentStatus, ['new', 'open', 'pending', 'hold'])
                            ? ['new', 'open', 'pending', 'hold', 'solved']
                            : array_keys($statusLabels));
                @endphp
                @if ($isClosed)
                    <span class="badge badge-closed">{{ $statusLabels['closed'] }}</span>
                    <span class="text-xs text-gray-500">(não pode ser reaberto)</span>
                @else
                    <form id="status-form" action="{{ route('tickets.status.update', $ticket) }}" method="post" class="inline-flex items-center gap-2">
                        @csrf
                        <select name="status" id="status-select" data-current="{{ $currentStatus }}" class="text-sm border border-gray-300 rounded-md px-2.5 py-1.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                            @foreach ($statusLabels as $val => $label)
                                @if (in_array($val, $allowedStatuses))
                                    <option value="{{ $val }}" {{ $currentStatus === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endif
                            @endforeach
                        </select>
                    </form>
                @endif
            @else
                <span class="badge badge-{{ $ticket->status ?? 'open' }}">{{ $statusLabels[$ticket->status ?? ''] ?? ($ticket->status ?? '-') }}</span>
            @endif
        </div>
    </div>

    @if (auth()->user())
        <div class="mt-4 p-4 bg-slate-50 border border-slate-200 rounded-lg">
            <h3 class="text-sm font-semibold text-slate-800 mb-3">Pessoas e organização</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
                <div>
                    <span class="text-slate-500 font-medium">Solicitante:</span>
                    @if ($ticket->requester)
                        <span>{{ $ticket->requester->name }}</span>
                        @if ($ticket->requester->email)
                            <a href="mailto:{{ $ticket->requester->email }}" class="text-blue-600 hover:underline ml-1">{{ $ticket->requester->email }}</a>
                        @endif
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </div>
                <div>
                    <span class="text-slate-500 font-medium">Enviado por:</span>
                    @if ($ticket->submitter)
                        <span>{{ $ticket->submitter->name }}</span>
                        @if ($ticket->submitter->email)
                            <a href="mailto:{{ $ticket->submitter->email }}" class="text-blue-600 hover:underline ml-1">{{ $ticket->submitter->email }}</a>
                        @endif
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </div>
                <div>
                    <span class="text-slate-500 font-medium">Responsável:</span>
                    @if ($ticket->assignee)
                        <span>{{ $ticket->assignee->name }}</span>
                        @if ($ticket->assignee->email)
                            <a href="mailto:{{ $ticket->assignee->email }}" class="text-blue-600 hover:underline ml-1">{{ $ticket->assignee->email }}</a>
                        @endif
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </div>
                <div class="md:col-span-2">
                    <span class="text-slate-500 font-medium">Organização:</span>
                    @if ($ticket->organization)
                        <span>{{ $ticket->organization->name }}</span>
                        @if ($ticket->organization->domain_names && count($ticket->organization->domain_names) > 0)
                            <span class="text-slate-400 text-xs">({{ implode(', ', $ticket->organization->domain_names) }})</span>
                        @endif
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </div>
                @php $collaborators = $ticket->collaborators; @endphp
                @if ($collaborators->isNotEmpty())
                    <div class="md:col-span-2 lg:col-span-3">
                        <span class="text-slate-500 font-medium">Colaboradores (CC):</span>
                        <span class="text-slate-700">
                            @foreach ($collaborators as $c)
                                {{ $c->name }}@if($c->email) &lt;<a href="mailto:{{ $c->email }}" class="text-blue-600 hover:underline">{{ $c->email }}</a>&gt;@endif{{ !$loop->last ? ', ' : '' }}
                            @endforeach
                        </span>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-3 mt-2 text-sm text-gray-600">
        <span><strong>Criado (Zendesk):</strong> {{ ($ticket->zd_created_at ?? $ticket->created_at)?->format('d/m/y H:i') ?? '-' }}</span>
        <span>|</span>
        <span><strong>Atualizado (Zendesk):</strong> {{ ($ticket->zd_updated_at ?? $ticket->updated_at)?->format('d/m/y H:i') ?? '-' }}</span>
        @if (!in_array($ticket->status ?? '', ['solved', 'closed']) && ($ticket->zd_updated_at ?? $ticket->updated_at))
            @php $age = $ticket->age_status; @endphp
            @if ($age === 'old')
                <span class="badge" style="background:#fef3c7;color:#92400e;">Antigo ({{ $ticket->days_since_update }}d desde atualização)</span>
            @elseif ($age === 'too_old')
                <span class="badge" style="background:#fee2e2;color:#991b1b;">Muito antigo ({{ $ticket->days_since_update }}d desde atualização)</span>
            @elseif ($age === 'recent')
                <span class="badge" style="background:#dbeafe;color:#1e40af;">{{ $ticket->days_since_update }}d desde atualização</span>
            @elseif ($age === 'fresh')
                <span class="badge" style="background:#d1fae5;color:#065f46;">Recente</span>
            @endif
        @endif
    </div>

    {{-- Tags --}}
    <div class="mt-4 p-4 bg-gray-50 border border-gray-200 rounded-lg">
        <h3 class="text-sm font-semibold text-gray-800 mb-2">Tags</h3>
        @if (auth()->user() && in_array(auth()->user()->role, ['admin', 'colaborador']))
            <div class="mb-3 flex flex-wrap gap-2 items-center">
                <form action="{{ route('tickets.tags.update', $ticket) }}" method="post" class="flex flex-wrap gap-2 items-center flex-1 min-w-0">
                    @csrf
                    <input type="text" name="tags" value="{{ implode(', ', $ticket->tags ?? []) }}"
                        placeholder="Tags separadas por vírgula"
                        class="flex-1 min-w-[200px] px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <button type="submit" class="px-3 py-2 text-sm font-medium bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                        Salvar tags
                    </button>
                </form>
                <form action="{{ route('tickets.tags.sync', $ticket) }}" method="post" class="inline">
                    @csrf
                    <button type="submit" class="px-3 py-2 text-sm font-medium bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors">
                        Sincronizar do Zendesk
                    </button>
                </form>
            </div>
        @endif
        <div class="flex flex-wrap gap-1.5">
            @forelse ($ticket->tags ?? [] as $tag)
                <span class="text-xs px-2 py-0.5 bg-gray-200 text-gray-700 rounded">{{ $tag }}</span>
            @empty
                <span class="text-sm text-gray-500">Nenhuma tag</span>
            @endforelse
        </div>
    </div>
</div>

<div class="card mb-6 border-l-4 border-l-blue-500">
    <div class="flex justify-between items-start mb-3">
        <h2 class="font-semibold text-lg">Resumo IA</h2>
        @if ($analysis)
            <div class="flex items-center gap-3">
                @if ($analysis->last_ai_refresh_at)
                    <span class="text-xs text-gray-500">Última atualização: {{ $analysis->last_ai_refresh_at->diffForHumans() }}</span>
                @endif
                @if (auth()->user()?->role === 'admin')
                    <form action="{{ route('tickets.refresh-ai', $ticket) }}" method="post" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">Atualizar IA</button>
                    </form>
                @endif
            </div>
        @endif
    </div>

    @if ($analysis)
        @if ($analysis->bullets && count($analysis->bullets) > 0)
            <ul class="list-disc list-inside mb-3 space-y-1 text-sm">
                @foreach ($analysis->bullets as $bullet)
                <li>{{ $bullet }}</li>
                @endforeach
            </ul>
        @elseif ($analysis->summary)
            <p class="text-sm mb-3 whitespace-pre-line">{{ $analysis->summary }}</p>
        @endif

        @php
            $openQuestions = $analysis->open_questions_list ?? [];
            if (empty($openQuestions) && $analysis->open_questions) {
                $openQuestions = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $analysis->open_questions)));
            }
        @endphp
        @if (!empty($openQuestions))
            <div class="mb-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                <h4 class="font-semibold text-amber-900 text-sm mb-2">Perguntas abertas</h4>
                <ul class="space-y-1.5 text-sm text-amber-900 list-disc list-inside">
                    @foreach ($openQuestions as $q)
                    <li>{{ $q }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php $actionsNeeded = $analysis->actions_needed_list ?? []; @endphp
        @if (!empty($actionsNeeded))
            <div class="mb-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <h4 class="font-semibold text-blue-900 text-sm mb-2">Ações necessárias</h4>
                <ul class="space-y-1.5 text-sm text-blue-900 list-disc list-inside">
                    @foreach ($actionsNeeded as $action)
                    <li>{{ $action }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($analysis->current_status)
            <p class="text-sm mb-2"><strong>Status:</strong> {{ $analysis->current_status }}</p>
        @endif

        @if ($analysis->next_action)
            <p class="text-sm mb-3 p-2 bg-blue-50 border-l-2 border-blue-400 rounded"><strong>Próxima ação:</strong> {{ $analysis->next_action }}</p>
        @endif

        @if (($analysis->what_reported || $analysis->what_tried) && !($analysis->bullets && count($analysis->bullets) > 0))
            <details class="mb-3 text-sm">
                <summary class="cursor-pointer text-gray-600 hover:text-gray-800">Mais contexto</summary>
                <div class="mt-2 pl-3 border-l-2 border-gray-200 space-y-1 text-gray-600">
                    @if ($analysis->what_reported)
                        <p><strong>Reportado:</strong> {{ $analysis->what_reported }}</p>
                    @endif
                    @if ($analysis->what_tried)
                        <p><strong>Tentativas:</strong> {{ $analysis->what_tried }}</p>
                    @endif
                </div>
            </details>
        @elseif ($analysis->what_reported || $analysis->what_tried)
            <div class="mb-3 text-sm text-gray-600 space-y-1">
                @if ($analysis->what_reported)
                    <p><strong>Reportado:</strong> {{ $analysis->what_reported }}</p>
                @endif
                @if ($analysis->what_tried)
                    <p><strong>Tentativas:</strong> {{ $analysis->what_tried }}</p>
                @endif
            </div>
        @endif

        <div class="mb-3 flex flex-wrap items-center gap-2">
            @if (auth()->user() && in_array(auth()->user()->role, ['admin', 'colaborador']))
                <form action="{{ route('tickets.pending-action.update', $ticket) }}" method="post" class="inline-flex items-center gap-2">
                    @csrf
                    <label for="pending_action" class="text-sm text-gray-600">Pendente por:</label>
                    <select name="pending_action" id="pending_action" onchange="this.form.submit()" class="text-sm border border-gray-300 rounded-md px-2.5 py-1.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                        <option value="">—</option>
                        <option value="our_side" {{ ($analysis->pending_action ?? '') === 'our_side' ? 'selected' : '' }}>Pendente: Nossa equipe</option>
                        <option value="customer_side" {{ ($analysis->pending_action ?? '') === 'customer_side' ? 'selected' : '' }}>Pendente: Cliente</option>
                        <option value="can_close" {{ ($analysis->pending_action ?? '') === 'can_close' ? 'selected' : '' }}>Pode fechar</option>
                    </select>
                </form>
            @elseif ($analysis->pending_action)
                @if ($analysis->pending_action === 'our_side')
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-sm font-medium bg-amber-100 text-amber-800" title="Precisamos agir ou responder">Pendente: Nossa equipe</span>
                @elseif ($analysis->pending_action === 'customer_side')
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-sm font-medium bg-blue-100 text-blue-800" title="Aguardando resposta do cliente">Pendente: Cliente</span>
                @elseif ($analysis->pending_action === 'can_close')
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-sm font-medium bg-green-100 text-green-800" title="Última resposta do cliente sugere que podemos fechar">Pode fechar</span>
                @endif
            @endif
        </div>

        {{-- Previsões de esforço --}}
        <div class="mb-4 p-4 bg-gray-50 border border-gray-200 rounded-lg">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">Previsão de horas</h4>
            <div class="flex flex-wrap items-center gap-4">
                @if ($analysis->effort_min !== null || $analysis->effort_max !== null)
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-600">Previsão IA:</span>
                        <span class="inline-flex items-center px-3 py-1.5 bg-indigo-50 text-indigo-800 rounded-md font-medium text-sm">{{ $analysis->effort_min ?? '-' }}-{{ $analysis->effort_max ?? '-' }}h</span>
                    </div>
                @endif
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-600">Previsão interna:</span>
                    @if (auth()->user() && in_array(auth()->user()->role, ['admin', 'colaborador']))
                    <form action="{{ route('tickets.internal-effort', $ticket) }}" method="post" class="inline-flex items-center gap-2">
                        @csrf
                        <input type="number" name="internal_effort_min" step="0.5" min="0" placeholder="min" value="{{ $analysis->internal_effort_min }}"
                            class="w-20 px-2.5 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                        <span class="text-gray-400">–</span>
                        <input type="number" name="internal_effort_max" step="0.5" min="0" placeholder="max" value="{{ $analysis->internal_effort_max }}"
                            class="w-20 px-2.5 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                        <span class="text-sm text-gray-500">h</span>
                        <button type="submit" class="px-3 py-1.5 text-sm font-medium bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">Salvar</button>
                    </form>
                    @else
                    <span class="inline-flex items-center px-3 py-1.5 bg-gray-100 text-gray-700 rounded-md font-medium text-sm">{{ $analysis->internal_effort_min ?? '-' }}-{{ $analysis->internal_effort_max ?? '-' }}h</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 mb-3 text-sm">
            @if ($analysis->severity)
                <span><strong>Gravidade:</strong> <span class="badge badge-{{ $analysis->severity }}">{{ $analysis->severity }}</span></span>
            @endif
            @if ($analysis->requires_dev !== null)
                <span>|</span>
                <span><strong>Requer Dev:</strong> <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $analysis->requires_dev ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $analysis->requires_dev ? 'Sim' : 'Não' }}</span></span>
            @endif
            @if ($analysis->suggested_owner)
                <span>|</span>
                <span><strong>Responsável sugerido:</strong> {{ $analysis->suggested_owner }}</span>
            @endif
        </div>

        @if ($similarTickets->isNotEmpty())
            <div class="mt-3 pt-3 border-t border-gray-200">
                <h4 class="font-semibold text-gray-800 text-sm mb-2">Tickets similares</h4>
                <ul class="space-y-1.5 text-sm">
                    @foreach ($similarTickets as $sim)
                    <li>
                        <a href="{{ route('tickets.show', $sim->similarTicket) }}" class="text-blue-600 hover:underline" title="{{ $sim->similarTicket->subject }}">
                            #{{ $sim->similarTicket->zd_id }} — {{ Str::limit($sim->similarTicket->subject, 55) }}
                        </a>
                        <span class="text-gray-500"> ({{ number_format($sim->score * 100, 1) }}%)</span>
                        @if ($sim->similarTicket->requester || $sim->similarTicket->organization)
                            <span class="text-xs text-gray-400 block mt-0.5">
                                {{ $sim->similarTicket->requester?->name ?? $sim->similarTicket->requester?->email ?? '-' }}
                                @if ($sim->similarTicket->organization)
                                    · {{ $sim->similarTicket->organization->name }}
                                @endif
                            </span>
                        @endif
                    </li>
                    @endforeach
                </ul>
                @if ($similarTicketsAvgHours)
                    <p class="text-xs text-gray-500 mt-1">Média de resolução: {{ $similarTicketsAvgHours }}h</p>
                @endif
            </div>
        @endif

        @if ($analysis->effort_reason)
            <p class="text-xs text-gray-500 mb-2"><em>{{ $analysis->effort_reason }}</em></p>
        @endif

        @if ($analysis->suggested_tags && count($analysis->suggested_tags) > 0)
            <div class="flex items-center gap-2 mt-2">
                <span class="text-sm text-gray-600">Tags sugeridas:</span>
                @foreach ($analysis->suggested_tags as $tag)
                    <span class="text-xs px-2 py-0.5 bg-gray-100 text-gray-700 rounded">{{ $tag }}</span>
                @endforeach
                @if (auth()->user() && in_array(auth()->user()->role, ['admin', 'colaborador']))
                <form action="{{ route('tickets.apply-tags', $ticket) }}" method="post" class="inline">
                    @csrf
                    <button type="submit" class="text-xs px-2.5 py-1 bg-green-600 text-white rounded hover:bg-green-700 transition-colors">Aplicar</button>
                </form>
                @endif
            </div>
        @endif

        @if ($ticket->analysisHistory->isNotEmpty())
            <details class="mt-3 pt-3 border-t border-gray-200">
                <summary class="cursor-pointer text-sm text-gray-600 hover:text-gray-800">Histórico de análise</summary>
                <ul class="mt-2 space-y-2 text-xs">
                    @foreach ($ticket->analysisHistory as $hist)
                    <li class="p-2 bg-gray-50 rounded">
                        <span class="text-gray-500">{{ $hist->snapshot_at->format('d/m/y H:i') }}</span>
                        @if ($hist->effort_min !== null || $hist->effort_max !== null)
                            <span class="ml-2">Esforço: {{ $hist->effort_min ?? '-' }}-{{ $hist->effort_max ?? '-' }}h</span>
                        @endif
                        @if ($hist->severity)
                            <span class="ml-2">Gravidade: {{ $hist->severity }}</span>
                        @endif
                        @if (!empty($hist->actions_needed_list))
                            <span class="ml-2">Ações: {{ count($hist->actions_needed_list) }}</span>
                        @endif
                    </li>
                    @endforeach
                </ul>
            </details>
        @endif

        @php $labels = array_merge($analysis->categories ?? [$analysis->category], $analysis->modules ?? []); @endphp
        @if (!empty(array_filter($labels)))
            <div class="flex flex-wrap gap-1 mt-2">
                @foreach ($labels as $label)
                    @if ($label)
                        <span class="text-xs px-2 py-0.5 bg-indigo-50 text-indigo-700 rounded">{{ $label }}</span>
                    @endif
                @endforeach
            </div>
        @endif
    @else
        <p class="text-gray-500 text-sm">Sem análise IA ainda.@if (auth()->user()?->role === 'admin') Execute o pipeline de IA ou clique em Atualizar.@endif</p>
        @if (auth()->user()?->role === 'admin')
            <form action="{{ route('tickets.refresh-ai', $ticket) }}" method="post" class="inline mt-2">
                @csrf
                <button type="submit" class="px-3 py-1.5 text-sm font-medium bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">Atualizar IA</button>
            </form>
        @endif
    @endif
</div>

@if (session('success'))
    <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
        <ul class="list-disc list-inside">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if (!$analysis && $similarTickets->isNotEmpty())
    <div class="card mb-6 border-l-4 border-l-blue-500">
        <h3 class="font-semibold mb-3 text-gray-800">Tickets similares</h3>
        <ul class="space-y-1.5 text-sm">
            @foreach ($similarTickets as $sim)
            <li>
                <a href="{{ route('tickets.show', $sim->similarTicket) }}" class="text-blue-600 hover:underline" title="{{ $sim->similarTicket->subject }}">
                    #{{ $sim->similarTicket->zd_id }} — {{ Str::limit($sim->similarTicket->subject, 55) }}
                </a>
                <span class="text-gray-500"> ({{ number_format($sim->score * 100, 1) }}%)</span>
                @if ($sim->similarTicket->requester || $sim->similarTicket->organization)
                    <span class="text-xs text-gray-400 block mt-0.5">
                        {{ $sim->similarTicket->requester?->name ?? $sim->similarTicket->requester?->email ?? '-' }}
                        @if ($sim->similarTicket->organization)
                            · {{ $sim->similarTicket->organization->name }}
                        @endif
                    </span>
                @endif
            </li>
            @endforeach
        </ul>
    </div>
@endif

<div class="mt-6">
    <h3 class="font-semibold mb-2">Conversa</h3>
    @if (auth()->user())
        <div class="card mb-4 p-4 bg-slate-50 border border-slate-200">
            <form action="{{ route('tickets.comments.store', $ticket) }}" method="post" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label for="comment_body" class="block text-sm font-medium text-gray-700 mb-1">Novo comentário</label>
                    <textarea name="body" id="comment_body" rows="4" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Digite seu comentário..."></textarea>
                </div>
                @if (in_array(auth()->user()->role, ['admin', 'colaborador']))
                <div class="mb-3">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="is_internal" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm text-gray-700">Comentário interno (só colaboradores)</span>
                    </label>
                </div>
                @if (!in_array($ticket->status ?? '', ['closed']))
                <div class="mb-3">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="close_with_comment" value="1" id="close_with_comment" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm text-gray-700">
                            @if (in_array($ticket->status ?? '', ['solved']))
                                Enviar e fechar ticket
                            @else
                                Enviar e marcar como resolvido
                            @endif
                        </span>
                    </label>
                </div>
                @endif
                @endif
                <div class="mb-3">
                    <label for="attachments" class="block text-sm font-medium text-gray-700 mb-1">Anexos (máx. 50 MB cada)</label>
                    <input type="file" name="attachments[]" id="attachments" multiple
                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors text-sm font-medium">
                    Enviar comentário
                </button>
            </form>
        </div>
    @endif
    @foreach ($ticket->comments as $comment)
        <div class="card mb-2 {{ $comment->is_public ? '' : 'bg-amber-50 border-amber-200' }}">
            <p class="text-sm text-gray-600 mb-1">
                <span class="font-medium text-gray-800">{{ $comment->author?->name ?? $comment->author?->email ?? 'Desconhecido' }}</span>
                · {{ $comment->created_at?->format('d/m/y H:i') }}
                @if (!$comment->is_public)
                    <span class="badge badge-pending">Interno</span>
                @endif
            </p>
            <div class="prose max-w-none text-sm">
                {!! nl2br(e($comment->body)) !!}
            </div>
            @php $attachments = $comment->attachments_json ?? []; @endphp
            @if (!empty($attachments))
                <div class="mt-2 pt-2 border-t border-gray-100">
                    <p class="text-xs text-gray-500 mb-2">Anexos</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($attachments as $idx => $att)
                            @php
                                $contentUrl = $att['content_url'] ?? $att['url'] ?? null;
                                $filename = $att['filename'] ?? 'attachment';
                                $contentType = $att['content_type'] ?? '';
                                $isImage = str_starts_with($contentType, 'image/');
                                $size = $att['size'] ?? null;
                            @endphp
                            @if ($contentUrl && str_contains($contentUrl, 'zendesk.com'))
                                @if ($isImage)
                                    <a href="{{ route('tickets.attachment', [$ticket, $comment->id, $idx]) }}" target="_blank" rel="noopener" class="block">
                                        <img src="{{ route('tickets.attachment', [$ticket, $comment->id, $idx]) }}" alt="{{ $filename }}" class="max-h-32 max-w-full rounded border border-gray-200 object-contain hover:opacity-90" loading="lazy">
                                        <span class="text-xs text-gray-500 block mt-0.5">{{ $filename }}</span>
                                    </a>
                                @else
                                    <a href="{{ route('tickets.attachment', [$ticket, $comment->id, $idx]) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-sm text-blue-600 hover:underline">
                                        <span>📎</span>
                                        <span>{{ $filename }}</span>
                                        @if ($size)
                                            <span class="text-gray-400 text-xs">({{ number_format($size / 1024, 1) }} KB)</span>
                                        @endif
                                    </a>
                                @endif
                            @elseif ($contentUrl)
                                <a href="{{ $contentUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-sm text-blue-600 hover:underline">
                                    <span>📎</span>
                                    <span>{{ $filename }}</span>
                                </a>
                            @else
                                <span class="text-sm text-gray-500">📎 {{ $filename }}</span>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endforeach
</div>

@if (auth()->user() && in_array(auth()->user()->role, ['admin', 'colaborador']))
<script>
(function() {
    var statusForm = document.getElementById('status-form');
    var statusSelect = document.getElementById('status-select');
    var commentBody = document.getElementById('comment_body');
    if (statusForm && statusSelect && commentBody) {
        statusSelect.addEventListener('change', function() {
            var hasComment = commentBody && commentBody.value.trim().length > 0;
            if (hasComment) {
                if (!confirm('Você tem um comentário não enviado. Deseja descartar e alterar o status?')) {
                    statusSelect.value = statusSelect.getAttribute('data-current');
                    return;
                }
            }
            statusForm.submit();
        });
    }
})();
</script>
@endif
@endsection
