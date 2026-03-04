@extends('layouts.app')

@section('title', 'Editar Usuário - Admin')

@section('content')
<h1 class="text-2xl font-bold mb-6">Editar usuário</h1>

<div class="mb-4">
    <a href="{{ route('admin.users.index') }}" class="text-blue-600 hover:underline">← Voltar</a>
</div>

<div class="card max-w-xl">
    <p class="mb-2"><strong>Nome:</strong> {{ $user->name }}</p>
    <p class="mb-2"><strong>Email:</strong> {{ $user->email }}</p>
    <p class="mb-2"><strong>Role:</strong> {{ $user->role }}</p>
    <p class="mb-4"><strong>Organização (Zendesk):</strong> {{ $user->zdUser?->organization?->name ?? '—' }}</p>

    <form method="POST" action="{{ route('admin.users.update', $user) }}">
        @csrf
        @method('PUT')

        <div class="mb-4">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="hidden" name="can_view_org_tickets" value="0">
                <input type="checkbox" name="can_view_org_tickets" value="1"
                    {{ $user->can_view_org_tickets ? 'checked' : '' }}>
                <span>Pode visualizar todos os tickets da sua organização (gerente)</span>
            </label>
            <p class="text-sm text-gray-500 mt-1">Quando ativado, o usuário vê todos os tickets da organização dele no Zendesk.</p>
        </div>

        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Salvar</button>
    </form>
</div>
@endsection
