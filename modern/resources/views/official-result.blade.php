@extends('layouts.app')

@section('title', 'Réponse')

@section('content')
    <section class="panel">
        <h1>{{ $answer->correct ? 'Bravo, tu as trouvé' : "Faux, tu n'as pas trouvé" }}</h1>
        @if ($answer->image_storage_key && $answer->image_path === $answer->image_storage_source)
            <img class="quiz-image" src="{{ route('official-image', ['answer', $answer->legacy_id]) }}" alt="{{ $answer->image_alt ?? '' }}">
        @endif
        @if ($answer->explanation) <p>{{ $answer->explanation }}</p> @endif
        @if (! $answer->correct)
            <p>Vous devez recommencer depuis le début.</p>
            <form action="{{ route('official-quiz.start', $quizId) }}" method="post">@csrf <button type="submit">Recommencer</button></form>
        @elseif ($hasNext)
            <form action="{{ route('official-quiz.next', $quizId) }}" method="post">@csrf <button type="submit">Question suivante</button></form>
        @else
            <p>Ce quiz est terminé.</p>
            @if ($resultRecorded) <p>Votre résultat est enregistré dans votre profil.</p> @endif
            <a href="{{ route('catalogue') }}">Retour au catalogue</a>
        @endif
    </section>
@endsection
