# Plan de modernisation de QETG

## Objectif

Faire évoluer QETG vers une application maintenable, testable et déployable de façon reproductible, sans tenter de réécrire tous les parcours en une seule fois.

Ce plan s'appuie sur l'inventaire statique de [`FONCTIONNALITES.md`](FONCTIONNALITES.md). Il décrit une cible recommandée et un ordre de réalisation ; ce n'est pas encore une décision d'architecture validée ni un engagement de livraison.

**Périmètre demandé :** conserver toutes les fonctionnalités existantes, y compris « Sortie Ciné », l'aide, la newsletter, les abonnements et la modération. Pour les parcours incomplets, préserver leur intention et documenter leur comportement avant de les achever.

## État actuel observé

Le projet est une application PHP procédurale rendue côté serveur. Les pages mélangent requêtes SQL, logique métier et HTML, parfois dans de très gros fichiers (`index.php`, `profil.php`, `quiz_utilisateur.php`). La couche de données est une classe maison autour de MySQLi ; la configuration est un fichier PHP local non versionné.

L'audit fonctionnel a également relevé :

- des chemins web absolus en `/quiz/`, qui lient certaines images et pages au nom du dossier de déploiement ;
- une base SQL d'exemple nommée `test`, alors que la configuration d'installation doit désigner explicitement la base créée ;
- des noms de tables différents entre certains écrans d'administration et le schéma `QETG.sql` ;
- des parcours inachevés ou absents, notamment la recherche d'aide et la reprise de quiz incomplets ;
- un champ historique `pass_md5` utilisé pour stocker les mots de passe ; les comptes non encore reconnectés peuvent contenir un ancien hachage MD5 ;
- à l'origine, pas de socle de tests automatisés ni de manifeste Composer repéré pour le site historique.

Ces constats sont issus d'une lecture du code et du schéma, pas d'un audit d'exécution ou d'un audit de sécurité exhaustif.

## Cible technique recommandée

Pour cette application web de taille modeste, privilégier une application monolithique bien structurée :

| Domaine | Choix recommandé | Raison |
|---|---|---|
| Langage | PHP 8.5, avec une montée de version progressive | Version maintenue à la date de rédaction ; elle reçoit les correctifs de sécurité jusqu'au 31 décembre 2029. |
| Framework | Laravel 13 | Routage, validation, sessions, authentification, migrations et outils de test intégrés ; support PHP 8.3 à 8.5. |
| Vues | Blade, avec Alpine.js ou Livewire seulement pour les interactions qui le justifient | Conserve le rendu serveur adapté aux parcours actuels ; évite d'ajouter un frontend SPA sans besoin établi. |
| Base de données | MySQL ou MariaDB dans une version prise en charge par la version choisie de Laravel | Conserve le modèle relationnel actuel, tout en remplaçant les requêtes dispersées par des migrations et des relations explicites. |
| Dépendances | Composer avec `composer.lock` versionné | Installation reproductible et mises à jour maîtrisées des dépendances PHP. |
| Qualité | PHPUnit ou Pest, PHPStan/Larastan et Laravel Pint | Tests automatiques, analyse statique et formatage cohérent. |
| Environnement local | Docker Compose, avec PHP, serveur web et base séparés | Même configuration pour les contributeurs et la CI ; XAMPP peut rester un outil local de transition. |
| CI | GitHub Actions | Lancer les tests, le formatage, l'analyse statique et les contrôles de dépendances sur chaque pull request. |

**Alternative :** Symfony 7.4 LTS est un choix pertinent si l'équipe connaît déjà Symfony ou privilégie son cycle LTS : le calendrier officiel indique des correctifs de bogues jusqu'en novembre 2028 et de sécurité jusqu'en novembre 2029. Éviter d'installer simultanément Laravel et Symfony ; choisir un seul framework.

Les versions évoluent : vérifier les pages officielles de support juste avant le démarrage et choisir le dernier correctif de la branche retenue.

## Outils à ajouter

### Dès le début

- **Composer** : manifeste, dépendances et installation reproductible.
- **PHPUnit ou Pest** : tests des parcours métier et des règles de calcul.
- **PHPStan avec Larastan** : détection des erreurs de types et d'appels invalides avant exécution.
- **Laravel Pint** : formatage automatique du code PHP.
- **GitHub Actions** : exécution obligatoire des contrôles sur les changements proposés.
- **Docker Compose** : environnement de développement versionné, avec données de démonstration non sensibles.
- **`.env.example`** : liste des paramètres requis, sans secrets ; les valeurs réelles restent dans un `.env` ignoré par Git.

### À introduire selon le besoin

- **Playwright** pour les tests de bout en bout dans un navigateur, une fois les parcours prioritaires stabilisés.
- **Rector** pour automatiser certaines transformations de code pendant la migration, avec revue et tests après chaque lot.
- **Redis et files d'attente** uniquement si l'envoi d'e-mails, les tâches longues ou la charge le justifient.
- Un stockage objet tel que S3 uniquement si les avatars et images doivent être partagés entre plusieurs serveurs ou sauvegardés séparément.

Ne pas ajouter dès le départ Kubernetes, microservices, un frontend React/Vue indépendant ou Redis sans besoin concret : ces outils augmenteraient l'exploitation et la complexité sans corriger les problèmes structurels actuels.

## Changements d'architecture

1. **Routage explicite** : chaque URL appelle une route nommée, puis un contrôleur ; supprimer les liens construits à la main avec `/quiz/`.
2. **Responsabilités séparées** : contrôleurs pour le protocole HTTP, modèles pour les données, vues Blade pour l'affichage ; isoler les règles métier du parcours de quiz dans des classes dédiées quand elles sont réutilisées.
3. **Validation centralisée** : valider toutes les entrées de formulaire avec les règles du framework et présenter des erreurs compréhensibles.
4. **Accès par rôle et propriété** : définir des policies pour les actions administratives et pour modifier/supprimer uniquement ses propres quiz.
5. **Base versionnée** : remplacer progressivement les changements manuels de schéma par des migrations ; documenter les relations, contraintes et index.
6. **Opérations atomiques** : enregistrer la création d'un quiz et ses questions/réponses dans une transaction afin d'éviter les objets partiellement créés.
7. **Gestion des fichiers** : valider type MIME, taille et nom des fichiers, stocker hors des chemins codés en dur et ne jamais faire confiance au nom envoyé par le navigateur.
8. **Erreurs et journaux** : afficher une page d'erreur utilisateur non technique, journaliser le détail côté serveur et ne pas continuer après une configuration de base de données invalide.
9. **Mot de passe et sessions** : migrer les anciens mots de passe lors d'une connexion validée vers le hachage pris en charge par le framework ; ne pas conserver le mécanisme MD5.
10. **Accessibilité et interface** : assurer navigation clavier, labels, contrastes et retours de validation ; harmoniser les composants et rendre les textes traduisibles sans dupliquer les pages.

## Feuille de route par étapes

### Étape 0 — Cadrer et sauvegarder

- Conserver toutes les fonctionnalités recensées, y compris « Sortie Ciné », l'aide, la newsletter, les abonnements et la modération.
- Sauvegarder la base réelle et les fichiers téléversés ; ne pas réimporter `QETG.sql` sur une base de production, car le dump contient des `DROP TABLE`.
- Établir un environnement de test avec une copie anonymisée de la base et des données de démonstration.

**Résultat attendu :** périmètre produit confirmé et environnement de migration réversible.

### Étape 1 — Sécuriser le socle existant

- Premiers tests de caractérisation en place : chargement indépendant du dossier, fonctions utilitaires et logique de publication ; exécutables avec `php tests/characterization.php`, sans accès à la base réelle. La CI les lance sur PHP 8.2 et 8.5 et vérifie la syntaxe des fichiers PHP du projet. Cela ne valide pas encore les parcours complets ni la compatibilité fonctionnelle avec PHP 8.5.
- Premiers tests HTTP avec MySQL isolé : accueil, jeu et progression, création/modification/suppression de quiz, comptes et récupération de mot de passe, favoris, réactions, abonnements, commentaires et signalements. Le transport e-mail est simulé ; les autres parcours restent à couvrir. Les conteneurs n'exposent pas de port de base sur l'hôte et n'utilisent pas la base XAMPP.
- Les tests ont accompagné la correction de requêtes de progression de quiz et du contrôle du mot de passe lors de la connexion par login. Les mots de passe MD5 historiques sont convertis après une connexion réussie ; les inscriptions, changements et réinitialisations utilisent un hachage moderne.
- Les paramètres SMTP sont à configurer localement dans `config.inc.php`. Un ancien identifiant présent dans le code doit être révoqué ou renouvelé côté fournisseur, car il peut rester dans l'historique Git.
- Les tests ont aussi accompagné la correction de la table utilisée par les réactions aux quiz membres et de la perte de description lors de la modification d'un quiz sans nouvelle image.
- Une configuration dont le nom de base est vide est refusée avant la connexion ; la documentation avertit que le SQL de démonstration sélectionne `test` et peut écraser ses tables.
- Les liens principaux de navigation et de partage de quiz ne supposent plus une installation dans `/quiz/` ; d'autres chemins historiques restent à examiner.
- Documenter les versions exactes de PHP, MySQL/MariaDB et extensions nécessaires.
- Sortir les secrets de la configuration versionnée et contrôler les paramètres obligatoires au démarrage.
- Ajouter des tests de caractérisation sur les parcours qui fonctionnent aujourd'hui : inscription/connexion, consultation d'un quiz, réponses, création/publication, profil et administration.
- Corriger d'abord les incohérences de configuration et de chemins, notamment le choix du nom de base et le préfixe `/quiz/`.
- Remplacer les chemins incomplets et définir le comportement attendu des pages d'aide et de reprise d'un quiz.

**Résultat attendu :** comportement de référence documenté, problèmes visibles et tests anti-régression de base.

### Étape 2 — Créer le nouveau socle

- Un squelette Laravel 13 séparé est disponible dans `modern/`, avec Compose, une base locale dédiée et des tests. Les quiz officiels peuvent être importés et joués ; les comptes de recette peuvent se connecter, reprendre un quiz, importer leurs résultats et progressions officiels et gérer leurs favoris. Les comptes, mots de passe, résultats et progressions ne sont pas synchronisés avec l'ancien site.
- Les images référencées par les quiz officiels peuvent être copiées depuis une copie de fichiers montée en lecture seule et affichées dans le catalogue et le parcours de jeu. Les autres fichiers, notamment les images des quiz membres et les avatars, restent à migrer.
- La récupération du mot de passe des comptes importés utilise un lien à usage unique et un transport e-mail à configurer ; les changements ne sont pas synchronisés avec le site historique.
- L'inscription de recette demande une confirmation par e-mail avant de créer le compte et conserve la préférence newsletter ; la protection anti-robot et l'envoi de newsletters restent à migrer.
- Les membres peuvent modifier leurs informations et leur mot de passe ou supprimer leur compte dans le nouveau profil ; la suppression n'agit pas sur le site historique et les comptes supprimés ne sont pas réimportés. La vérification d'une nouvelle adresse e-mail et les avatars restent à réaliser.
- L'aide dispose d'une recherche simple et la page « Sortie Ciné » reprend la sélection historique ; les lecteurs externes ne sont chargés qu'à la demande.
- Un répertoire permet de rechercher des membres et de consulter leurs résultats officiels publics, sans exposer leurs coordonnées privées.
- Les abonnements aux membres peuvent être importés, consultés et modifiés ; un réimport ne réactive pas les désabonnements effectués sur la nouvelle version.
- Les signalements de membres peuvent être importés et classés dans une première interface de modération réservée aux administrateurs explicitement autorisés ; les autres fonctions d'administration restent à migrer.
- Créer le projet Laravel 13 séparément de l'ancien code, avec Composer, Docker Compose, migrations, tests, analyse statique et CI.
- Reproduire le schéma de données utile dans les migrations et ajouter des commandes/imports vérifiables pour les données existantes.
- Configurer une authentification maintenue par le framework ; migrer les mots de passe historiques au moment d'une connexion réussie.
- Définir les routes, les rôles, la validation et les politiques d'accès avant de déplacer les écrans.

**Résultat attendu :** application vide mais installable et testée de façon identique sur un poste local et dans la CI.

### Étape 3 — Migrer par parcours complets

Ordre conseillé :

1. catalogue, recherche, présentation et participation aux quiz ;
2. création, modification, publication et suppression des quiz utilisateurs ;
3. comptes, profils, avatars, historique, XP et succès ;
4. favoris, réactions, abonnements, commentaires et signalements ;
5. administration, e-mails, newsletter, aide et pages d'information.

Chaque lot doit inclure ses écrans, règles métier, permissions, migrations nécessaires et tests. Ne pas maintenir deux versions concurrentes d'une même fonctionnalité au-delà de la période de migration.

**Résultat attendu :** chaque parcours prioritaire fonctionne dans le nouveau socle et possède des tests.

### Étape 4 — Basculer et retirer l'ancien code

- Comparer les résultats et les données de l'ancien site et du nouveau sur un jeu de données de test.
- Tester une restauration de sauvegarde et un retour arrière avant la mise en ligne.
- Déployer d'abord en environnement de recette, puis migrer la production avec une fenêtre et un plan de retour arrière.
- Supprimer les pages et dépendances historiques seulement après validation des parcours équivalents.
- Mettre à jour la documentation d'installation et les consignes d'exploitation.

**Résultat attendu :** déploiement reproductible, sauvegardes vérifiées et ancien code retiré sans perte fonctionnelle.

## Critères d'acceptation

- Un nouveau développeur peut démarrer le projet à partir de la documentation, sans chemin XAMPP codé en dur.
- Les tests et l'analyse statique passent automatiquement avant qu'un changement soit accepté.
- Les parcours retenus ont des tests couvrant au minimum leurs cas nominaux, erreurs de validation et permissions.
- Aucun secret, mot de passe utilisateur ou configuration de production n'est stocké dans Git.
- Les actions de création/modification/suppression ne sont accessibles qu'aux utilisateurs autorisés.
- Les migrations et la procédure de sauvegarde/restauration sont documentées et testées sur une copie.

## Références de versions

Calendriers consultés le 2 novembre 2026 :

- [Versions PHP prises en charge](https://www.php.net/supported-versions.php) — PHP 8.5 est maintenu avec des correctifs actifs jusqu'au 31 décembre 2027 et de sécurité jusqu'au 31 décembre 2029.
- [Politique de versions Laravel](https://laravel.com/docs/releases) — Laravel 13 prend en charge PHP 8.3 à 8.5 ; les correctifs de sécurité sont annoncés jusqu'au 17 mars 2028.
- [Versions et support Symfony](https://symfony.com/releases) — Symfony 7.4 LTS est maintenu pour les bogues jusqu'en novembre 2028 et pour la sécurité jusqu'en novembre 2029.
