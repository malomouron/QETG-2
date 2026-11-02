@extends('layouts.app')

@section('title', 'Inscription')

@section('content')
    <section class="panel">
        <h1>Inscription</h1>
        <p>Un message de confirmation sera envoyé avant la création du compte sur cette version de recette.</p>
        @if (session('status')) <p class="notice" role="status">{{ session('status') }}</p> @endif
        <form action="{{ route('register.submit') }}" method="post">
            @csrf
            @foreach (['login' => 'Login', 'last_name' => 'Nom', 'first_name' => 'Prénom', 'email' => 'Adresse e-mail'] as $name => $label)
                <label for="{{ $name }}">{{ $label }}</label>
                <input class="text-input" id="{{ $name }}" name="{{ $name }}" type="{{ $name === 'email' ? 'email' : 'text' }}" value="{{ old($name) }}" maxlength="{{ $name === 'email' ? 255 : 50 }}" required>
                @error($name) <p class="notice error" role="alert">{{ $message }}</p> @enderror
            @endforeach
            <label for="password">Mot de passe</label>
            <input class="text-input" id="password" name="password" type="password" minlength="8" maxlength="1024" autocomplete="new-password" required>
            <label for="password_confirmation">Confirmer le mot de passe</label>
            <input class="text-input" id="password_confirmation" name="password_confirmation" type="password" minlength="8" maxlength="1024" autocomplete="new-password" required>
            @error('password') <p class="notice error" role="alert">{{ $message }}</p> @enderror
            <label><input type="checkbox" name="newsletter" value="1" @checked(old('newsletter'))> Recevoir la newsletter</label>
            @error('newsletter') <p class="notice error" role="alert">{{ $message }}</p> @enderror
            <p><button type="submit">S'inscrire</button></p>
        </form>
    </section>
@endsection
