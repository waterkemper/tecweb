@extends('layouts.app')

@section('title', 'Organizações - Admin')

@section('content')
<h1 class="text-2xl font-bold mb-6">Organizações</h1>

<div class="mb-4 flex gap-4">
    <a href="{{ route('admin.users.index') }}" class="text-blue-600 hover:underline">Usuários</a>
</div>

@if ($organizations->isNotEmpty())
<table>
    <thead>
        <tr>
            <th>Nome</th>
            <th>Domínios</th>
            <th>Usuários</th>
            <th>Tickets</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @foreach ($organizations as $org)
        <tr>
            <td>{{ $org->name ?? '—' }}</td>
            <td class="text-sm">{{ $org->domain_names ? implode(', ', $org->domain_names) : '—' }}</td>
            <td>
                <a href="{{ route('admin.organizations.users', $org) }}" class="text-blue-600 hover:underline">{{ $org->zd_users_count ?? 0 }}</a>
            </td>
            <td>{{ $org->tickets_count ?? 0 }}</td>
            <td>
                <a href="{{ route('tickets.index', ['org' => $org->zd_id]) }}" class="text-blue-600 hover:underline text-sm">Ver tickets</a>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="mt-4">
    {{ $organizations->links() }}
</div>
@else
<p class="text-gray-500">Nenhuma organização encontrada. Execute o sync do Zendesk.</p>
@endif
@endsection
