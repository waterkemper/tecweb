@extends('layouts.app')

@section('title', 'Ticket #' . $ticket->zd_id . ' - ' . Str::limit($ticket->subject, 50))

@section('content')
<div class="mb-6">
    <div class="flex justify-between items-start">
        <h1 class="text-2xl font-bold">Ticket #{{ $ticket->zd_id }} — <span class="font-normal text-gray-700">{{ $ticket->subject }}</span></h1>
        <span class="badge badge-{{ $ticket->status }}">{{ $ticket->status }}</span>
    </div>

    @if (auth()->user() && in_array(auth()->user()->role, ['admin', 'colaborador']))
        <div class="mt-4 p-4 bg-slate-50 border border-slate-200 rounded-lg">
            <h3 class="text-sm font-semibold text-slate-800 mb-3">Pessoas e organização</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
                <div>
                    <span class="text-slate-500 font-medium">Requester:</span>
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
                    <span class="text-slate-500 font-medium">Submitter:</span>
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
                    <span class="text-slate-500 font-medium">Assignee:</span>
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
        <span><strong>Created (Zendesk):</strong> {{ ($ticket->zd_created_at ?? $ticket->created_at)?->format('Y-m-d H:i') ?? '-' }}</span>
        <span>|</span>
        <span><strong>Updated (Zendesk):</strong> {{ ($ticket->zd_updated_at ?? $ticket->updated_at)?->format('Y-m-d H:i') ?? '-' }}</span>
        @if (!in_array($ticket->status ?? '', ['solved', 'closed']) && ($ticket->zd_updated_at ?? $ticket->updated_at))
            @php $age = $ticket->age_status; @endphp
            @if ($age === 'old')
                <span class="badge" style="background:#fef3c7;color:#92400e;">Old ({{ $ticket->days_since_update }}d since update)</span>
            @elseif ($age === 'too_old')
                <span class="badge" style="background:#fee2e2;color:#991b1b;">Too old ({{ $ticket->days_since_update }}d since update)</span>
            @elseif ($age === 'recent')
                <span class="badge" style="background:#dbeafe;color:#1e40af;">{{ $ticket->days_since_update }}d since update</span>
            @elseif ($age === 'fresh')
                <span class="badge" style="background:#d1fae5;color:#065f46;">Fresh</span>
            @endif
        @endif
    </div>
</div>

<div class="card mb-6 border-l-4 border-l-blue-500">
    <div class="flex justify-between items-start mb-3">
        <h2 class="font-semibold text-lg">AI Summary</h2>
        @if ($analysis)
            <div class="flex items-center gap-3">
                @if ($analysis->last_ai_refresh_at)
                    <span class="text-xs text-gray-500">Última atualização: {{ $analysis->last_ai_refresh_at->diffForHumans() }}</span>
                @endif
                <form action="{{ route('tickets.refresh-ai', $ticket) }}" method="post" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">Atualizar IA</button>
                </form>
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
            <p class="text-sm mb-3 p-2 bg-blue-50 border-l-2 border-blue-400 rounded"><strong>Next action:</strong> {{ $analysis->next_action }}</p>
        @endif

        @if (($analysis->what_reported || $analysis->what_tried) && !($analysis->bullets && count($analysis->bullets) > 0))
            <details class="mb-3 text-sm">
                <summary class="cursor-pointer text-gray-600 hover:text-gray-800">More context</summary>
                <div class="mt-2 pl-3 border-l-2 border-gray-200 space-y-1 text-gray-600">
                    @if ($analysis->what_reported)
                        <p><strong>Reported:</strong> {{ $analysis->what_reported }}</p>
                    @endif
                    @if ($analysis->what_tried)
                        <p><strong>Tried:</strong> {{ $analysis->what_tried }}</p>
                    @endif
                </div>
            </details>
        @elseif ($analysis->what_reported || $analysis->what_tried)
            <div class="mb-3 text-sm text-gray-600 space-y-1">
                @if ($analysis->what_reported)
                    <p><strong>Reported:</strong> {{ $analysis->what_reported }}</p>
                @endif
                @if ($analysis->what_tried)
                    <p><strong>Tried:</strong> {{ $analysis->what_tried }}</p>
                @endif
            </div>
        @endif

        @if ($analysis->pending_action)
            <div class="mb-3">
                @if ($analysis->pending_action === 'our_side')
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-sm font-medium bg-amber-100 text-amber-800" title="We need to act or reply">Pending: Our side</span>
                @elseif ($analysis->pending_action === 'customer_side')
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-sm font-medium bg-blue-100 text-blue-800" title="Waiting for customer response">Pending: Customer</span>
                @elseif ($analysis->pending_action === 'can_close')
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-sm font-medium bg-green-100 text-green-800" title="Customer's last reply suggests we can close">Can close</span>
                @endif
            </div>
        @endif

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
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 mb-3 text-sm">
            @if ($analysis->severity)
                <span><strong>Severity:</strong> <span class="badge badge-{{ $analysis->severity }}">{{ $analysis->severity }}</span></span>
            @endif
            @if ($analysis->requires_dev !== null)
                <span>|</span>
                <span><strong>Requires Dev:</strong> <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $analysis->requires_dev ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $analysis->requires_dev ? 'Sim' : 'Não' }}</span></span>
            @endif
            @if ($analysis->suggested_owner)
                <span>|</span>
                <span><strong>Suggested owner:</strong> {{ $analysis->suggested_owner }}</span>
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
                <form action="{{ route('tickets.apply-tags', $ticket) }}" method="post" class="inline">
                    @csrf
                    <button type="submit" class="text-xs px-2.5 py-1 bg-green-600 text-white rounded hover:bg-green-700 transition-colors">Aplicar</button>
                </form>
            </div>
        @endif

        @if ($ticket->analysisHistory->isNotEmpty())
            <details class="mt-3 pt-3 border-t border-gray-200">
                <summary class="cursor-pointer text-sm text-gray-600 hover:text-gray-800">Histórico de análise</summary>
                <ul class="mt-2 space-y-2 text-xs">
                    @foreach ($ticket->analysisHistory as $hist)
                    <li class="p-2 bg-gray-50 rounded">
                        <span class="text-gray-500">{{ $hist->snapshot_at->format('Y-m-d H:i') }}</span>
                        @if ($hist->effort_min !== null || $hist->effort_max !== null)
                            <span class="ml-2">Effort: {{ $hist->effort_min ?? '-' }}-{{ $hist->effort_max ?? '-' }}h</span>
                        @endif
                        @if ($hist->severity)
                            <span class="ml-2">Severity: {{ $hist->severity }}</span>
                        @endif
                        @if (!empty($hist->actions_needed_list))
                            <span class="ml-2">Actions: {{ count($hist->actions_needed_list) }}</span>
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
        <p class="text-gray-500 text-sm">No AI analysis yet. Run the AI pipeline or click Refresh.</p>
        <form action="{{ route('tickets.refresh-ai', $ticket) }}" method="post" class="inline mt-2">
            @csrf
            <button type="submit" class="px-3 py-1.5 text-sm font-medium bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">Atualizar IA</button>
        </form>
    @endif
</div>

@if (session('success'))
    <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">{{ session('error') }}</div>
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
            </li>
            @endforeach
        </ul>
    </div>
@endif

<div class="mt-6">
    <h3 class="font-semibold mb-2">Conversation</h3>
    @foreach ($ticket->comments as $comment)
        <div class="card mb-2 {{ $comment->is_public ? '' : 'bg-amber-50 border-amber-200' }}">
            <p class="text-sm text-gray-600 mb-1">
                {{ $comment->created_at?->format('Y-m-d H:i') }}
                @if (!$comment->is_public)
                    <span class="badge badge-pending">Internal</span>
                @endif
            </p>
            <div class="prose max-w-none text-sm">
                {!! nl2br(e($comment->body)) !!}
            </div>
            @php $attachments = $comment->attachments_json ?? []; @endphp
            @if (!empty($attachments))
                <div class="mt-2 pt-2 border-t border-gray-100">
                    <p class="text-xs text-gray-500 mb-2">Attachments</p>
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
@endsection
