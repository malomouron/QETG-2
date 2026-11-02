@extends('layouts.app')

@section('title', 'Modifier mon profil')

@section('content')
    <section class="panel">
        <h1>Modifier mon profil</h1>
        <p>Les changements ne sont pas synchronisés avec le site historique.</p>
        @if (session('status')) <p class="notice" role="status">{{ session('status') }}</p> @endif
        <form action="{{ route('profile.update') }}" method="post">
            @csrf
            @foreach (['login' => ['Login', $member->legacy_login], 'first_name' => ['Prénom', $member->legacy_first_name], 'last_name' => ['Nom', $member->legacy_last_name], 'email' => ['Adresse e-mail', $member->email]] as $name => [$label, $value])
                <label for="{{ $name }}">{{ $label }}</label>
                <input class="text-input" id="{{ $name }}" name="{{ $name }}" type="{{ $name === 'email' ? 'email' : 'text' }}" value="{{ old($name, $value) }}" maxlength="{{ $name === 'email' ? 255 : 50 }}" required>
                @error($name) <p class="notice error" role="alert">{{ $message }}</p> @enderror
            @endforeach
            <input type="hidden" name="newsletter" value="0">
            <label><input type="checkbox" name="newsletter" value="1" @checked(old('newsletter', $member->legacy_newsletter))> Recevoir la newsletter</label>
            <label for="profile-current-password">Mot de passe actuel</label>
            <input class="text-input" id="profile-current-password" name="current_password" type="password" autocomplete="current-password" required>
            @error('current_password') <p class="notice error" role="alert">{{ $message }}</p> @enderror
            <button type="submit">Enregistrer</button>
        </form>
    </section>
    <section class="panel">
        <h2>Modifier le mot de passe</h2>
        <form action="{{ route('profile.password') }}" method="post">
            @csrf
            <label for="password-current">Mot de passe actuel</label>
            <input class="text-input" id="password-current" name="current_password" type="password" autocomplete="current-password" required>
            <label for="password-new">Nouveau mot de passe</label>
            <input class="text-input" id="password-new" name="password" type="password" minlength="8" maxlength="1024" autocomplete="new-password" required>
            <label for="password-confirm">Confirmer le nouveau mot de passe</label>
            <input class="text-input" id="password-confirm" name="password_confirmation" type="password" minlength="8" maxlength="1024" autocomplete="new-password" required>
            @error('current_password') <p class="notice error" role="alert">{{ $message }}</p> @enderror
            @error('password') <p class="notice error" role="alert">{{ $message }}</p> @enderror
            <button type="submit">Modifier le mot de passe</button>
        </form>
    </section>
    <section class="panel">
        <h2>Supprimer mon compte sur cette version</h2>
        <p>Cette action supprime aussi vos résultats et progressions sur cette version. Elle ne supprime pas votre compte sur l'ancien site.</p>
        <form action="{{ route('profile.destroy') }}" method="post">
            @csrf
            <label for="delete-current">Mot de passe actuel</label>
            <input class="text-input" id="delete-current" name="current_password" type="password" autocomplete="current-password" required>
            <label><input type="checkbox" name="confirmation" value="1" required> Je confirme la suppression de mon compte sur cette version.</label>
            @error('current_password') <p class="notice error" role="alert">{{ $message }}</p> @enderror
            @error('confirmation') <p class="notice error" role="alert">{{ $message }}</p> @enderror
            <p><button type="submit">Supprimer mon compte</button></p>
        </form>
    </section>
@endsection
