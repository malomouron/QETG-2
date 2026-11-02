@extends('layouts.app')

@section('title', 'Profil')

@section('content')
    <h1>Profil de {{ auth()->user()->name }}</h1>
    <p><a href="{{ route('profile.edit') }}">Modifier mes informations ou mon mot de passe</a></p>
    <p><a href="{{ route('subscriptions.index') }}">Mes abonnements</a></p>
    <p class="notice">Cette page présente uniquement les résultats officiels importés ou réalisés sur cette version de recette.</p>
    <h2>Quiz réalisés et favoris</h2>
    <div class="catalogue">
        @forelse ($results as $result)
            <article class="quiz-card">
                <h3><a href="{{ route('official-quiz.show', $result->legacy_id) }}">{{ $result->name }}</a></h3>
                @if ($result->legacy_date) <p>Date historique : {{ $result->legacy_date }}</p> @endif
                @if ($result->completed_at) <p>Réalisé le : {{ $result->completed_at }}</p> @endif
                <form action="{{ route('profile.favorite', $result->legacy_id) }}" method="post">
                    @csrf
                    <input type="hidden" name="favorite" value="{{ $result->favorite ? '0' : '1' }}">
                    <button type="submit">{{ $result->favorite ? 'Retirer des favoris' : 'Ajouter aux favoris' }}</button>
                </form>
            </article>
        @empty
            <p>Aucun résultat officiel pour le moment.</p>
        @endforelse
    </div>
    {{ $results->links('pagination.simple') }}
@endsection
