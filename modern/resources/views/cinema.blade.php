@extends('layouts.app')

@section('title', 'Sortie Ciné')

@section('content')
    <h1>Sortie Ciné</h1>
    <p>Cette sélection reprend les deux films de la page historique ; elle n'est pas actualisée automatiquement.</p>
    <p class="notice">Afficher une vidéo charge le lecteur Allociné : votre navigateur se connectera alors à ce service externe.</p>
    <section class="panel" id="fille">
        <h2>Un tour chez ma fille</h2>
        <p>Une mère s'installe plus longtemps que prévu chez sa fille.</p>
        <p><a href="https://www.allocine.fr/film/fichefilm_gen_cfilm=273138.html" target="_blank" rel="noopener noreferrer">Fiche du film</a> · <a href="https://www.allocine.fr/video/player_gen_cmedia=19587387&amp;cfilm=273138.html" target="_blank" rel="noopener noreferrer">Teaser sur Allociné</a></p>
        @if ($video === 'fille')
            <iframe class="cinema-video" src="https://player.allocine.fr/19587387.html" title="Teaser d'Un tour chez ma fille" referrerpolicy="no-referrer" allowfullscreen></iframe>
        @else
            <a href="{{ route('cinema', ['video' => 'fille']) }}#fille">Afficher le teaser</a>
        @endif
    </section>
    <section class="panel" id="colere">
        <h2>Un homme en colère</h2>
        <p>Un nouveau convoyeur révèle une précision surprenante lors d'une attaque.</p>
        <p><a href="https://www.allocine.fr/film/fichefilm_gen_cfilm=242600.html" target="_blank" rel="noopener noreferrer">Fiche du film</a> · <a href="https://www.allocine.fr/video/player_gen_cmedia=19589615&amp;cfilm=242600.html" target="_blank" rel="noopener noreferrer">Bande-annonce sur Allociné</a></p>
        @if ($video === 'colere')
            <iframe class="cinema-video" src="https://player.allocine.fr/19589615.html" title="Bande-annonce d'Un homme en colère" referrerpolicy="no-referrer" allowfullscreen></iframe>
        @else
            <a href="{{ route('cinema', ['video' => 'colere']) }}#colere">Afficher la bande-annonce</a>
        @endif
    </section>
@endsection
