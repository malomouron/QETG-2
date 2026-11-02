@extends('layouts.app')

@section('title', 'Connexion')

@section('content')
    <section class="panel">
        <h1>Connexion</h1>
        @if (session('status')) <p class="notice" role="status">{{ session('status') }}</p> @endif
        <p>Les comptes importés depuis une copie de l'ancien site peuvent se connecter ici.</p>
        <p class="notice">Les fonctionnalités de profil non encore migrées restent sur le site historique.</p>
        <form action="{{ route('login.submit') }}" method="post">
            @csrf
            <label for="login">Identifiant ou adresse e-mail</label>
            <input class="text-input" id="login" name="login" value="{{ old('login') }}" autocomplete="username" required>
            <label for="password">Mot de passe</label>
            <input class="text-input" id="password" name="password" type="password" autocomplete="current-password" required>
            @error('login') <p class="notice error" role="alert">{{ $message }}</p> @enderror
            @error('password') <p class="notice error" role="alert">{{ $message }}</p> @enderror
            <button type="submit">Se connecter</button>
        </form>
        <p><a href="{{ route('password.request') }}">Mot de passe oublié ?</a></p>
    </section>
@endsection
