@extends('layouts.app')

@section('title', $quiz->name)

@section('content')
    <section class="panel">
        <h1>{{ $quiz->name }}</h1>
        @if ($quiz->image_storage_key && $quiz->image_path === $quiz->image_storage_source)
            <img class="quiz-image" src="{{ route('official-image', ['quiz', $quiz->legacy_id]) }}" alt="{{ $quiz->image_alt ?? '' }}">
        @endif
        <p>{{ $quiz->description }}</p>
        <p>{{ $questionCount }} question(s). Une mauvaise réponse fait recommencer le quiz.</p>
        @auth
            <p class="notice">Votre progression et votre résultat sont enregistrés sur cette version de recette.</p>
        @else
            <p class="notice">En tant que visiteur, votre résultat ne sera pas enregistré.</p>
        @endauth
        @error('quiz') <p class="notice error" role="alert">{{ $message }}</p> @enderror
        @if ($progress)
            <form action="{{ route('official-quiz.resume', $quiz->legacy_id) }}" method="post">
                @csrf
                <button type="submit">Reprendre à la question {{ $progress->number }}</button>
            </form>
        @endif
        <form action="{{ route('official-quiz.start', $quiz->legacy_id) }}" method="post">
            @csrf
            <button type="submit" @disabled($questionCount === 0)>{{ $progress ? 'Recommencer au début' : 'Commencer le quiz' }}</button>
        </form>
        <p><a href="{{ route('catalogue') }}">Retour au catalogue</a></p>
    </section>
@endsection
