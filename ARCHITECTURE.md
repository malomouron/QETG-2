# Architecture cible

Le site reste une application PHP historique rendue côté serveur. Cette arborescence prépare une migration progressive vers une architecture en couches ; elle ne déplace ni ne remplace les pages existantes à la racine.

## Dossiers

- `src/Domain` : règles métier et contrats indépendants de PHP web et de MySQL.
- `src/Application` : cas d'utilisation et objets de transfert ; orchestre le domaine.
- `src/Infrastructure` : adaptateurs concrets (MySQLi, e-mail, stockage, sécurité).
- `src/Presentation` : contrôleurs HTTP, middleware, requêtes et vues.
- `routes` : déclaration des routes lorsque le routage sera introduit.
- `resources` : vues et sources CSS/JavaScript.
- `bootstrap` : initialisation de l'application et de ses dépendances.
- `config` : configuration applicative non secrète ; les secrets restent dans l'environnement local.
- `tests` : les tests actuels restent en place et seront étendus avec chaque parcours migré.

## Sens des dépendances

`Presentation` et `Infrastructure` peuvent dépendre de `Application`, qui dépend de `Domain`. `Domain` ne dépend d'aucune autre couche. Les pages historiques peuvent appeler temporairement le nouveau code, mais les nouvelles fonctionnalités ne doivent pas recréer une seconde logique métier.

## Migration sans rupture

Les scripts PHP historiques, les téléversements et le document root Apache restent inchangés pour le moment. Migrer un parcours à la fois, conserver son URL et ses tests de caractérisation, puis seulement après validation envisager un `public/` dédié et adapter Apache/Docker. Ne pas placer de journaux, cache ou fichiers privés dans les dossiers actuellement servis par Apache ; définir d'abord un stockage protégé hors du document root.

Les dossiers vides restent des emplacements cibles. Le namespace PSR-4 `QETG\\` est mappé sur `src/` dans Composer ; régénérer l'autoloader avec Composer après l'ajout de classes. Les entités du domaine sont le premier modèle introduit, sans remplacement immédiat des accès SQL historiques.

Les interfaces `*RepositoryInterface` sont les contrats de persistance du domaine. Leurs adaptateurs `MySql*Repository` sont placés dans `src/Infrastructure/Persistence/MySQL` et mappent explicitement les noms historiques de tables/colonnes via le wrapper `db` existant ; les entités et contrats ne dépendent pas de MySQLi.

## Accueil et routage

Le front controller `index.php` délègue les routes `GET /`, `GET /index.php` et `GET /accueil` au contrôleur d'accueil. Les autres scripts PHP historiques restent accessibles directement ; `.htaccess` ne réécrit que les URL qui ne correspondent pas déjà à un fichier ou un dossier. Le contrôleur compose les données via les interfaces du domaine et rend `src/Presentation/View/home.php`. Le thème de l'accueil est isolé dans `resources/assets/css/home.css` et reprend les tons bleu `#3cb2ff`, anthracite `#3d3d3d` et les surfaces blanches du site.

Les parcours routés de compte utilisent `AccountController` : connexion, inscription avec confirmation e-mail, déconnexion, mise à jour du profil, avatar prédéfini ou téléversé, historique/favoris/succès/quiz créés, changement de mot de passe, réinitialisation par jeton et suppression du compte. Les suppressions de compte sont transactionnelles et refusées si le membre possède des quiz officiels. La migration SQL de réinitialisation doit être appliquée manuellement avant d’activer ce parcours sur une base existante ; voir `database/migrations/README.md`. Les scripts PHP historiques restent présents pour compatibilité, notamment les profils publics consultés via `profil.php?u=...`.

Le jeu des quiz officiels utilise également le routeur : `GET /quiz-officiel?id=…` affiche la présentation, `GET /quiz/question?id_quiz=…&numero_question=…` sert la question, et `POST /quiz/reponse` valide la réponse. Les anciens scripts `Quiz-presentation.php`, `question.php` et `reponse.php` restent disponibles durant la migration ; le catalogue et les nouveaux liens internes utilisent le parcours routé. La progression membre est persistée, la progression anonyme reste en session, une réponse incorrecte redémarre le quiz et une réussite finale enregistre l’historique/favori existant et vérifie les succès de quiz officiels.

## Assets et téléversements

- Les images partagées sont rangées sous `assets/images/community/` et les avatars fournis sous `assets/images/avatars/presets/`.
- Les fichiers téléversés par les membres sont des données persistantes séparées du code : `uploads/quizzes/` et `uploads/avatars/`. Leur contenu est ignoré par Git.
- Les volumes Compose conservent leurs noms `quiz_uploads` et `profile_uploads`, mais sont montés sous `uploads/`.
- `.htaccess` réécrit les anciens chemins `/commun/...`, `/image-profil/...` et `/quiz_perso/...` vers les nouveaux emplacements. Les valeurs `src` et `image_profil` existantes en base restent ainsi servables sans modification SQL.
- Les contrôleurs et styles PHP partagés (`commun/bandeau.php`, `commun/bandeau.css`, etc.) restent dans `commun/` pendant la migration des pages historiques ; seuls les fichiers images ont été déplacés.