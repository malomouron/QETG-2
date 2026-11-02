@extends('layouts.app')

@section('title', 'Mot de passe oublié')

@section('content')
    <section class="panel">
        <h1>Mot de passe oublié</h1>
        <p>Indiquez votre login ou votre adresse e-mail. Si plusieurs comptes partagent cette adresse, utilisez votre login.</p>
        @if (session('status')) <p class="notice" role="status">{{ session('status') }}</p> @endif
        <form action="{{ route('password.email') }}" method="post">
            @csrf
            <label for="login">Login ou adresse e-mail</label>
            <input class="text-input" id="login" name="login" value="{{ old('login') }}" required maxlength="255" autocomplete="username">
            @error('login') <p class="notice error" role="alert">{{ $message }}</p> @enderror
            <button type="submit">Envoyer le lien</button>
        </form>
    </section>
@endsection
