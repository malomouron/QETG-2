<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class InformationController extends Controller
{
    public function help(Request $request): View
    {
        $validated = $request->validate(['recherche' => ['nullable', 'string', 'max:200']]);
        $search = trim($validated['recherche'] ?? '');
        $articles = collect([
            ['title' => 'Jouer à un quiz officiel', 'body' => 'Ouvrez le catalogue, choisissez un quiz et commencez. Une mauvaise réponse impose de recommencer au début.', 'keywords' => 'question réponse catalogue jouer'],
            ['title' => 'Reprendre un quiz', 'body' => 'Connectez-vous pour enregistrer votre progression, puis choisissez Reprendre sur la présentation du quiz.', 'keywords' => 'progression question membre'],
            ['title' => 'Compte et connexion', 'body' => 'Inscrivez-vous puis confirmez votre adresse avec le lien reçu. Un compte importé peut aussi se connecter avec son login ou son e-mail.', 'keywords' => 'inscription compte adresse mail membre'],
            ['title' => 'Mot de passe oublié', 'body' => 'Utilisez le lien Mot de passe oublié sur la page de connexion. Si une adresse est partagée, indiquez votre login unique.', 'keywords' => 'réinitialisation mail e-mail connexion'],
            ['title' => 'Modifier ou supprimer son compte', 'body' => 'Ouvrez Profil puis Modifier mes informations. Les changements sur cette version ne sont pas synchronisés avec le site historique.', 'keywords' => 'profil mot de passe suppression newsletter'],
        ]);
        if ($search !== '') {
            $articles = $articles->filter(fn ($article) => mb_stripos(implode(' ', $article), $search) !== false);
        }

        return view('help', ['articles' => $articles, 'search' => $search]);
    }

    public function cinema(Request $request): View
    {
        $input = $request->validate(['video' => ['nullable', 'in:fille,colere']]);

        return view('cinema', ['video' => $input['video'] ?? null]);
    }
}
