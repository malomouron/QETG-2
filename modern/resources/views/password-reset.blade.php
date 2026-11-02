@extends('layouts.app')

@section('title', 'Nouveau mot de passe')

@section('content')
    <section class="panel">
        <h1>Choisir un nouveau mot de passe</h1>
        <form action="{{ route('password.update', ['user' => $user, 'token' => $token]) }}" method="post">
            @csrf
            <label for="password">Nouveau mot de passe</label>
            <input class="text-input" type="password" id="password" name="password" required minlength="8" maxlength="1024" autocomplete="new-password">
            <label for="password_confirmation">Confirmer le mot de passe</label>
            <input class="text-input" type="password" id="password_confirmation" name="password_confirmation" required minlength="8" maxlength="1024" autocomplete="new-password">
            @error('password') <p class="notice error" role="alert">{{ $message }}</p> @enderror
            <button type="submit">Modifier le mot de passe</button>
        </form>
    </section>
@endsection
