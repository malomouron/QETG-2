# QETG - Quiz En Tout Genre

QETG est une application de quiz simple et personnalisable, développée en PHP. Ce projet permet aux utilisateurs de créer et de participer à des quiz variés et flexibles.

## Prérequis

- **PHP** avec les extensions MySQLi et OpenSSL ; les vérifications automatisées utilisent PHP 8.2 et 8.5
- **MySQL** ou **MariaDB** pour la base de données
- **Apache** ou tout autre serveur web compatible avec PHP
- **Composer** (facultatif, selon la gestion des dépendances)

## Installation

### 1. Cloner le dépôt

```bash
git clone https://github.com/malomouron/QETG-2.git
cd QETG-2
```

### 2. Configuration de la base de données

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
Les recommandations d'outillage et la feuille de route sont décrites dans [PLAN_MODERNISATION.md](PLAN_MODERNISATION.md).

Un premier socle Laravel isolé se trouve dans [`modern/`](modern/README.md). Il ne remplace pas encore le site historique et ne contient pas les parcours métier.

### Tests de caractérisation

Depuis la racine du projet, exécutez `php tests/characterization.php`. Sous XAMPP, utilisez par exemple `C:\xampp\php\php.exe tests\characterization.php`.
Ces premiers tests vérifient le chargement de la configuration et quelques règles existantes sans utiliser de base de données réelle. Ils ne couvrent pas encore les parcours complets du site.

Des tests HTTP et MySQL isolés sont également disponibles avec Docker Compose :

```powershell
docker compose -p qetg-tests-mon-identifiant -f tests\compose.integration.yml up --build --abort-on-container-exit --exit-code-from integration
docker compose -p qetg-tests-mon-identifiant -f tests\compose.integration.yml down
```

Choisissez un identifiant de projet Docker unique. La base des tests fonctionne dans un conteneur temporaire en mémoire, sans port publié ni accès à la base XAMPP ; son schéma est réinitialisé à chaque démarrage. N'utilisez jamais cette configuration pour vos données réelles.

## Support

Pour toute question ou problème, merci de contacter l'équipe de développement ou de créer une issue sur le dépôt GitHub.



## Contributions

Les contributions sont les bienvenues ! Si vous souhaitez apporter des améliorations ou ajouter des fonctionnalités, n'hésitez pas à ouvrir une issue ou à soumettre une pull request.
