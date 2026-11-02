# QETG - Quiz En Tout Genre

QETG est une application de quiz simple et personnalisable, développée en PHP. Ce projet permet aux utilisateurs de créer et de participer à des quiz variés et flexibles.

## Prérequis

- **PHP** version 7.4 ou plus
- **MySQL** ou **MariaDB** pour la base de données
- **Apache** ou tout autre serveur web compatible avec PHP
- **Composer** (facultatif, selon la gestion des dépendances)

## Installation

### 1. Cloner le dépôt

```bash
git clone https://github.com/malomouron/QETG.git
cd qetg
```

### 2. Configuration de la base de données

1. Importez le modèle de base de données fourni [ici](https://github.com/malomouron/QETG/blob/main/QETG.sql) :

   - Utilisez un client MySQL ou phpMyAdmin pour importer le fichier SQL du modèle :
   
     ```bash
     mysql -u votre_utilisateur -p votre_base_de_donnees < chemin/vers/le/modele_de_base.sql
     ```

2. Créez le fichier `config.inc.php` à la racine du projet, à côté de `db.php` et `functions.php`, en remplissant les informations de connexion à la base de données :

   ```php
   <?php
      // config.inc.php
   
   	$servername = "localhost";
   	$username = "root";
   	$password = "";
   	$dbname = "";
   	$domaine = 'localhost';
   	$expediteur   = 'email@domain.com';
   	$site_key = ''; //G-capcha key
   	$os = "windos"; //[windos|linux]
   	$myprivatekey = "";
   	$nombreDeQuizAfficher = 5;
   ?>
	
   ```


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

## Support

Pour toute question ou problème, merci de contacter l'équipe de développement ou de créer une issue sur le dépôt GitHub.



## Contributions

Les contributions sont les bienvenues ! Si vous souhaitez apporter des améliorations ou ajouter des fonctionnalités, n'hésitez pas à ouvrir une issue ou à soumettre une pull request.
