# Architecture cible

Le site reste une application PHP rendue côté serveur. Les parcours migrés passent par le front controller et l’architecture en couches ; des adaptateurs légers conservent certaines anciennes URL.

## Dossiers

- `src/Domain` : règles métier et contrats indépendants de PHP web et de MySQL.
- `src/Application` : cas d'utilisation et objets de transfert ; orchestre le domaine.
- `src/Infrastructure` : adaptateurs concrets (MySQLi, e-mail, stockage, sécurité).
- `src/Presentation` : contrôleurs HTTP, middleware, requêtes et vues.
- `index.php` et `src/Presentation/Http/Router.php` : composition des dépendances et déclaration des routes HTTP.
- `src/Presentation/View` : vues PHP ; `assets/css/` et `assets/js/` : styles et scripts applicatifs.
- `bootstrap` : initialisation de l'application et de ses dépendances.
- `config` : configuration applicative non secrète ; les secrets restent dans l'environnement local.
- `tests` : les tests actuels restent en place et seront étendus avec chaque parcours migré.

## Sens des dépendances

`Presentation` et `Infrastructure` peuvent dépendre de `Application`, qui dépend de `Domain`. `Domain` ne dépend d'aucune autre couche. Les pages historiques peuvent appeler temporairement le nouveau code, mais les nouvelles fonctionnalités ne doivent pas recréer une seconde logique métier.

## Migration sans rupture

Le document root Apache et les téléversements restent en place. Migrer un parcours à la fois, préserver les URL utiles et les tests de caractérisation, puis seulement après validation envisager un `public/` dédié et adapter Apache/Docker. Ne pas placer de journaux, cache ou fichiers privés dans les dossiers actuellement servis par Apache ; définir d'abord un stockage protégé hors du document root.

Le namespace PSR-4 `QETG\\` est mappé sur `src/` dans Composer. Les nouvelles fonctionnalités utilisent les contrats et services des couches applicatives.

Les interfaces `*RepositoryInterface` sont les contrats de persistance du domaine. Leurs adaptateurs `MySql*Repository` sont placés dans `src/Infrastructure/Persistence/MySQL` et mappent explicitement les noms historiques de tables/colonnes via le service `MySqlConnection`. Celui-ci encapsule MySQLi, les requêtes préparées et les transactions ; `bootstrap/config.php` centralise la configuration. La classe globale historique `db` a été supprimée.

## Accueil et routage

Le front controller `index.php` délègue les routes `GET /`, `GET /index.php` et `GET /accueil` au contrôleur d'accueil. Il construit une fois chaque dépôt MySQL puis le partage entre les contrôleurs. `.htaccess` laisse passer les fichiers présents et transmet les chemins non reconnus au routeur, qui répond 404 lorsqu’aucune route n’existe. Les URL historiques `profil.php?u=…`, `signal.php?id=…` et `admin.php` disposent d’adaptateurs de compatibilité. Le contrôleur d’accueil compose les données via les interfaces du domaine et rend `src/Presentation/View/home.php`. Les styles partagés et les scripts applicatifs sont centralisés sous `assets/css/` et `assets/js/`.

Les parcours routés de compte utilisent `AccountController` : connexion, inscription avec confirmation e-mail, déconnexion, mise à jour du profil, avatar prédéfini ou téléversé, historique/favoris/succès/quiz créés, changement de mot de passe par `ChangeMemberPassword`, réinitialisation par jeton via `ResetMemberPassword` et suppression du compte. Les suppressions de compte sont transactionnelles et refusées si le membre possède des quiz officiels. La migration SQL de réinitialisation doit être appliquée manuellement avant d’activer ce parcours sur une base existante ; voir `database/migrations/README.md`.

Les profils publics sont servis par `GET /profil-public?id=…` avec `PublicProfileController`. L’ancienne URL `profil.php?u=…` la redirige ; sans identifiant elle redirige vers l’espace membre privé. Le profil public affiche uniquement les informations publiques du membre, l’historique de quiz visibles, ses quiz publics et ses succès.

Le signalement d’un profil utilise `POST /communaute/signalement`, un jeton CSRF et le cas d’usage `ReportMember`; un membre ne peut pas se signaler lui-même ni signaler plusieurs fois le même profil. L’ancienne URL `signal.php?id=…` redirige vers le profil et ne modifie plus la base sur GET.

La recherche est accessible sur `GET /recherche?search=…`. `SearchController` délègue à `SearchQuizzes`, puis charge les images des résultats via le dépôt d’images. La requête du dépôt est paramétrée et ne retourne que les quiz officiels et les quiz membres terminés et publics. Aucun adaptateur `search.php` n’est maintenu : cette ancienne URL n’est pas garantie.

La gestion des favoris du profil utilise `POST /compte/favori` et le cas d’usage `SetQuizFavorite`. L’action exige un compte connecté, un jeton CSRF valide et un résultat de quiz déjà existant ; elle ne modifie que le statut favori et préserve le score/la date. Les deux anciennes URLs GET de favoris ne font plus de mutation et redirigent vers le profil.

Les réponses aux commentaires utilisent `POST /interactions/reponse-commentaire`, le contrôleur `CommentReplyController` et le cas d’usage `SubmitCommentReply`. Le membre doit être connecté, le jeton CSRF est vérifié, et le cas d’usage contrôle l’existence du commentaire, la longueur du texte et l’adresse IP avant l’enregistrement. L’ancien script `reponse_commentaire.php` a été retiré ; le point d’entrée actuel est cette route POST.

La page de la communauté est servie par `GET /quiz-membres` avec `MemberQuizController` et `member-quizzes.php`. Elle regroupe le catalogue public, la recherche privée par identifiant, les quiz du membre, les classements, les appréciations et le suivi/découverte de membres. Les mises à jour d’abonnement passent par `POST /communaute/abonnement` et `SetMemberSubscription`. L’ancien fichier `quiz_utilisateur.php` n’est plus présent et aucune redirection de compatibilité n’est enregistrée.

Le jeu des quiz membres utilise `GET /quiz-membres/jouer?id=…` et `POST /quiz-membres/reponse`. `SubmitMemberQuiz` vérifie que chaque réponse appartient à la question correspondante, calcule le score et enregistre la première participation du membre dans `quiz_statistique`. La création et la modification se font sur `GET /quiz-membres/editeur` et `POST /quiz-membres/enregistrer` ; `SaveMemberQuiz` valide le contenu et persiste quiz/questions/réponses dans une transaction. Les difficultés présentées et validées par l’éditeur proviennent de `quiz_difficulte`, afin d’utiliser les identifiants réels de la base. Les images téléversées sont vérifiées par MIME et taille puis nommées aléatoirement sous `uploads/quizzes/`. Les anciens scripts `makeQuiz.php`, `poster_quiz.php`, `poster_quiz_action.php` et `deleteQuiz.php` ne sont pas maintenus comme adaptateurs.

Le jeu des quiz officiels utilise le routeur : `GET /quiz-officiel?id=…` affiche la présentation, `GET /quiz/question?id_quiz=…&numero_question=…` sert la question, et `POST /quiz/reponse` valide la réponse. Les anciens scripts `Quiz-presentation.php`, `question.php` et `reponse.php` ne sont pas présents ni maintenus en compatibilité. La progression membre est persistée, la progression anonyme reste en session, une réponse incorrecte redémarre le quiz et une réussite finale enregistre l’historique/favori existant et vérifie les succès de quiz officiels.

## Administration

`GET /administration` affiche le tableau de bord réservé aux administrateurs : statistiques, contrôles de santé opérationnels, fréquentation quotidienne sur sept jours, liste paginée des quiz officiels et comptes signalés. Le trafic ne compte que les GET des routes applicatives correspondantes (hors tableau d’administration) ; une même session PHP contribue au plus une session visiteuse par date, tandis que ses pages restent comptées. Ce chiffre estime des sessions, pas des personnes uniques ; seule la date et des totaux quotidiens sont persistés, sans IP, agent utilisateur ni identifiant de visiteur. Appliquez `database/migrations/202611230004_create_daily_traffic.sql` aux bases existantes pour activer ces métriques ; sans cette migration, le tableau affiche explicitement leur indisponibilité et les autres statistiques restent disponibles. La vue `src/Presentation/View/administration.php` utilise l’en-tête du site et l’éditeur de quiz partagé. La publication (`POST /administration/quiz/creer`), la suppression d’un quiz officiel (`POST /administration/quiz/supprimer`) et la suppression d’un compte signalé (`POST /administration/signalements/supprimer-compte`) exigent une autorisation administrateur et un jeton CSRF. `AdministrationRepositoryInterface` définit l’accès aux données ; `MySqlAdministrationRepository` exécute les mutations dans des transactions.

Apache répond 410 pour les anciens écrans et traitements sous `/admin/`, même si des fichiers subsistent après un déploiement. `GET /admin.php` répond 303 vers `/administration` sous le même préfixe de déploiement, sans retransmettre les paramètres ; les autres méthodes reçoivent une réponse 405. L’adaptateur ne charge ni session ni base de données.

## Assets et téléversements

- Les images partagées sont rangées sous `assets/images/community/` et les avatars fournis sous `assets/images/avatars/presets/`.
- Toutes les feuilles de style applicatives sont centralisées sous `assets/css/`; l’ancien dossier `resources/assets/` a été supprimé.
- Les fichiers téléversés par les membres sont des données persistantes séparées du code : `uploads/quizzes/` et `uploads/avatars/`. Leur contenu est ignoré par Git.
- Les volumes Compose conservent leurs noms `quiz_uploads` et `profile_uploads`, mais sont montés sous `uploads/`.
- `.htaccess` réécrit les anciens chemins d’images `/commun/...`, `/image-profil/...` et `/quiz_perso/...` vers les nouveaux emplacements. Les valeurs `src` et `image_profil` existantes en base restent ainsi servables sans modification SQL.
- Les bandeaux historiques vivent comme vues de compatibilité sous `src/Presentation/View/Shared/`; le dossier `commun/` a été supprimé.

## Services et fonctions historiques

Les règles applicatives réutilisées sont extraites en services dédiés. `PasswordHasher` utilise `password_hash`/`password_verify` (Argon2id si disponible, sinon l’algorithme sécurisé par défaut de PHP) et n’accepte plus MD5. Les colonnes de stockage sont `quiz_membre.password_hash` et `quiz_cle.cle_password_hash`; les anciens MD5 sont invalidés par la migration `202611230002_remove_legacy_md5_passwords.sql`, et les comptes concernés doivent réinitialiser leur mot de passe par e-mail. `RandomPasswordGenerator` génère les mots de passe temporaires, `ViewHelpers` centralise l’échappement HTML et la construction des URL d’assets, et `ApplicationLayout` rend le squelette HTML commun, le `<head>` responsive et ses métadonnées. Les vues réutilisent aussi les bandeaux et pied de page partagés sous `src/Presentation/View/Shared/`. L’ancien `functions.php` a été supprimé ; les pages procédurales encore actives chargent `bootstrap/legacy.php` pour la configuration et l’autoloader, tandis que les dépôts, commandes CLI et le front controller créent leur connexion avec `MySqlConnection`.

Les confirmations d’inscription et la réinitialisation de mot de passe sont gérées par `AccountController` avec `PendingRegistrationRepositoryInterface`, `PasswordResetRepositoryInterface` et `PhpMailerEmailSender`. Les anciens scripts d’inscription, de changement/réinitialisation de mot de passe et le transport `mail.php` ne sont plus utilisés. `PurgeExpiredRegistrations` s’exécute en tâche CLI via `bin/purge-expired-registrations.php`. La newsletter est gérée par `SendNewsletterCampaign` (commande CLI `bin/send-newsletter.php`) et `UnsubscribeFromNewsletter`; `GET /newsletter/desabonnement` affiche une confirmation et seul le POST signé + CSRF modifie la préférence. Les liens sont signés avec une clé `APP_PRIVATE_KEY` d’au moins 32 caractères.