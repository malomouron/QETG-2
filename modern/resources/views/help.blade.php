@extends('layouts.app')

@section('title', 'Aide')

@section('content')
    <h1>Aide</h1>
    <form action="{{ route('help') }}" method="get">
        <label for="recherche">Rechercher dans l'aide</label>
        <input class="text-input" id="recherche" name="recherche" type="search" maxlength="200" value="{{ $search }}">
        <button type="submit">Rechercher</button>
    </form>
    @forelse ($articles as $article)
        <section class="panel">
            <h2>{{ $article['title'] }}</h2>
            <p>{{ $article['body'] }}</p>
        </section>
    @empty
        <p>Aucune réponse ne correspond à cette recherche.</p>
    @endforelse
@endsection
