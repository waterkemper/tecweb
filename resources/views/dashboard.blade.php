@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<h1 class="text-2xl font-bold mb-6">tecDESK Dashboard</h1>

{{-- Cards de resumo --}}
<div class="grid grid-cols-2 md:grid-cols-7 gap-4 mb-8">
    <div class="stat-card">
        <div class="text-sm text-gray-600">New</div>
        <div class="stat-value">{{ $newCount }}</div>
    </div>
    <div class="stat-card">
        <div class="text-sm text-gray-600">Open</div>
        <div class="stat-value">{{ $openCount }}</div>
    </div>
    <div class="stat-card">
        <div class="text-sm text-gray-600">Pending / Hold</div>
        <div class="stat-value">{{ $pendingCount }}</div>
    </div>
    <div class="stat-card">
        <div class="text-sm text-gray-600">Total ativos</div>
        <div class="stat-value">{{ $totalActive }}</div>
    </div>
    <div class="stat-card">
        <div class="text-sm text-gray-600">Resolvidos (30d)</div>
        <div class="stat-value">{{ $resolvedLast30 }}</div>
    </div>
    <div class="stat-card">
        <div class="text-sm text-gray-600">Atrasados</div>
        <div class="stat-value">
            @if (($overdueCount ?? 0) > 0)
                <a href="{{ route('tickets.index', ['overdue' => 1]) }}" class="text-red-600 hover:underline">{{ $overdueCount }}</a>
            @else
                {{ $overdueCount ?? 0 }}
            @endif
        </div>
    </div>
    <div class="stat-card">
        <div class="text-sm text-gray-600">Sem prazo</div>
        <div class="stat-value">
            @if (($withoutDeadlineCount ?? 0) > 0)
                <a href="{{ route('tickets.index', ['without_deadline' => 1]) }}" class="text-amber-600 hover:underline">{{ $withoutDeadlineCount }}</a>
            @else
                {{ $withoutDeadlineCount ?? 0 }}
            @endif
        </div>
    </div>
</div>

<div class="stat-card mb-8" style="max-width: 200px;">
    <div class="text-sm text-gray-600">Horas previstas</div>
    <div class="stat-value">{{ number_format($hoursPredicted, 1) }}h</div>
</div>

@if (auth()->user() && (in_array(auth()->user()->role, ['admin', 'colaborador']) || auth()->user()->canViewAllOrgTickets()))
    {{-- Por organização --}}
    @if ($byOrg && $byOrg->isNotEmpty())
        <div class="card mb-8">
            <h2 class="text-lg font-semibold mb-4">Por organização</h2>
            <table>
                <thead>
                    <tr>
                        <th>Organização</th>
                        <th>Tickets</th>
                        <th>Horas prev.</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($byOrg as $row)
                    <tr>
                        <td>{{ $row['org']?->name ?? '—' }}</td>
                        <td>{{ $row['count'] }}</td>
                        <td>{{ number_format($row['hours'], 1) }}h</td>
                        <td>
                            @if ($row['org'])
                                <a href="{{ route('tickets.index', ['org' => $row['org']->zd_id]) }}" class="text-blue-600 hover:underline text-sm">Ver tickets</a>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if (auth()->user()?->role === 'admin' && $byRequester && $byRequester->isNotEmpty())
        {{-- Por requester (admin only) --}}
        <div class="card mb-8">
            <h2 class="text-lg font-semibold mb-4">Por cliente (requester)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Tickets</th>
                        <th>Horas prev.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($byRequester as $row)
                    <tr>
                        <td>{{ $row['user']?->name ?? $row['user']?->email ?? '—' }}</td>
                        <td>{{ $row['count'] }}</td>
                        <td>{{ number_format($row['hours'], 1) }}h</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Por severidade --}}
    @if (!empty($bySeverity))
        <div class="card mb-8">
            <h2 class="text-lg font-semibold mb-4">Por severidade</h2>
            <div class="flex flex-wrap gap-4">
                @foreach ($bySeverity as $sev => $cnt)
                    @if ($cnt > 0)
                        <div class="flex items-center gap-2">
                            <span class="badge badge-{{ $sev }}">{{ $sev }}</span>
                            <span class="font-semibold">{{ $cnt }}</span>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    {{-- Por categoria --}}
    @if (!empty($byCategory))
        <div class="card mb-8">
            <h2 class="text-lg font-semibold mb-4">Top categorias</h2>
            <div class="flex flex-wrap gap-4">
                @foreach ($byCategory as $cat => $cnt)
                    <div class="flex items-center gap-2">
                        <span class="text-sm px-2 py-1 bg-indigo-50 text-indigo-700 rounded">{{ $cat }}</span>
                        <span class="font-semibold">{{ $cnt }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Tickets por data --}}
    @if (!empty($ticketsByDate))
        <div class="card mb-8">
            <h2 class="text-lg font-semibold mb-4">Tickets criados por data (últimos 30 dias)</h2>
            <div style="height: 200px;">
                <canvas id="ticketsByDateChart"></canvas>
            </div>
        </div>
    @endif

    {{-- Tickets atrasados --}}
    <div class="card mb-8">
        <h2 class="text-lg font-semibold mb-4">Tickets atrasados</h2>
        <form method="GET" class="mb-4 p-3 bg-slate-50 rounded-lg border border-slate-200 flex flex-wrap gap-3 items-end">
            @if (($overdueOrganizations ?? collect())->isNotEmpty())
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-0.5">Organização</label>
                <select name="overdue_org" class="w-44 px-2 py-1.5 text-sm border border-slate-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todas</option>
                    @foreach ($overdueOrganizations as $org)
                    <option value="{{ $org->zd_id }}" {{ ($overdueFilters['overdue_org'] ?? '') == $org->zd_id ? 'selected' : '' }}>{{ $org->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            @if (auth()->user()?->role === 'admin' && ($overdueRequesters ?? collect())->isNotEmpty())
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-0.5">Solicitante</label>
                <select name="overdue_requester" class="w-44 px-2 py-1.5 text-sm border border-slate-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    @foreach ($overdueRequesters as $req)
                    <option value="{{ $req->zd_id }}" {{ ($overdueFilters['overdue_requester'] ?? '') == $req->zd_id ? 'selected' : '' }}>{{ $req->name ?? $req->email ?? "#{$req->zd_id}" }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-0.5">Status</label>
                <select name="overdue_status" class="w-32 px-2 py-1.5 text-sm border border-slate-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <option value="new" {{ ($overdueFilters['overdue_status'] ?? '') === 'new' ? 'selected' : '' }}>Novo</option>
                    <option value="open" {{ ($overdueFilters['overdue_status'] ?? '') === 'open' ? 'selected' : '' }}>Aberto</option>
                    <option value="pending" {{ ($overdueFilters['overdue_status'] ?? '') === 'pending' ? 'selected' : '' }}>Pendente</option>
                    <option value="hold" {{ ($overdueFilters['overdue_status'] ?? '') === 'hold' ? 'selected' : '' }}>Aguardando</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-0.5">Prioridade</label>
                <select name="overdue_priority" class="w-28 px-2 py-1.5 text-sm border border-slate-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todas</option>
                    <option value="urgent" {{ ($overdueFilters['overdue_priority'] ?? '') === 'urgent' ? 'selected' : '' }}>Urgente</option>
                    <option value="high" {{ ($overdueFilters['overdue_priority'] ?? '') === 'high' ? 'selected' : '' }}>Alta</option>
                    <option value="normal" {{ ($overdueFilters['overdue_priority'] ?? '') === 'normal' ? 'selected' : '' }}>Normal</option>
                    <option value="low" {{ ($overdueFilters['overdue_priority'] ?? '') === 'low' ? 'selected' : '' }}>Baixa</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-0.5">Severidade</label>
                <select name="overdue_severity" class="w-28 px-2 py-1.5 text-sm border border-slate-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todas</option>
                    <option value="critical" {{ ($overdueFilters['overdue_severity'] ?? '') === 'critical' ? 'selected' : '' }}>Critical</option>
                    <option value="high" {{ ($overdueFilters['overdue_severity'] ?? '') === 'high' ? 'selected' : '' }}>High</option>
                    <option value="medium" {{ ($overdueFilters['overdue_severity'] ?? '') === 'medium' ? 'selected' : '' }}>Medium</option>
                    <option value="low" {{ ($overdueFilters['overdue_severity'] ?? '') === 'low' ? 'selected' : '' }}>Low</option>
                </select>
            </div>
            <button type="submit" class="px-3 py-1.5 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700">Filtrar</button>
        </form>
        @if (($overdueTickets ?? collect())->isNotEmpty())
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Subject</th>
                    <th>Solicitante</th>
                    <th>Organização</th>
                    <th>Prazo</th>
                    <th>Dias atraso</th>
                    <th>Status</th>
                    <th>Prioridade</th>
                    <th>Severidade</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($overdueTickets as $ticket)
                @php $analysis = $ticket->analysis->first(); @endphp
                <tr>
                    <td><a href="{{ route('tickets.show', $ticket) }}" class="text-blue-600 hover:underline">#{{ $ticket->zd_id }}</a></td>
                    <td>{{ Str::limit($ticket->subject, 50) }}</td>
                    <td class="text-sm">{{ $ticket->requester?->name ?? $ticket->requester?->email ?? '—' }}</td>
                    <td class="text-sm">{{ $ticket->organization?->name ?? '—' }}</td>
                    <td>{{ $ticket->due_at?->format('d/m/y') ?? '—' }}</td>
                    <td>{{ $ticket->days_overdue ?? '—' }}d</td>
                    <td><span class="badge badge-{{ $ticket->status }}">{{ $ticket->status }}</span></td>
                    <td>{{ $ticket->priority ?? '—' }}</td>
                    <td>
                        @if ($analysis?->severity)
                            <span class="badge badge-{{ $analysis->severity }}">{{ $analysis->severity }}</span>
                        @else
                            —
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @php
            $overdueLinkParams = ['overdue' => 1];
            $f = $overdueFilters ?? [];
            if (!empty($f['overdue_org'])) $overdueLinkParams['org'] = $f['overdue_org'];
            if (!empty($f['overdue_requester'])) $overdueLinkParams['requester'] = $f['overdue_requester'];
            if (!empty($f['overdue_status'])) $overdueLinkParams['status'] = $f['overdue_status'];
            if (!empty($f['overdue_priority'])) $overdueLinkParams['priority'] = $f['overdue_priority'];
            if (!empty($f['overdue_severity'])) $overdueLinkParams['severity'] = $f['overdue_severity'];
        @endphp
        <p class="text-sm text-gray-500 mt-2">
            <a href="{{ route('tickets.index', $overdueLinkParams) }}" class="text-blue-600 hover:underline">Ver todos os atrasados</a>
        </p>
        @else
        <p class="text-gray-500 py-4">Nenhum ticket atrasado{{ !empty(array_filter($overdueFilters ?? [])) ? ' com os filtros aplicados' : '' }}.</p>
        @endif
    </div>

    {{-- Fila alta severidade --}}
    <div class="card mb-8">
        <h2 class="text-lg font-semibold mb-4">Fila alta severidade</h2>
        @if ($highSeverityTickets->isEmpty())
            <p class="text-gray-500">Nenhum ticket de alta severidade na fila.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Severity</th>
                        <th>Category</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($highSeverityTickets as $ticket)
                    @php $analysis = $ticket->analysis->first(); @endphp
                    <tr>
                        <td><a href="{{ route('tickets.show', $ticket) }}" class="text-blue-600 hover:underline">#{{ $ticket->zd_id }}</a></td>
                        <td>{{ Str::limit($ticket->subject, 50) }}</td>
                        <td><span class="badge badge-{{ $ticket->status }}">{{ $ticket->status }}</span></td>
                        <td>
                            @if ($analysis)
                                <span class="badge badge-{{ $analysis->severity }}">{{ $analysis->severity }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $analysis?->category ?? '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@else
    {{-- Cliente: visão simplificada --}}
    <div class="card mb-8">
        <h2 class="text-lg font-semibold mb-4">Meus tickets atrasados</h2>
        <form method="GET" class="mb-4 p-3 bg-slate-50 rounded-lg border border-slate-200 flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-0.5">Status</label>
                <select name="overdue_status" class="w-32 px-2 py-1.5 text-sm border border-slate-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <option value="new" {{ ($overdueFilters['overdue_status'] ?? '') === 'new' ? 'selected' : '' }}>Novo</option>
                    <option value="open" {{ ($overdueFilters['overdue_status'] ?? '') === 'open' ? 'selected' : '' }}>Aberto</option>
                    <option value="pending" {{ ($overdueFilters['overdue_status'] ?? '') === 'pending' ? 'selected' : '' }}>Pendente</option>
                    <option value="hold" {{ ($overdueFilters['overdue_status'] ?? '') === 'hold' ? 'selected' : '' }}>Aguardando</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-0.5">Prioridade</label>
                <select name="overdue_priority" class="w-28 px-2 py-1.5 text-sm border border-slate-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todas</option>
                    <option value="urgent" {{ ($overdueFilters['overdue_priority'] ?? '') === 'urgent' ? 'selected' : '' }}>Urgente</option>
                    <option value="high" {{ ($overdueFilters['overdue_priority'] ?? '') === 'high' ? 'selected' : '' }}>Alta</option>
                    <option value="normal" {{ ($overdueFilters['overdue_priority'] ?? '') === 'normal' ? 'selected' : '' }}>Normal</option>
                    <option value="low" {{ ($overdueFilters['overdue_priority'] ?? '') === 'low' ? 'selected' : '' }}>Baixa</option>
                </select>
            </div>
            <button type="submit" class="px-3 py-1.5 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700">Filtrar</button>
        </form>
        @if (($overdueTickets ?? collect())->isNotEmpty())
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Subject</th>
                    <th>Prazo</th>
                    <th>Dias atraso</th>
                    <th>Status</th>
                    <th>Prioridade</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($overdueTickets as $ticket)
                <tr>
                    <td><a href="{{ route('tickets.show', $ticket) }}" class="text-blue-600 hover:underline">#{{ $ticket->zd_id }}</a></td>
                    <td>{{ Str::limit($ticket->subject, 50) }}</td>
                    <td>{{ $ticket->due_at?->format('d/m/y') ?? '—' }}</td>
                    <td>{{ $ticket->days_overdue ?? '—' }}d</td>
                    <td><span class="badge badge-{{ $ticket->status }}">{{ $ticket->status }}</span></td>
                    <td>{{ $ticket->priority ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @php
            $overdueLinkParams = ['overdue' => 1];
            $f = $overdueFilters ?? [];
            if (!empty($f['overdue_status'])) $overdueLinkParams['status'] = $f['overdue_status'];
            if (!empty($f['overdue_priority'])) $overdueLinkParams['priority'] = $f['overdue_priority'];
        @endphp
        <p class="text-sm text-gray-500 mt-2">
            <a href="{{ route('tickets.index', $overdueLinkParams) }}" class="text-blue-600 hover:underline">Ver todos os atrasados</a>
        </p>
        @else
        <p class="text-gray-500 py-4">Nenhum ticket atrasado{{ !empty(array_filter($overdueFilters ?? [])) ? ' com os filtros aplicados' : '' }}.</p>
        @endif
    </div>

    @if (!empty($ticketsByDate))
        <div class="card mb-8">
            <h2 class="text-lg font-semibold mb-4">Meus tickets criados (últimos 30 dias)</h2>
            <div style="height: 200px;">
                <canvas id="ticketsByDateChart"></canvas>
            </div>
        </div>
    @endif

    <div class="card mb-8">
        <h2 class="text-lg font-semibold mb-4">Meus tickets prioritários</h2>
        @if ($highSeverityTickets->isEmpty())
            <p class="text-gray-500">Nenhum ticket de alta severidade.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Severity</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($highSeverityTickets as $ticket)
                    @php $analysis = $ticket->analysis->first(); @endphp
                    <tr>
                        <td><a href="{{ route('tickets.show', $ticket) }}" class="text-blue-600 hover:underline">#{{ $ticket->zd_id }}</a></td>
                        <td>{{ Str::limit($ticket->subject, 50) }}</td>
                        <td><span class="badge badge-{{ $ticket->status }}">{{ $ticket->status }}</span></td>
                        <td>
                            @if ($analysis)
                                <span class="badge badge-{{ $analysis->severity }}">{{ $analysis->severity }}</span>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endif

@if (!empty($ticketsByDate))
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('ticketsByDateChart');
    if (!ctx) return;
    const data = @json($ticketsByDate);
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: Object.keys(data),
            datasets: [{
                label: 'Tickets criados',
                data: Object.values(data),
                backgroundColor: 'rgba(59, 130, 246, 0.5)',
                borderColor: 'rgb(59, 130, 246)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            }
        }
    });
});
</script>
@endif
@endsection
