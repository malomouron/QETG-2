@extends('layouts.app')

@section('title', 'Profil de '.$member->name)

@section('content')
    <h1>Profil de {{ $member->legacy_login ?? $member->name }}</h1>
    @if (session('status')) <p class="notice" role="status">{{ session('status') }}</p> @endif
    @error('action') <p class="notice error" role="alert">{{ $message }}</p> @enderror
    @error('report') <p class="notice error" role="alert">{{ $message }}</p> @enderror
    @auth
        @if ((int) auth()->id() !== (int) $member->id)
            <form action="{{ route('subscriptions.update', $member->id) }}" method="post">
                @csrf
                <input type="hidden" name="action" value="{{ $isFollowing ? 'unfollow' : 'follow' }}">
                <button type="submit">{{ $isFollowing ? 'Se désabonner' : "S'abonner" }}</button>
            </form>
            <form action="{{ route('members.report', $member->id) }}" method="post">
                @csrf
                <button type="submit">Signaler ce membre</button>
            </form>
        @endif
    @endauth
    <p class="notice">Seuls les résultats des quiz officiels présents sur cette version sont affichés.</p>
    <h2>Quiz réalisés et favoris</h2>
    <div class="catalogue">
        @forelse ($results as $result)
            <article class="quiz-card">
                <h3><a href="{{ route('official-quiz.show', $result->legacy_id) }}">{{ $result->name }}</a></h3>
                @if ($result->favorite) <p>Favori</p> @endif
                @if ($result->legacy_date) <p>Date historique : {{ $result->legacy_date }}</p> @endif
                @if ($result->completed_at) <p>Réalisé le : {{ $result->completed_at }}</p> @endif
            </article>
        @empty
            <p>Aucun résultat officiel pour le moment.</p>
        @endforelse
    </div>
    {{ $results->links('pagination.simple') }}
@endsection
