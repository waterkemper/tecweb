@extends('layouts.app')

@section('title', 'Usuários - ' . ($organization->name ?? 'Org') . ' - Admin')

@section('content')
<h1 class="text-2xl font-bold mb-6">Usuários da organização: {{ $organization->name ?? '—' }}</h1>

<div class="mb-4 flex gap-4">
    <a href="{{ route('admin.organizations.index') }}" class="text-blue-600 hover:underline">← Organizações</a>
    <a href="{{ route('admin.users.index') }}" class="text-blue-600 hover:underline">Todos os usuários</a>
</div>

@if ($zdUsers->isNotEmpty())
<table>
    <thead>
        <tr>
            <th>Nome</th>
            <th>Email</th>
            <th>Role (ZD)</th>
            <th>Conta app</th>
            <th>Vê org</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @foreach ($zdUsers as $zdUser)
        @php
            $appUser = $appUsersByZdUserId->get($zdUser->id);
        @endphp
        <tr>
            <td>{{ $zdUser->name ?? '—' }}</td>
            <td>{{ $zdUser->email ?? '—' }}</td>
            <td><span class="badge">{{ $zdUser->role ?? '—' }}</span></td>
            <td>{{ $appUser ? 'Sim' : 'Não' }}</td>
            <td>{{ $appUser && $appUser->can_view_org_tickets ? 'Sim' : 'Não' }}</td>
            <td>
                @if ($appUser)
                    <a href="{{ route('admin.users.edit', $appUser) }}" class="text-blue-600 hover:underline text-sm">Editar</a>
                @else
                    <span class="text-gray-400 text-sm">—</span>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="mt-4">
    {{ $zdUsers->links() }}
</div>
@else
<p class="text-gray-500">Nenhum usuário encontrado nesta organização.</p>
@endif
@endsection
