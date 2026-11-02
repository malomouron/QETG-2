# Nouveau socle QETG

Cette application Laravel est **en construction** et ne remplace pas encore le site historique. Les quiz, comptes et autres fonctionnalités restent sur l'ancien site. Ne dirigez pas les utilisateurs vers cette application comme remplacement.

## Démarrage local avec Docker Compose

Depuis ce dossier, copiez `.env.example` vers `.env`, puis adaptez les mots de passe locaux. La base du conteneur est distincte de celle du site historique et n'est pas exposée sur l'hôte.

```powershell
Copy-Item .env.example .env
docker compose build
docker compose run --rm --no-deps app composer install
docker compose run --rm --no-deps app php artisan key:generate
docker compose up -d
docker compose exec app php artisan migrate
```

Ouvrez `http://localhost:8000`. Les migrations créent les tables Laravel et une table pour l'aperçu du catalogue officiel ; elles n'importent pas les données QETG. Ne configurez pas cette application sur une base de production existante.

Pour arrêter les conteneurs sans supprimer la base locale : `docker compose down`. Le volume persiste entre les démarrages.

Les pages `/aide` et `/sortie-cine` sont disponibles dans la nouvelle interface. La sélection cinéma est historique et non actualisée ; les lecteurs vidéo externes ne sont chargés qu'après une action de l'utilisateur.

## Vérifications

```powershell
docker compose run --rm --no-deps app php artisan test
```

Les tests utilisent une base SQLite en mémoire.

## Premier import : catalogue officiel

Le catalogue `/catalogue` permet de rechercher par nom et d'essayer les quiz officiels importés ; les quiz membres ne sont pas encore inclus. La progression d'un visiteur ne dure que le temps de sa session ; celle d'un membre connecté et son résultat sont enregistrés dans la nouvelle base, sans synchronisation avec l'ancien site. Pour alimenter le catalogue, préparez une **copie isolée** de la base historique, accessible depuis le conteneur, et configurez les paramètres `LEGACY_DB_*` dans `.env` avec un compte MySQL autorisé uniquement à lire cette copie. Ne pointez pas vers la base de production.

Le nom d'hôte doit être accessible depuis le conteneur : `localhost` désigne le conteneur Laravel lui-même. Sur Docker Desktop, `host.docker.internal` peut désigner une base de recette hébergée sur votre machine. Après modification de `.env`, redémarrez l'application avec `docker compose restart app`.

```powershell
docker compose exec app php artisan qetg:import-official-quizzes --dry-run
docker compose exec app php artisan qetg:import-official-quizzes
```

La commande lit les quiz officiels associés à une image, comme l'accueil historique. Elle importe également les questions, réponses et références d'image. Elle ajoute ou met à jour les quiz sans supprimer les quiz précédemment importés ; pour chaque quiz présent dans la copie, les questions et réponses supprimées de la copie sont également retirées de la nouvelle base. Une partie en cours doit être recommencée si le quiz change lors d'un import. La source n'est jamais modifiée. La parité fonctionnelle reste à réaliser.

### Images des quiz officiels

Après l'import du catalogue, préparez une **copie isolée des fichiers historiques**, dont la racine contient par exemple `commun`. Ne montez pas le répertoire de production. Depuis `modern`, indiquez son chemin absolu dans PowerShell, puis montez cette copie en lecture seule uniquement pour l'import :

```powershell
$env:QETG_LEGACY_ASSET_ROOT = 'C:\chemin\vers\copie-fichiers'
docker compose -f compose.yml -f compose.legacy-images.example.yml run --rm --no-deps app php artisan qetg:import-official-images /legacy-assets --dry-run
docker compose -f compose.yml -f compose.legacy-images.example.yml run --rm --no-deps app php artisan qetg:import-official-images /legacy-assets
```

Le dossier est monté en lecture seule ; les images référencées sont copiées dans le stockage privé de Laravel. Les formats JPEG, PNG, GIF et WebP sont acceptés selon leur contenu, pas leur extension. Les références externes, fichiers absents, chemins hors de la copie et autres formats font échouer le lot explicitement. Les références locales commençant par `/quiz/` sont reconnues par défaut ; utilisez `--strip-prefix=autre` si l'ancien préfixe diffère, ou `--strip-prefix=` pour ne retirer aucun préfixe. L'import n'effectue aucune requête réseau.

Un réimport identique ne perturbe pas une partie ; une modification des images oblige à recommencer le quiz concerné. Si la référence d'une image change lors de l'import des quiz, l'ancienne image n'est plus affichée : relancez l'import des fichiers. Les fichiers copiés ne sont jamais supprimés automatiquement ; incluez le stockage privé dans vos sauvegardes. Une erreur pendant la copie peut laisser des fichiers non référencés, sans modifier les références en base.

## Import des comptes pour la recette

Avec la même copie isolée configurée en lecture seule, exécutez :

```powershell
docker compose exec app php artisan qetg:import-members --dry-run
docker compose exec app php artisan qetg:import-members
```

Cette opération copie les comptes et leurs hachages de mot de passe, ainsi que les champs historiques du profil. Protégez et anonymisez la copie de recette ; elle contient des données personnelles et ne doit pas être publiée. Les identifiants historiques sont conservés. Une fois importé, un compte n'est jamais écrasé par un nouvel import ; une synchronisation avec les changements ultérieurs de l'ancien site n'est pas implémentée.

La connexion accepte le login ou l'e-mail et migre le hachage MD5 après vérification réussie. En cas d'identifiants ou d'e-mails dupliqués, elle n'accepte la connexion que si un seul compte correspond au mot de passe. Les comptes importés ne reçoivent pas encore de permissions administrateur dans Laravel ; les autres éléments du profil restent sur l'ancien site. **Ne faites pas de bascule de production** : les deux applications ne synchronisent ni les comptes ni les changements de mot de passe.

L'inscription de recette crée une demande temporaire puis envoie un lien de confirmation ou de refus valable 24 heures ; le compte n'est créé qu'après confirmation. Le choix de newsletter est conservé, mais son envoi n'est pas encore migré. Un login ou e-mail déjà utilisé ou en attente ne peut pas être réservé. Un import ultérieur de comptes historiques refuse le lot si une nouvelle identité entre en conflit avec un compte créé ici ou une inscription en attente ; les doublons déjà présents dans la source restent conservés. Une protection anti-robot équivalente à celle de l'ancien formulaire reste à intégrer avant toute mise en ligne.

Depuis son profil de recette, un membre peut modifier son login, son nom, son prénom, son e-mail et sa préférence newsletter, changer son mot de passe ou supprimer son compte sur cette version, après vérification du mot de passe actuel. Les identifiants modifiés ne peuvent pas entrer en collision avec un compte ou une inscription en attente ; les doublons historiques inchangés sont conservés. Changer d'e-mail invalide les anciens liens de récupération ; la nouvelle adresse n'est pas encore reconfirmée par e-mail. La suppression efface les résultats et progressions de cette version et conserve un marqueur empêchant le réimport du même compte historique : elle ne supprime **pas** le compte ni ses données sur l'ancien site. Les imports de résultats ou progressions relatifs à un compte supprimé échoueront tant que la copie source n'est pas adaptée. Sauvegardez aussi les marqueurs de suppression ; leur migration refuse le retour arrière automatique. Le répertoire et les profils publics affichent les logins et résultats officiels, mais pas les données privées ; les avatars, les quiz membres, les succès et l'XP restent à migrer.

La récupération de mot de passe est disponible pour un identifiant correspondant à un seul compte. Si une adresse e-mail est partagée, utilisez un login unique ; une identité ambiguë ne reçoit pas de lien. Le lien expire après une heure et ne peut être utilisé qu'une fois. L'envoi utilise la configuration Laravel `MAIL_*` et les liens utilisent exclusivement `APP_URL` ; configurez une URL HTTPS correcte et un transport SMTP fiable avant toute utilisation réelle. Le transport `log` de l'exemple enregistre les messages et liens dans les journaux : il sert uniquement à la recette. Une erreur de transport annule l'émission du nouveau jeton. Lors du changement de mot de passe, les sessions enregistrées dans la table `sessions` sont supprimées ; utilisez `SESSION_DRIVER=database`, car les autres stockages de session ne sont pas révoqués par ce parcours.

Un mot de passe de plus de 72 octets est refusé pour un hachage bcrypt afin d'éviter sa troncature silencieuse. Si un ancien système l'avait déjà tronqué, réinitialisez-le avant la migration. Pour mettre à niveau un ancien MD5 de plus de 72 octets, PHP doit prendre en charge Argon2id.

La migration des colonnes historiques ne propose pas de retour arrière automatique après import des comptes ; avant toute migration réelle, sauvegardez et vérifiez une procédure de restauration.

## Import des résultats officiels

Après avoir importé **les quiz officiels puis les comptes** depuis la même copie, importez leurs résultats et favoris :

```powershell
docker compose exec app php artisan qetg:import-official-results --dry-run
docker compose exec app php artisan qetg:import-official-results
```

L'import refuse le lot si un résultat référence un membre ou un quiz officiel absent de la nouvelle base. Les résultats déjà présents ne sont pas écrasés, afin de préserver les favoris modifiés sur cette version. Les résultats de quiz membres, succès, niveaux et autres données du profil ne sont pas encore migrés.

La progression des quiz officiels peut également être importée après les quiz et les comptes :

```powershell
docker compose exec app php artisan qetg:import-official-progress --dry-run
docker compose exec app php artisan qetg:import-official-progress
```

Le lot est refusé si des membres ou questions nécessaires manquent, ou si plusieurs progressions existent pour un même membre et quiz. Une progression déjà présente dans la nouvelle base n'est pas écrasée. Si une question est retirée lors d'un import ultérieur, sa progression reste conservée mais ne peut plus être reprise ; le joueur doit recommencer le quiz.

## Abonnements aux membres

Après l'import des comptes depuis la même copie, importez les abonnements :

```powershell
docker compose exec app php artisan qetg:import-member-subscriptions --dry-run
docker compose exec app php artisan qetg:import-member-subscriptions
```

Le lot est refusé si un membre manque ou si la copie contient plusieurs lignes pour une même paire. Les membres peuvent s'abonner ou se désabonner depuis un profil public et retrouver leurs abonnements depuis leur profil. Un réimport ne réactive pas un abonnement supprimé sur cette version ; les changements ne sont pas synchronisés avec l'ancien site. Les marqueurs de désabonnement sont conservés dans la nouvelle base et la migration refuse leur suppression par retour arrière automatique.

## Signalements et modération partielle

Les membres connectés peuvent signaler un autre profil. Après import des comptes, les anciens signalements peuvent être copiés depuis la même source avec `qetg:import-member-reports --dry-run` puis `qetg:import-member-reports`. Les doublons et membres manquants font échouer le lot ; un signalement déjà classé n'est pas réouvert par un réimport. Les signalements historiques ne contiennent pas de motif et leur date d'import n'est pas la date d'origine.

Les droits administrateur historiques ne sont **jamais** accordés automatiquement. Pour autoriser explicitement un compte de recette, un opérateur ayant accès à la console peut exécuter `docker compose exec app php artisan qetg:set-admin ID grant`, où `ID` est l'identifiant du compte dans la nouvelle base (et non nécessairement son ancien identifiant) ; `revoke` retire le droit. La commande demande confirmation. L'écran de modération permet de consulter et classer les signalements ; les autres fonctions du tableau d'administration historique restent à migrer.
