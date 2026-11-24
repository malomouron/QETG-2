# Fonctionnalités de QETG par domaine métier

## Périmètre

Ce document décrit les fonctionnalités repérées dans le code PHP et le schéma `QETG.sql`. Il s'agit d'un inventaire statique : il ne garantit pas que chaque parcours fonctionne dans un environnement déployé ou avec une base de données correctement configurée.

Les principales catégories d'utilisateur visibles dans le code sont le visiteur, le membre connecté et le membre administrateur (`quiz_membre.admin = 1`). Les références de fichiers ci-dessous servent à retrouver les parcours et leur code.

## 1. Découverte et consultation des quiz

- La page d'accueil présente les quiz intégrés au site, avec pagination, illustrations, descriptions et indicateurs de popularité ; pour un membre connecté, elle affiche aussi le nombre de quiz réalisés (`index.php` et ses vues).
- La recherche de quiz est accessible depuis la navigation (`GET /recherche?search=…`) ; le parcours routé affiche les quiz officiels et les quiz membres terminés et publics. Aucun adaptateur `search.php` n’est maintenu.
- La page communautaire routée (`GET /quiz-membres`) propose le catalogue des quiz membres publics, la recherche par nom ou par identifiant pour les quiz privés, les quiz personnels (brouillons et publiés), les classements par score de réactions/vues, les quiz appréciés, les comptes suivis et la découverte/recherche de membres. L’ancien fichier `quiz_utilisateur.php` n’est plus présent et aucune redirection de compatibilité n’est enregistrée.
- Les quiz sont présentés avec leur nom, image, créateur, nombre de vues et réactions sur le catalogue communautaire routé. Le jeu membre affiche les propositions, le score, les bonnes réponses et les explications ; les participations des membres alimentent l’historique.
- La page d’aide (`GET /aide`) propose une FAQ filtrable côté navigateur et des liens vers les parcours d’assistance. La page de présentation du site est disponible sur `GET /a-propos`.

## 2. Participation aux quiz

- Le joueur officiel utilise `GET /quiz-officiel?id=…`, `GET /quiz/question?id_quiz=…&numero_question=…` et `POST /quiz/reponse`. Il affiche la progression et les retours corrects/incorrects ; les anciens scripts `Quiz-presentation.php`, `question.php` et `reponse.php` ne sont pas maintenus.
- Une question peut proposer plusieurs réponses, une difficulté, une image et un texte d’explication associé aux réponses (`QETG.sql`, `OfficialQuizController`).
- Les quiz créés par des membres se jouent sur `GET /quiz-membres/jouer?id=…` et se soumettent sur `POST /quiz-membres/reponse` ; le score, les bonnes réponses et les explications sont affichés par la vue du parcours.
- Pour les membres, les résultats des quiz officiels et des quiz de membres sont enregistrés pour l’historique dans `quiz_statistique`.

## 3. Création et gestion des quiz par les membres

- Un membre connecté peut créer et modifier un quiz sur les routes `/quiz-membres/editeur` et `/quiz-membres/enregistrer`. Les quiz, questions et réponses sont enregistrés via `SaveMemberQuiz` dans une transaction ; la suppression utilise une action POST avec CSRF du profil.
- L’éditeur prévoit un nom, une description, une image de présentation, 1 à 100 questions, une difficulté par question, 2 à 5 réponses, une réponse correcte et une explication facultative. Les couvertures sont contrôlées par MIME et limitées à 4 Mo.
- Le membre dispose de l’onglet « Mes quiz » dans `/quiz-membres`, qui permet de reprendre/modifier ses quiz et de supprimer un quiz publié ou brouillon.
- La publication et la visibilité reposent sur les champs `quizComplet`, `portee`, `id_quiz_user` et `id_user` de la table `quiz` (`QETG.sql`).

## 4. Comptes et profil membre

- Les parcours d'inscription, de connexion, de déconnexion et de confirmation d'adresse e-mail sont routés par `AccountController` et utilisent `PhpMailerEmailSender`.
- La connexion accepte un login ou une adresse e-mail. Le mot de passe oublié utilise un jeton de réinitialisation à durée limitée; les changements et réinitialisations utilisent `ChangeMemberPassword` et `ResetMemberPassword`.
- Les mots de passe sont stockés dans `quiz_membre.password_hash` et `quiz_cle.cle_password_hash`. `PasswordHasher` utilise Argon2id lorsqu'il est disponible, sinon l'algorithme sécurisé par défaut de PHP ; aucune connexion MD5 n'est acceptée. Après migration des bases existantes, les comptes qui avaient un ancien MD5 doivent réinitialiser leur mot de passe par e-mail.
- Un membre peut consulter et modifier son profil, son niveau, son expérience, ses succès et son historique sur `GET /compte/profil` (tables `quiz_membre_succes`, `quiz_succes`, `quiz_statistique`).
- Il peut également consulter le profil public d'un autre membre via `GET /profil-public?id=...` ; l’ancienne URL `profil.php?u=...` reste compatible par redirection.
- Le profil propose le choix d'un avatar fourni par le site ou le téléversement d'une image via `AccountController`.
- Les formulaires de modification des informations du compte et de suppression du compte sont présents dans l’espace membre routé.
- Le sélecteur de langue redirige vers Google Translate ; il ne s'agit pas d'un système de traduction interne.

## 5. Communauté et interactions

- Les quiz peuvent recevoir des appréciations positives ou négatives, avec affichage des compteurs et de la réaction courante ; les mutations utilisent `POST /interactions/reaction` et `ToggleQuizReaction`.
- Les membres peuvent marquer comme favoris les quiz effectués depuis leur profil ; l’action utilise `POST /compte/favori`, un jeton CSRF et le cas d’usage `SetQuizFavorite`. Les anciennes routes GET de favoris ne font pas de mutation.
- Le formulaire de commentaire de l'accueil est protégé par CSRF et peut envoyer une notification par e-mail. La validation reCAPTCHA s'active avec les deux clés Google ; sans clé, le formulaire fonctionne sans CAPTCHA, et avec une seule clé, seul l'envoi de nouveaux commentaires est indisponible. Les membres répondent avec `POST /interactions/reponse-commentaire`, protégé par CSRF et traité par `SubmitCommentReply`; les réponses sont affichées sous le commentaire correspondant.
- Le signalement d'un profil est disponible aux membres connectés via un formulaire POST protégé par CSRF (`/communaute/signalement`, table `quiz_signalement`). Un même membre ne peut signaler une cible qu'une seule fois ; l'ancienne URL `signal.php?id=…` redirige vers le profil sans écrire sur GET.
- Le suivi et le désabonnement depuis la page communautaire utilisent `POST /communaute/abonnement`, un jeton CSRF et le cas d’usage `SetMemberSubscription` (`quiz_subscription_membre`).
- La préférence newsletter est modifiable depuis le profil. `GET /newsletter/desabonnement` ouvre une confirmation et le désabonnement est appliqué au POST avec un jeton signé. L’envoi de campagne est disponible en CLI via `SendNewsletterCampaign` et `bin/send-newsletter.php`.
- Les membres peuvent consulter des classements de quiz par réactions et par vues dans l’onglet « Classements » de `/quiz-membres`.

## 6. Administration et modération

- Le tableau de bord `GET /administration` est réservé aux comptes dont `quiz_membre.admin` vaut `1`. Il affiche les totaux de membres, de quiz officiels et communautaires, de signalements et de participations terminées.
- Le catalogue paginé des quiz officiels permet de publier un quiz avec ses questions et réponses, ou de supprimer un quiz officiel après confirmation. Le tableau de bord ne propose pas de modification des quiz officiels existants.
- La liste paginée regroupe les signalements par compte. Après confirmation, un administrateur peut supprimer un compte signalé et ses contenus communautaires. Un compte administrateur ou propriétaire de quiz officiels ne peut pas être supprimé par ce parcours.
- Les actions de publication et de suppression utilisent des formulaires POST protégés par un jeton CSRF. L’ancien `GET /admin.php` redirige vers le tableau de bord ; les anciens écrans et formulaires du dossier `admin/` ne sont plus disponibles.

## 7. Contact, information et préférences

- Le site comprend des formulaires de commentaire/réponse, l’envoi de newsletter par commande CLI, les confirmations de compte et les réinitialisations de mot de passe via `PhpMailerEmailSender`.
- Les pages d’aide et de présentation du site sont accessibles par `GET /aide` et `GET /a-propos`.
- L'interface inclut une bannière de gestion des cookies avec Tarteaucitron (dépendance npm `tarteaucitronjs`). Le service Google reCAPTCHA n'est proposé que sur les pages affichant le widget.
- Un lien de don externe est proposé depuis la vue du profil membre.

## Configuration et limites de l'inventaire

- `QETG.sql` crée puis sélectionne une base nommée `test`. La valeur `$dbname` dans `config.inc.php` doit correspondre à la base réellement importée, ou le dump doit être adapté avant import.
- Le dump SQL contient des `DROP TABLE` avant la recréation de ses tables : réimporter ce fichier peut donc supprimer les données déjà présentes dans ces tables.
- Le dépôt ne contient pas `config.inc.php` (fichier local de configuration) ; les paramètres e-mail, base de données et services externes dépendent donc de l'installation.
- Les anciennes pages `aide-page.php`, `aide.php`, `search-aide.php`, `poster_question.php`, `poster_reponse.php` et `reponse_commentaire.php` ne sont pas présentes et ne sont pas maintenues comme adaptateurs. Les parcours actuels sont ceux des routes décrites plus haut.
- Certains liens historiques de traduction restent liés au site et au préfixe `/quiz/` d'origine. Les principaux liens locaux de navigation et de partage ont été rendus indépendants du nom du dossier, mais les autres chemins doivent encore être vérifiés.
- Les quiz officiels enregistrent et reprennent la progression des membres via `quiz_progression` dans le contrôleur routé des quiz officiels.
- Les noms de colonnes et les chemins présents dans la documentation décrivent l'implémentation actuelle, pas une spécification contractuelle. Les fonctions doivent être vérifiées sur une installation avec base et services configurés.
