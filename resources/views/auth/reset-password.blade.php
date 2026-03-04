@extends('layouts.guest')

@section('title', 'Redefinir senha')

@section('content')
<div class="login-card">
    <h1 class="login-brand">tecDESK</h1>
    <p class="login-subtitle">Defina sua nova senha</p>

    @if ($errors->any())
        <div class="login-alert login-alert-error">
            @foreach ($errors->all() as $error)
                <p style="margin:0">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <div class="login-field">
            <label for="email" class="login-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required readonly
                class="login-input" style="background:#e2e8f0;cursor:not-allowed">
        </div>

        <div class="login-field">
            <label for="password" class="login-label">Nova senha</label>
            <input id="password" type="password" name="password" required autocomplete="new-password"
                placeholder="••••••••" class="login-input">
        </div>

        <div class="login-field">
            <label for="password_confirmation" class="login-label">Confirmar senha</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                placeholder="••••••••" class="login-input">
        </div>

        <button type="submit" class="login-btn">Redefinir senha</button>
    </form>

    <a href="{{ route('login') }}" class="login-link">Voltar ao login</a>
</div>
@endsection
