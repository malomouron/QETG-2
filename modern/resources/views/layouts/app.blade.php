<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Quiz En Tout Genre') - QETG</title>
    <link rel="stylesheet" href="{{ asset('css/qetg.css') }}">
</head>
<body>
    <header class="site-header">
        <div class="site-header-inner">
            <a href="{{ url('/') }}">Quiz En Tout Genre</a>
            <nav aria-label="Navigation principale">
                <a href="{{ route('catalogue') }}">Catalogue officiel</a>
                <a href="{{ route('members.index') }}">Membres</a>
                <a href="{{ route('help') }}">Aide</a>
                <a href="{{ route('cinema') }}">Sortie Ciné</a>
                @auth
                    <a href="{{ route('profile') }}">Profil</a>
                    @if (auth()->user()->is_admin) <a href="{{ route('admin.reports') }}">Modération</a> @endif
                    <form class="inline-form" action="{{ route('logout') }}" method="post">
                        @csrf
                        <button class="nav-button" type="submit">Déconnexion</button>
                    </form>
                @else
                    <a href="{{ route('login') }}">Connexion</a>
                    <a href="{{ route('register') }}">Inscription</a>
                @endauth
            </nav>
        </div>
    </header>
    <main>
        @yield('content')
    </main>
    <footer class="site-footer">QETG : nouvelle version en cours de construction.</footer>
</body>
</html>
