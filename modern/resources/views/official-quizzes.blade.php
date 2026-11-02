@extends('layouts.app')

@section('title', 'Catalogue officiel')

@section('content')
    <h1>Catalogue officiel</h1>
    <p class="notice">Les quiz officiels importés peuvent être essayés en tant que visiteur ou membre. Cette version ne synchronise pas ses résultats avec le site historique.</p>
    <form action="{{ route('catalogue') }}" method="get">
        <label for="recherche">Rechercher un quiz officiel</label>
        <input class="text-input" id="recherche" name="recherche" value="{{ $search }}" maxlength="200" type="search">
        <button type="submit">Rechercher</button>
    </form>
    <div class="catalogue">
        @forelse ($quizzes as $quiz)
            <article class="quiz-card">
                @if ($quiz->image_storage_key && $quiz->image_path === $quiz->image_storage_source)
                    <img class="quiz-image" src="{{ route('official-image', ['quiz', $quiz->legacy_id]) }}" alt="{{ $quiz->image_alt ?? '' }}">
                @endif
                <h2><a href="{{ route('official-quiz.show', $quiz->legacy_id) }}">{{ $quiz->name }}</a></h2>
                <p>{{ $quiz->description }}</p>
            </article>
        @empty
            <p>{{ $search !== '' ? 'Aucun quiz officiel ne correspond à votre recherche.' : 'Aucun quiz officiel importé.' }}</p>
        @endforelse
    </div>
    {{ $quizzes->links('pagination.simple') }}
@endsection
