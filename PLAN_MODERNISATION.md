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

## Direction retenue

Conserver l'application PHP historique et l'améliorer progressivement, par parcours et avec des tests de non-régression. Le socle Laravel parallèle et sa CI ont été abandonnés ; il n'y aura pas de bascule vers un second framework dans cette feuille de route.

| Domaine | Choix recommandé | Raison |
|---|---|---|
| Langage et serveur | PHP avec Apache dans l'image Docker, testé sur PHP 8.2 et 8.5 | Conserver le fonctionnement actuel et rendre les versions vérifiables. |
| Structure | PHP procédural existant, refactorisé par petits changements | Éviter une réécriture parallèle et réduire le risque de régression. |
| Base de données | MySQL 8.4 dans Docker Compose ; conserver MySQL/MariaDB pour les installations natives | Utiliser un environnement reproductible sans exposer la base sur l'hôte. |
| Dépendances | Composer et npm avec leurs fichiers de verrouillage | Installer les bibliothèques externes sans versionner leurs copies. |
| CI/CD | GitHub Actions et Docker Compose | Tester les deux versions de PHP et publier l'image GHCR depuis `main` après succès. |
| Déploiement | Mise à jour manuelle de l'image via Docker Compose | Ne pas déployer sur un serveur sans cible ni procédure d'exploitation définies. |

## Outils à ajouter

### En place ou à poursuivre

- **Composer** : manifeste, dépendances et installation reproductible.
- **Tests de caractérisation et HTTP** : étendre progressivement la couverture des parcours historiques.
- **GitHub Actions et Docker Compose** : construire et tester sur les pull requests, publier l'image après un push validé sur `main`.
- **Docker Compose** : environnement local avec application, base de données et volumes persistants.
- **`.env.example`** : liste des paramètres requis, sans secrets ; les valeurs réelles restent dans un `.env` ignoré par Git.

### À introduire selon le besoin

- **Playwright** pour les tests de bout en bout dans un navigateur, une fois les parcours prioritaires stabilisés.
- **Redis et files d'attente** uniquement si l'envoi d'e-mails, les tâches longues ou la charge le justifient.
- Un stockage objet tel que S3 uniquement si les avatars et images doivent être partagés entre plusieurs serveurs ou sauvegardés séparément.

Ne pas ajouter dès le départ un framework parallèle, Kubernetes, des microservices, un frontend SPA ou Redis sans besoin concret.

## Changements d'architecture

1. **Responsabilités séparées** : extraire petit à petit les règles métier réutilisées hors des pages PHP volumineuses, sans déplacer plusieurs parcours à la fois.
2. **Accès aux données** : réutiliser la couche MySQLi existante et réduire les requêtes SQL dupliquées à mesure que chaque parcours est modifié.
3. **Validation centralisée** : valider les entrées de chaque formulaire touché et présenter des erreurs compréhensibles.
4. **Accès par rôle et propriété** : vérifier les autorisations administratives et la propriété des quiz lors de chaque changement de parcours.
5. **Base documentée** : examiner et tester les changements de schéma avant de les appliquer ; ne pas réimporter le dump d'exemple sur une base existante.
6. **Opérations atomiques** : enregistrer la création d'un quiz et ses questions/réponses dans une transaction afin d'éviter les objets partiellement créés.
7. **Gestion des fichiers** : valider type MIME, taille et nom des fichiers, stocker hors des chemins codés en dur et ne jamais faire confiance au nom envoyé par le navigateur.
8. **Erreurs et journaux** : afficher une page d'erreur utilisateur non technique, journaliser le détail côté serveur et ne pas continuer après une configuration de base de données invalide.
9. **Mot de passe et sessions** : conserver le hachage moderne en place et supprimer les compatibilités MD5 résiduelles au fil des parcours vérifiés.
10. **Accessibilité et interface** : assurer navigation clavier, labels, contrastes et retours de validation ; harmoniser les composants et rendre les textes traduisibles sans dupliquer les pages.

## Feuille de route par étapes

### Étape 0 — Cadrer et sauvegarder

- Conserver toutes les fonctionnalités recensées, y compris « Sortie Ciné », l'aide, la newsletter, les abonnements et la modération.
- Sauvegarder la base réelle et les fichiers téléversés ; ne pas réimporter `QETG.sql` sur une base de production, car le dump contient des `DROP TABLE`.
- Établir un environnement de test avec une copie anonymisée de la base et des données de démonstration.

**Résultat attendu :** périmètre produit confirmé et environnement de migration réversible.

### Étape 1 — Sécuriser le socle existant

- Tests de caractérisation en place : chargement indépendant du dossier, fonctions utilitaires et logique de publication. La CI les exécute dans Docker Compose sur PHP 8.2 et 8.5 et vérifie la syntaxe des fichiers PHP.
- Tests HTTP avec MySQL isolé en place : accueil, jeu et progression, création/modification/suppression de quiz, comptes et récupération de mot de passe, favoris, réactions, abonnements, commentaires et signalements. Le transport e-mail est simulé ; d'autres parcours restent à couvrir. Les conteneurs n'exposent pas de port de base sur l'hôte et n'utilisent pas la base XAMPP.
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

### Étape 2 — Conteneuriser l'application existante

- Construire l'image PHP/Apache à partir du dépôt et installer Composer/npm dans des étapes de build verrouillées.
- Utiliser Docker Compose pour l'application historique, MySQL et les volumes persistants des données téléversées.
- Garder la base hors des ports hôte et ne jamais importer automatiquement `QETG.sql`.
- Exécuter caractérisation, lint et tests HTTP/MySQL dans la même configuration Compose sur PHP 8.2 et 8.5.
- Publier l'image GHCR uniquement après succès sur `main` ; effectuer les mises à jour de serveur manuellement.

**Résultat attendu :** environnement local reproductible, contrôles CI verts et image versionnée disponible pour déploiement.

### Étape 3 — Améliorer le code historique par petits lots

Ordre conseillé, un parcours et un objectif à la fois :

1. catalogue, recherche, présentation et participation aux quiz ;
2. création, modification, publication et suppression des quiz utilisateurs ;
3. comptes, profils, avatars, historique, XP et succès ;
4. favoris, réactions, abonnements, commentaires et signalements ;
5. administration, e-mails, newsletter, aide et pages d'information.

Chaque lot doit partir d'un comportement caractérisé, inclure ses tests et préserver les autres parcours. Ne pas créer de seconde implémentation en parallèle.

**Résultat attendu :** le code historique devient progressivement plus lisible et chaque changement est protégé par des tests.

### Étape 4 — Livrer et vérifier les changements

- Faire passer la pull request par la CI Compose avant toute intégration.
- Mettre à jour l'image sur le serveur avec la procédure Compose documentée et vérifier la sauvegarde de la base et des fichiers téléversés.
- Conserver un retour arrière vers le tag d'image précédent.

**Résultat attendu :** livraison traçable et retour arrière possible sans remplacer l'application par un framework parallèle.

## Critères d'acceptation

- Un nouveau développeur peut démarrer le projet à partir de la documentation, sans chemin XAMPP codé en dur.
- Les tests Docker Compose passent automatiquement avant qu'un changement soit accepté.
- Les parcours retenus ont des tests couvrant au minimum leurs cas nominaux, erreurs de validation et permissions.
- Aucun secret, mot de passe utilisateur ou configuration de production n'est stocké dans Git.
- Les actions de création/modification/suppression ne sont accessibles qu'aux utilisateurs autorisés.
- Les migrations et la procédure de sauvegarde/restauration sont documentées et testées sur une copie.

## Références de versions

Calendriers consultés le 2 novembre 2026 :

- [Versions PHP prises en charge](https://www.php.net/supported-versions.php) — PHP 8.5 est maintenu avec des correctifs actifs jusqu'au 31 décembre 2027 et de sécurité jusqu'au 31 décembre 2029.
