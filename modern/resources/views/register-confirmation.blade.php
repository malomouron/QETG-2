@extends('layouts.app')

@section('title', 'Confirmation de l’inscription')

@section('content')
    <section class="panel">
        <h1>Confirmation de l’inscription</h1>
        <p>Confirmez votre inscription ou refusez-la si vous n'en êtes pas à l'origine.</p>
        @error('decision') <p class="notice error" role="alert">{{ $message }}</p> @enderror
        <form action="{{ route('register.confirm', ['token' => $token]) }}" method="post">
            @csrf
            <button type="submit" name="decision" value="confirm">Confirmer</button>
            <button type="submit" name="decision" value="decline">Refuser</button>
        </form>
    </section>
@endsection
