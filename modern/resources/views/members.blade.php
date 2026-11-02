@extends('layouts.app')

@section('title', 'Membres')

@section('content')
    <h1>Membres</h1>
    <form action="{{ route('members.index') }}" method="get">
        <label for="recherche">Rechercher un membre par login</label>
        <input class="text-input" id="recherche" name="recherche" type="search" maxlength="200" value="{{ $search }}">
        <button type="submit">Rechercher</button>
    </form>
    <div class="catalogue">
        @forelse ($members as $member)
            <article class="quiz-card"><h2><a href="{{ route('members.show', $member->id) }}">{{ $member->legacy_login ?? $member->name }}</a></h2></article>
        @empty
            <p>Aucun membre ne correspond à cette recherche.</p>
        @endforelse
    </div>
    {{ $members->links('pagination.simple') }}
@endsection
