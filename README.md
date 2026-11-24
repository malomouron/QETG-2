# QETG - Quiz En Tout Genre

QETG est une application de quiz simple et personnalisable, développée en PHP. Ce projet permet aux utilisateurs de créer et de participer à des quiz variés et flexibles.

## Prérequis

- **Docker Engine** et **Docker Compose v2** pour le démarrage recommandé
- Pour une installation native/XAMPP : PHP 8.2 ou 8.5 avec MySQLi et OpenSSL, MySQL/MariaDB, Composer, Node.js/npm et Apache ou un serveur PHP compatible

## Installation

### 1. Cloner le dépôt

```bash
git clone https://github.com/malomouron/QETG-2.git
cd QETG-2
```

### 2. Démarrer avec Docker Compose

Copiez `.env.example` vers `.env`, remplacez les mots de passe d'exemple, puis lancez l'application :

```powershell
Copy-Item .env.example .env
docker compose up --build -d
```

Le site écoute sur `http://localhost:8080`. La base et les images téléversées sont conservées dans des volumes Docker. La base est vide au premier démarrage. Pour une démonstration isolée, le profil `demo` importe `QETG.sql` :

```powershell
docker compose --profile demo run --rm demo-import
```

**Attention :** `QETG.sql` contient des instructions `DROP TABLE` et sélectionne la base `test`. N'utilisez l'import de démonstration que sur une base jetable, jamais sur une base contenant des données à conserver. `docker compose down` conserve les volumes ; `docker compose down --volumes` les supprime.

### Installation native/XAMPP

Pour une installation native, installez les dépendances après chaque clone ou récupération propre :

```bash
composer install
npm ci
```

Les versions sont verrouillées dans `composer.lock` et `package-lock.json`. Les fichiers de configuration et secrets ne doivent pas être ajoutés à Git.

#### Configuration de la base de données

1. Pour une installation de démonstration isolée uniquement, importez le fichier [`QETG.sql`](QETG.sql) avec MySQL ou phpMyAdmin.

   **Attention :** ce fichier crée et sélectionne une base nommée `test` et contient des instructions `DROP TABLE`. Il peut donc détruire les tables d'une base `test` existante, même si une autre base est sélectionnée dans le client. Ne l'importez jamais sur un serveur contenant des données à conserver ; utilisez un serveur ou conteneur jetable.

2. Créez le fichier `config.inc.php` à la racine du projet ; il est chargé par `bootstrap/config.php` pour configurer la connexion MySQL et le site :

   ```php
   <?php
      // config.inc.php
   
   	$servername = "localhost";
   	$username = "root";
   	$password = "";
   $dbname = "test"; // Nom de la base réellement importée, à adapter.
   $domaine = 'http://localhost/QETG-2/'; // URL complète du site, avec / final.
   	$expediteur   = 'email@domain.com';
      $site_key = ''; // Clé publique Google reCAPTCHA v2 (case à cocher).
      $recaptcha_secret_key = ''; // Clé secrète Google reCAPTCHA v2.
   	$os = "windos"; //[windos|linux]
      $myprivatekey = ''; // Clé aléatoire réservée à la signature des liens de désabonnement.
   	$nombreDeQuizAfficher = 5;
   // Identifiants SMTP fournis par votre hébergeur, à ne pas publier.
   $smtp_host = 'votre-serveur-smtp';
   $smtp_username = 'votre-utilisateur-smtp';
   $smtp_password = 'votre-mot-de-passe-smtp';
   $smtp_from = 'expediteur@exemple.fr';
   $smtp_port = 465;
   $smtp_encryption = 'ssl';
   ?>
	
   ```

Le fichier `config.inc.php` est local et ignoré par Git. Les quatre paramètres SMTP sont requis pour envoyer des e-mails (inscription et récupération de mot de passe). Une ancienne version du dépôt contenait un identifiant SMTP : révoquez ou renouvelez-le auprès du fournisseur et configurez de nouveaux identifiants ; le retirer du code actuel ne l'efface pas de l'historique Git.

**reCAPTCHA (commentaires de l’accueil).** Configurez les deux clés Google reCAPTCHA v2 (`$site_key` et `$recaptcha_secret_key` en installation native ; `RECAPTCHA_SITE_KEY` et `RECAPTCHA_SECRET_KEY` avec Docker). Laissez les deux vides pour accepter les commentaires sans CAPTCHA (le contrôle CSRF reste actif). Une seule clé renseignée rend l’envoi de commentaires indisponible ; les réponses, les réactions et le reste du site restent accessibles. Si votre configuration utilisait l’ancien paramètre de signature comme secret Google, configurez désormais le secret Google dans le paramètre dédié. `APP_PRIVATE_KEY` (`$myprivatekey` en installation native) doit être une clé aléatoire indépendante d’au moins 32 caractères, réservée à la newsletter ; changer cette clé invalide les anciens liens signés.

Pour une base existante, appliquez les migrations décrites dans [`database/migrations/README.md`](database/migrations/README.md) pendant une fenêtre de maintenance avant de remettre le nouveau code en service. En particulier, `202611230002_remove_legacy_md5_passwords.sql` renomme les colonnes de hachage et invalide les anciens MD5. Les comptes concernés ne sont pas supprimés, mais leurs propriétaires devront demander une réinitialisation par e-mail. Sauvegardez la base avant l'opération.

`$dbname` doit désigner une base existante contenant les tables QETG ; ne le laissez pas vide. Le nom `test` ci-dessus correspond uniquement au fichier SQL de démonstration.

Les nouveaux mots de passe et ceux vérifiés lors d'une connexion sont hachés avec Argon2id si PHP le prend en charge, sinon avec l'algorithme par défaut de PHP. Avec le repli bcrypt, les mots de passe de plus de 72 octets ne peuvent pas être hachés ; activez Argon2id pour les prendre en charge.

Les connexions à un hachage bcrypt refusent les mots de passe de plus de 72 octets pour éviter que leur suffixe soit ignoré. Si un ancien système a déjà créé un hachage bcrypt en tronquant un mot de passe plus long, ce mot de passe doit être réinitialisé.


### Démarrage de l'application


### E-mails et tâches planifiées

- Les confirmations de compte et les liens de réinitialisation utilisent le transport SMTP configuré par `SMTP_HOST`, `SMTP_USERNAME`, `SMTP_PASSWORD` et `MAIL_FROM`.
- Générez une valeur aléatoire d’au moins 32 caractères pour `APP_PRIVATE_KEY`; elle signe les liens individuels de désabonnement des newsletters.
- Pour purger les inscriptions en attente expirées, planifiez la commande CLI `php bin/purge-expired-registrations.php` (par exemple une fois par jour). Elle supprime uniquement les demandes plus anciennes que leur durée de validité.
- Pour envoyer une campagne, préparez un fichier HTML puis lancez `php bin/send-newsletter.php "Sujet" chemin/vers/campagne.html`. La commande est réservée au CLI, envoie des e-mails individuels aux membres abonnés et ajoute un lien de désabonnement signé.
### Connexion

Utilisez les identifiants créés au préalable dans la base de données.

## Fonctionnalités

- Création de quiz personnalisés
- Participation à des quiz en ligne
- Gestion des utilisateurs et des scores
- Autre...

L'inventaire des fonctionnalités par domaine métier et les limites observées sont documentés dans [FONCTIONNALITES.md](FONCTIONNALITES.md).
La feuille de route décrit maintenant une évolution incrémentale de l'application historique, sans socle parallèle.

### CI/CD et déploiement

Chaque pull request et chaque push exécutent le lint de syntaxe PHP, JavaScript et Bash ainsi que les tests Compose sur PHP 8.2 et 8.5. Un job Xdebug exige au moins **80 % de couverture des lignes exécutables** dans `src/`. Le rapport est disponible dans le résumé GitHub Actions et dans l’artefact `php-coverage`.

Après un push sur `main`, l’image n’est publiée dans GitHub Container Registry (`ghcr.io/malomouron/qetg-2`, tags `latest` et `sha-…`) que si tous ces contrôles passent.

#### Activer le déploiement automatique

1. Sur le serveur, installez Docker et Compose v2, préparez la version actuelle de [compose.yml](compose.yml) et un `.env` de production dans un répertoire dédié, puis initialisez la base et appliquez les [migrations](database/migrations/README.md) avant le premier déploiement. Configurez `APP_URL`, des identifiants uniques, les sauvegardes et HTTPS. Si l’image est privée, authentifiez le compte de déploiement à GHCR avec un accès en lecture.
2. Dans GitHub, créez l’environnement `production`, restreignez-le à `main` et ajoutez les variables d’environnement `QETG_DEPLOY_HOST` (nom d’hôte SSH, port 22), `QETG_DEPLOY_USER` et `QETG_DEPLOY_PATH` (répertoire absolu du projet). Ajoutez les secrets `QETG_DEPLOY_SSH_KEY` (clé privée dédiée) et `QETG_DEPLOY_KNOWN_HOSTS` (clé d’hôte vérifiée hors bande).
3. Définissez la **variable de dépôt** `QETG_DEPLOY_ENABLED` à `true`. Sans cette variable, le pipeline publie l’image mais ne contacte aucun serveur.

Le job déploie l’image par digest, redémarre uniquement le service `app` et attend que sa page d’accueil soit joignable. Il ne copie pas la configuration du serveur, n’applique pas de migration et ne recrée ni la base ni les volumes. Pour revenir à une version précédente, renseignez `QETG_IMAGE` avec son digest dans `.env`, puis exécutez `docker compose pull app` et `docker compose up -d --no-build --no-deps --wait app`.

### Rejouer les tests de la CI

Depuis la racine du projet, utilisez Bash (Git Bash sous Windows) avec Docker et Compose v2 :

```bash
PHP_VERSION=8.2 bash tests/run-ci.sh
PHP_VERSION=8.5 bash tests/run-ci.sh
```

La CI utilise [une configuration Compose autonome](tests/compose.integration.yml) : elle ne charge ni `.env` ni la configuration de déploiement, et ne publie aucun port. Chaque exécution crée son propre projet et son propre volume MySQL, importe [QETG.sql](QETG.sql), vérifie la syntaxe PHP et exécute les tests PHP, HTTP et MySQL. Les migrations sont testées uniquement dans un schéma jetable de cette instance. Le script supprime ses conteneurs et son volume en fin de test, y compris si un test échoue.

### Mesurer la couverture PHP

Depuis Git Bash avec Docker/Compose, exécutez `bash tests/run-coverage.sh`. Cette analyse ajoute Xdebug à une image de test temporaire, exécute la suite PHP, MySQL et HTTP sur la même base jetable, puis supprime les conteneurs et le volume. Elle ne modifie ni la configuration PHP XAMPP, ni l’image de production, ni la base de développement. Le rapport sépare les couches Application, Domain, Infrastructure et Presentation, affiche les fichiers les moins couverts et échoue si un fichier source de `src/` n’est pas chargé.

Au 24 novembre 2026, la suite couvre **82,15 % des lignes exécutables** et charge **117/117 fichiers de production**. La mesure porte sur les lignes, pas sur les branches, et n’implique pas que chaque cas métier soit exhaustivement vérifié. Aucun message SMTP n’est envoyé : le test du transport utilise uniquement une connexion loopback fermée. Les réponses Google reCAPTCHA sont simulées, sans appel externe.

## Support

Pour toute question ou problème, merci de contacter l'équipe de développement ou de créer une issue sur le dépôt GitHub.



## Contributions

Les contributions sont les bienvenues ! Si vous souhaitez apporter des améliorations ou ajouter des fonctionnalités, n'hésitez pas à ouvrir une issue ou à soumettre une pull request.
