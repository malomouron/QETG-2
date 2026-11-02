@extends('layouts.app')

@section('title', 'Accueil')

@section('content')
    <section class="panel">
        <h1>Quiz En Tout Genre</h1>
        <p>La nouvelle application est en cours de construction.</p>
        <p class="notice">Les quiz et les comptes restent disponibles sur le site historique pendant la migration.</p>
        <p><a href="{{ route('catalogue') }}">Consulter le catalogue officiel</a></p>
    </section>
@endsection
