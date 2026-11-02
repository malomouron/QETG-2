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

2. Créez le fichier `config.inc.php` à la racine du projet, à côté de `db.php` et `functions.php`, en remplissant les informations de connexion à la base de données :

   ```php
   <?php
      // config.inc.php
   
   	$servername = "localhost";
   	$username = "root";
   	$password = "";
   $dbname = "test"; // Nom de la base réellement importée, à adapter.
   $domaine = 'http://localhost/QETG-2/'; // URL complète du site, avec / final.
   	$expediteur   = 'email@domain.com';
   	$site_key = ''; //G-capcha key
   	$os = "windos"; //[windos|linux]
   	$myprivatekey = "";
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

`$dbname` doit désigner une base existante contenant les tables QETG ; ne le laissez pas vide. Le nom `test` ci-dessus correspond uniquement au fichier SQL de démonstration.

Les nouveaux mots de passe et ceux vérifiés lors d'une connexion sont hachés avec Argon2id si PHP le prend en charge, sinon avec l'algorithme par défaut de PHP. Avec le repli bcrypt, les mots de passe de plus de 72 octets ne peuvent pas être hachés ; activez Argon2id pour les prendre en charge.

Les connexions à un hachage bcrypt refusent les mots de passe de plus de 72 octets pour éviter que leur suffixe soit ignoré. Si un ancien système a déjà créé un hachage bcrypt en tronquant un mot de passe plus long, ce mot de passe doit être réinitialisé.


### Démarrage de l'application

- Assurez-vous que votre serveur web est configuré pour exécuter des scripts PHP.
- Accédez à votre projet via l'URL locale correspondant au nom du dossier (ex. : `http://localhost/QETG-2/` pour un dossier `QETG-2`).
- Les fichiers PHP de connexion sont chargés depuis le dossier du projet ; il n'est pas nécessaire de nommer ce dossier `quiz`.

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

Chaque pull request et chaque push vérifient la configuration Compose, construisent l'application et exécutent les tests sur PHP 8.2 et 8.5. Après un push validé sur `main`, l'image est publiée sur GitHub Container Registry sous `ghcr.io/malomouron/qetg-2:latest` et avec un tag de commit.

Le déploiement reste manuel : configurez `.env` sur le serveur, authentifiez-vous à GHCR, puis exécutez `docker compose pull app` et `docker compose up -d --no-build`. Configurez `APP_URL` et des mots de passe uniques avant toute exposition hors machine locale.

### Tests de caractérisation

Depuis la racine du projet, exécutez `php tests/characterization.php`. Sous XAMPP, utilisez par exemple `C:\xampp\php\php.exe tests\characterization.php`.
Ces premiers tests vérifient le chargement de la configuration et quelques règles existantes sans utiliser de base de données réelle. Ils ne couvrent pas encore les parcours complets du site.

Les tests de caractérisation, la vérification de syntaxe et les tests HTTP/MySQL isolés s'exécutent dans le même environnement Docker Compose :

```powershell
docker compose -p qetg-tests -f compose.yml -f tests\compose.integration.yml up --build --abort-on-container-exit --exit-code-from app
docker compose -p qetg-tests -f compose.yml -f tests\compose.integration.yml down --volumes --remove-orphans
```

La base des tests est indépendante de la base locale et le projet Compose utilise un nom séparé. La configuration de test importe le SQL de démonstration dans ce volume isolé ; utilisez `down --volumes` pour le supprimer après les tests.

## Support

Pour toute question ou problème, merci de contacter l'équipe de développement ou de créer une issue sur le dépôt GitHub.



## Contributions

Les contributions sont les bienvenues ! Si vous souhaitez apporter des améliorations ou ajouter des fonctionnalités, n'hésitez pas à ouvrir une issue ou à soumettre une pull request.
