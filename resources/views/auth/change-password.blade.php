@extends('layouts.app')

@section('title', 'Alterar senha')

@section('content')
<h1 class="text-2xl font-bold mb-6">Alterar senha</h1>

@if (session('status'))
    <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm">
        {{ session('status') }}
    </div>
@endif

@if ($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800 text-sm">
        @foreach ($errors->all() as $error)
            <p class="m-0">{{ $error }}</p>
        @endforeach
    </div>
@endif

<form method="POST" action="{{ route('profile.password.update') }}" class="max-w-md">
    @csrf

    <div class="mb-4">
        <label for="current_password" class="block text-sm font-medium text-slate-600 mb-1">Senha atual</label>
        <input id="current_password" type="password" name="current_password" required autocomplete="current-password"
            class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            placeholder="••••••••">
    </div>

    <div class="mb-4">
        <label for="password" class="block text-sm font-medium text-slate-600 mb-1">Nova senha</label>
        <input id="password" type="password" name="password" required autocomplete="new-password"
            class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            placeholder="••••••••">
    </div>

    <div class="mb-6">
        <label for="password_confirmation" class="block text-sm font-medium text-slate-600 mb-1">Confirmar nova senha</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
            class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            placeholder="••••••••">
    </div>

    <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors shadow-sm">
        Alterar senha
    </button>
</form>
@endsection
