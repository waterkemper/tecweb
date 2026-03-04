@extends('layouts.guest')

@section('title', 'Login')

@section('content')
<div class="login-card">
    <h1 class="login-brand">tecDESK</h1>
    <p class="login-subtitle">Acesse sua conta</p>

    @if (session('status'))
        <div class="login-alert login-alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="login-alert login-alert-error">
            @foreach ($errors->all() as $error)
                <p style="margin:0">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="login-field">
            <label for="email" class="login-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                placeholder="seu@email.com" class="login-input">
        </div>

        <div class="login-field">
            <label for="password" class="login-label">Senha</label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                placeholder="••••••••" class="login-input">
        </div>

        <div style="display:flex;align-items:center;margin-bottom:1.5rem">
            <input id="remember" type="checkbox" name="remember" class="login-checkbox">
            <label for="remember" class="login-checkbox-label">Lembrar-me</label>
        </div>

        <button type="submit" class="login-btn">Entrar</button>

        <a href="{{ route('password.request') }}" class="login-link">Esqueci minha senha</a>
    </form>
</div>
@endsection
