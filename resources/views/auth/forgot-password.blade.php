@extends('layouts.guest')

@section('title', 'Esqueci minha senha')

@section('content')
<div class="login-card">
    <h1 class="login-brand">tecDESK</h1>
    <p class="login-subtitle">Recuperar senha</p>

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

    <p class="login-text">Informe seu email e enviaremos um link para redefinir sua senha.</p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="login-field">
            <label for="email" class="login-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                placeholder="seu@email.com" class="login-input">
        </div>

        <button type="submit" class="login-btn">Enviar link de recuperação</button>
    </form>

    <a href="{{ route('login') }}" class="login-link">Voltar ao login</a>
</div>
@endsection
