@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<h1 class="text-2xl font-bold mb-6">Zendesk AI Support Dashboard</h1>

<div class="grid mb-8">
    <div class="stat-card">
        <div class="text-sm text-gray-600">New</div>
        <div class="stat-value">{{ $newCount }}</div>
    </div>
    <div class="stat-card">
        <div class="text-sm text-gray-600">Open</div>
        <div class="stat-value">{{ $openCount }}</div>
    </div>
    <div class="stat-card">
        <div class="text-sm text-gray-600">Pending / Waiting</div>
        <div class="stat-value">{{ $waitingCount }}</div>
    </div>
</div>

<div class="card">
    <h2 class="text-lg font-semibold mb-4">AI High-Severity Queue</h2>
    @if ($highSeverityTickets->isEmpty())
        <p class="text-gray-500">No high-severity tickets in queue.</p>
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
                <tr>
                    <td><a href="{{ route('tickets.show', $ticket) }}">#{{ $ticket->zd_id }}</a></td>
                    <td>{{ Str::limit($ticket->subject, 50) }}</td>
                    <td><span class="badge badge-{{ $ticket->status }}">{{ $ticket->status }}</span></td>
                    <td>
                        @if ($analysis = $ticket->analysis->first())
                            <span class="badge badge-{{ $analysis->severity }}">{{ $analysis->severity }}</span>
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $ticket->analysis->first()?->category ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
