# Fonctionnalités de QETG par domaine métier

## Périmètre

Ce document décrit les fonctionnalités repérées dans le code PHP et le schéma `QETG.sql`. Il s'agit d'un inventaire statique : il ne garantit pas que chaque parcours fonctionne dans un environnement déployé ou avec une base de données correctement configurée.

Les principales catégories d'utilisateur visibles dans le code sont le visiteur, le membre connecté et le membre administrateur (`quiz_membre.admin = 1`). Les références de fichiers ci-dessous servent à retrouver les parcours et leur code.

## 1. Découverte et consultation des quiz

- La page d'accueil présente les quiz intégrés au site, avec pagination, illustrations, descriptions et indicateurs de popularité ; pour un membre connecté, elle affiche aussi le nombre de quiz réalisés (`index.php`, notamment lignes 1-363, `commun/bandeau.php`).
- La recherche de quiz est accessible depuis la navigation (`search.php`).
- Le catalogue des quiz publiés par les membres permet de rechercher par nom, de consulter les indicateurs de popularité, de voir les classements par réactions et par vues, et d'afficher les quiz publics. Une recherche par identifiant est également proposée pour les quiz privés (`quiz_utilisateur.php`, notamment lignes 1-134 et sections de classement).
- Les quiz sont présentés avec leur nom, image, créateur, nombre de vues et réactions (`quiz_utilisateur.php`, `Quiz-presentation.php`, `makeQuiz.php`).
- La page d'aide existe, mais son contenu indique qu'elle n'est pas opérationnelle ; son formulaire cible `search-aide.php`, absent du dépôt (`aide-page.php`).
- Une page autonome « Sortie Ciné » est également présente sans intégration apparente au parcours des quiz (`SortiCine.php`).

## 2. Participation aux quiz

- Le joueur consulte la présentation d'un quiz puis répond à ses questions (`Quiz-presentation.php`, `makeQuiz.php`, `question.php`).
- Une question peut proposer plusieurs réponses, une difficulté, une image et un texte d'explication associé aux réponses (`QETG.sql`, `question.php`, `reponse.php`).
- Le parcours affiche un retour en cas de réponse correcte ou incorrecte et permet de continuer ou de recommencer le quiz (`reponse.php`).
- Les quiz créés par des membres se passent dans un formulaire dédié ; la note, les bonnes réponses et les explications sont affichées à la soumission (`makeQuiz.php`, lignes 1-174).
- Pour les membres, les résultats des quiz intégrés et des quiz de membres sont enregistrés pour l'historique du profil (`reponse.php`, `makeQuiz.php`, `profil.php`, table `quiz_statistique`).

## 3. Création et gestion des quiz par les membres

- Un membre connecté peut créer un quiz et ajouter des questions et réponses, avec des images et un choix de visibilité public ou privé (`poster_quiz.php`, `poster_quiz_action.php`).
- La création prévoit un nom, une description d'accueil, une image de présentation, un nombre de questions, une difficulté par question et une réponse correcte avec explication.
- Le membre dispose d'un espace listant ses quiz. Il peut reprendre la préparation d'un quiz incomplet, modifier ou supprimer un quiz publié (`quiz_utilisateur.php`, `poster_quiz.php`, `deleteQuiz.php`).
- La publication et la visibilité reposent sur les champs `quizComplet`, `portee`, `id_quiz_user` et `id_user` de la table `quiz` (`QETG.sql`).

## 4. Comptes et profil membre

- Les parcours d'inscription, d'identification, de déconnexion et de confirmation d'adresse e-mail sont présents (`inscription.php`, `identifier.php`, `deconnexion.php`, `email_confirmation.php`, `confirmation.php`).
- La connexion accepte un login ou une adresse e-mail. Le parcours de mot de passe oublié prévoit l'envoi d'un nouveau mot de passe par e-mail (`identifier.php`, `mot-pass-oublier.php`, `changer.php`, `mail.php`).
- Le schéma conserve le nom historique `pass_md5`. Les nouveaux mots de passe utilisent un hachage moderne ; les hachages MD5 historiques sont mis à niveau après une connexion réussie (`QETG.sql`, `functions.php`, `identifier.php`).
- Un membre peut consulter son profil, son niveau, son expérience, ses succès et son historique de quiz (`profil.php`, tables `quiz_membre_succes`, `quiz_succes`, `quiz_statistique`).
- Il peut également consulter le profil d'un autre membre via `profil.php?u=...` (`profil.php`).
- Le profil propose le choix d'un avatar fourni par le site ou le téléversement d'une image (`profil.php`, `changer-image-profil.php`).
- Des formulaires de modification des informations du compte et de suppression du compte sont présents (`profil.php`, `changer.php`, `supr-compte_prof.php`).
- Le sélecteur de langue redirige vers le site français ou Google Translate ; il ne s'agit pas d'un système de traduction interne (`profil.php`).

## 5. Communauté et interactions

- Les quiz peuvent recevoir des appréciations positives ou négatives, avec affichage des compteurs et des réactions (`likedislike.php`, `likedislike_user.php`, `quiz_utilisateur.php`).
- Les membres peuvent marquer comme favoris les quiz effectués ; leurs favoris sont visibles depuis le profil (`changer-en-favoris.php`, `changer-en-non-favoris.php`, `profil.php`).
- Le formulaire de commentaire de l'accueil est associé à un traitement avec validation reCAPTCHA et envoi d'e-mail (`index.php`, `envoyer-mail.php`). Les commentaires et les réponses sont affichés séparément (`afficher-commentaire.php`, `reponse_commentaire.php`; tables `quiz_commentaire`, `quiz_reponse_commentaire`).
- Une action de signalement d'utilisateur existe (`signal.php`, table `quiz_signalement`). Le motif et son traitement ne sont pas explicités dans l'interface repérée.
- Des fonctions d'abonnement et de consultation/ajout de comptes suivis sont présentes (`abonnement.php`, `desabonnement.php`, `quiz_utilisateur.php`, table `quiz_subscription_membre`).
- La préférence newsletter peut être associée à un compte ; un parcours d'inscription/désabonnement et un script de campagne e-mail sont présents, mais l'enchaînement complet n'est pas clairement établi (`inscription.php`, `newletter.php`, `desabonnement.php`).
- Les membres peuvent consulter des classements de quiz, notamment par réactions et par vues (`quiz_utilisateur.php`).

## 6. Administration du contenu

- Un espace d'administration est exposé aux comptes dont le champ `admin` vaut `1` (`admin.php`, `quiz_membre.admin`).
- Les opérations disponibles dans le tableau de bord sont :

| Domaine | Opérations proposées | Pages représentatives |
|---|---|---|
| Quiz | Créer, modifier, supprimer | `admin/creer_quiz.php`, `admin/modifier_quiz.php`, `admin/suprimer_quiz.php` |
| Questions | Créer, modifier, supprimer | `admin/creer_question.php`, `admin/modifier_question.php`, `admin/suprimer_question.php` |
| Réponses | Créer, modifier, supprimer | `admin/creer_reponse.php`, `admin/modifier_reponse.php`, `admin/suprimer_reponse.php` |
| Utilisateurs | Créer, modifier, supprimer | `admin/creer_utilisateur.php`, `admin/modifier_utilisateur.php`, `admin/suprimer_utilisateur.php` |
| Statistiques | Créer, modifier, supprimer | `admin/creer_statistique.php`, `admin/modifier_statistique.php`, `admin/suprimer_statistique.php` |
| Commentaires | Créer, modifier, supprimer | `admin/creer_commentaire.php`, `admin/modifier_commentaire.php`, `admin/suprimer_commentaire.php` |
| Images | Ajouter, modifier | `admin/creer_image.php`, `admin/modifier_image.php` |

- Les traitements de formulaires correspondants se trouvent en majorité dans `admin/action-form/`.
- Le tableau de bord visible ne propose pas de rubrique dédiée à la modération des signalements.
- Le modèle de données contient aussi des tables de succès, de difficultés, de progression, de signalements et d'abonnements (`QETG.sql`).

## 7. Contact, information et préférences

- Le site comprend des formulaires de contact/commentaire, une gestion de newsletter et des fonctionnalités d'envoi d'e-mail (`envoyer-mail.php`, `afficher-commentaire.php`, `newletter.php`, `mail.php`).
- Des pages d'aide et de présentation du site sont présentes (`aide-page.php`, `aide.php`, `lien-bandeau/`).
- L'interface inclut une bannière de gestion des cookies avec Tarteaucitron et des services Google/reCAPTCHA configurés dans le code (`functions.php`, dépendance npm `tarteaucitronjs`).
- Un lien de don externe est proposé depuis le profil (`profil.php`).

## Configuration et limites de l'inventaire

- `QETG.sql` crée puis sélectionne une base nommée `test`. La valeur `$dbname` dans `config.inc.php` doit correspondre à la base réellement importée, ou le dump doit être adapté avant import.
- Le dump SQL contient des `DROP TABLE` avant la recréation de ses tables : réimporter ce fichier peut donc supprimer les données déjà présentes dans ces tables.
- Le dépôt ne contient pas `config.inc.php` (fichier local de configuration) ; les paramètres e-mail, base de données et services externes dépendent donc de l'installation.
- La recherche d'aide cible `search-aide.php`, absent du dépôt, et l'aide indique explicitement ne pas être opérationnelle (`aide-page.php`).
- Le parcours de publication contient des liens vers `poster_question.php` et `poster_reponse.php`, absents de la racine du dépôt ; vérifier ce chemin de reprise d'un quiz incomplet avant de considérer le parcours opérationnel de bout en bout (`functions.php`).
- Certains liens historiques de traduction restent liés au site et au préfixe `/quiz/` d'origine. Les principaux liens locaux de navigation et de partage ont été rendus indépendants du nom du dossier, mais les autres chemins doivent encore être vérifiés.
- Certains écrans d'administration référencent des noms de tables comme `image`, `membre` ou `question`, alors que le schéma fourni définit notamment `quiz_image`, `quiz_membre` et `quiz_question`. Ces écrans peuvent dépendre d'une version de base différente et doivent être vérifiés avant utilisation.
- Les quiz officiels enregistrent et reprennent la progression des membres via `quiz_progression` (`question.php`, `Quiz-presentation.php`).
- Les noms de colonnes et les chemins présents dans la documentation décrivent l'implémentation actuelle, pas une spécification contractuelle. Les fonctions doivent être vérifiées sur une installation avec base et services configurés.
