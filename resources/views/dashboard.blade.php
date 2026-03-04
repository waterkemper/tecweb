@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<h1 class="text-2xl font-bold mb-6">tecDESK Dashboard</h1>

{{-- Cards de resumo --}}
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
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
