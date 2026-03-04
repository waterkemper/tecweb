@extends('layouts.app')

@section('title', 'Usuários - Admin')

@section('content')
<h1 class="text-2xl font-bold mb-6">Usuários</h1>

<div class="mb-4 flex gap-4">
    <a href="{{ route('admin.organizations.index') }}" class="text-blue-600 hover:underline">Organizações</a>
</div>

@if (session('success'))
    <p class="text-green-600 mb-4">{{ session('success') }}</p>
@endif

@if ($users->isNotEmpty())
<table>
    <thead>
        <tr>
            <th>Nome</th>
            <th>Email</th>
            <th>Role</th>
            <th>Organização</th>
            <th>Vê org</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @foreach ($users as $user)
        <tr>
            <td>{{ $user->name }}</td>
            <td>{{ $user->email }}</td>
            <td><span class="badge">{{ $user->role }}</span></td>
            <td class="text-sm">{{ $user->zdUser?->organization?->name ?? '—' }}</td>
            <td>{{ $user->can_view_org_tickets ? 'Sim' : 'Não' }}</td>
            <td>
                <a href="{{ route('admin.users.edit', $user) }}" class="text-blue-600 hover:underline text-sm">Editar</a>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="mt-4">
    {{ $users->links() }}
</div>
@else
<p class="text-gray-500">Nenhum usuário encontrado.</p>
@endif
@endsection
