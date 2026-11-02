@extends('layouts.app')

@section('title', 'Mes abonnements')

@section('content')
    <h1>Mes abonnements</h1>
    @if (session('status')) <p class="notice" role="status">{{ session('status') }}</p> @endif
    <div class="catalogue">
        @forelse ($members as $member)
            <article class="quiz-card">
                <h2><a href="{{ route('members.show', $member->id) }}">{{ $member->legacy_login ?? $member->name }}</a></h2>
                <form action="{{ route('subscriptions.update', $member->id) }}" method="post">
                    @csrf
                    <input type="hidden" name="action" value="unfollow">
                    <button type="submit">Se désabonner</button>
                </form>
            </article>
        @empty
            <p>Vous n'êtes abonné à aucun membre.</p>
        @endforelse
    </div>
    {{ $members->links('pagination.simple') }}
@endsection
