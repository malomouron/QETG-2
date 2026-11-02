@extends('layouts.app')

@section('title', 'Question '.$question->number)

@section('content')
    <section class="panel">
        <h1>Question {{ $question->number }} / {{ $questionCount }}</h1>
        @if ($question->introduction) <p>{{ $question->introduction }}</p> @endif
        @if ($question->difficulty) <p>Difficulté : {{ $question->difficulty }}</p> @endif
        @if ($question->image_storage_key && $question->image_path === $question->image_storage_source)
            <img class="quiz-image" src="{{ route('official-image', ['question', $question->legacy_id]) }}" alt="{{ $question->image_alt ?? '' }}">
        @endif
        <form action="{{ route('official-quiz.answer', $quizId) }}" method="post">
            @csrf
            <fieldset>
                <legend>{{ $question->text }}</legend>
                @foreach ($answers as $answer)
                    <label class="answer"><input type="radio" name="answer_id" value="{{ $answer->legacy_id }}" required> {{ $answer->text }}</label>
                @endforeach
            </fieldset>
            @error('answer_id') <p class="notice error" role="alert">{{ $message }}</p> @enderror
            <button type="submit">Répondre</button>
        </form>
        <p><a href="{{ route('official-quiz.show', $quizId) }}">Retour à la présentation</a></p>
    </section>
@endsection
