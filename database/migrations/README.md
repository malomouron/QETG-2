# Migrations SQL

Les fichiers de ce dossier sont des changements explicites de schéma. Ils ne sont jamais exécutés automatiquement par l’application.

Avant une migration :

1. sauvegarder la base et vérifier la restauration sur une copie ;
2. contrôler que la migration vise la bonne base et examiner les contraintes qu’elle ajoute ;
3. appliquer le fichier manuellement à un environnement de test ;
4. lancer les tests applicatifs, puis planifier l’application en production avec une sauvegarde et un retour arrière.

La migration `202611030001_create_quiz_password_reset.sql` crée la table de jetons temporaires nécessaire au parcours de réinitialisation de mot de passe routé. Appliquez-la avant d’activer ce parcours sur une base existante. Elle ne modifie pas les tables historiques et supprime les jetons associés lorsqu’un membre est supprimé.

La migration `202611230001_seed_quiz_difficulties.sql` ajoute les niveaux de base manquants (`Très simple`, `Simple`, `Intermédiaire`, `Difficile`, `Très difficile`), sans modifier les lignes ni les identifiants existants. Elle peut être relancée, y compris sur une base ayant reçu la version qui n’ajoutait que trois niveaux. Le niveau `Moyenne` n’est pas ajouté, car `Intermédiaire` remplit déjà ce rôle. Un import neuf de `QETG.sql` contient déjà ces cinq niveaux.

La migration `202611230002_remove_legacy_md5_passwords.sql` renomme `quiz_membre.pass_md5` en `password_hash` et `quiz_cle.cle_pass_md5` en `cle_password_hash`. Elle efface les MD5 des comptes existants sans supprimer les comptes : ces membres doivent réinitialiser leur mot de passe via le lien envoyé à leur adresse e-mail. Les demandes d’inscription en attente qui utilisent encore MD5 sont supprimées et doivent être recommencées. Le code n’accepte ensuite plus de connexion MD5. **Application obligatoire pour mettre en service la version qui utilise ces nouveaux noms de colonnes.**

Pour la mise en production, sauvegardez d’abord la base, placez le site en maintenance, déployez le code et appliquez la migration avant de rouvrir le site. Cette migration est à appliquer une seule fois à une base créée avec l’ancien schéma; le schéma de démonstration `QETG.sql` contient déjà les nouveaux noms.

### Unicité des comptes membres

La migration `202611230003_enforce_unique_member_identity.sql` convertit `quiz_membre.login` en `VARCHAR(255)` et ajoute deux index uniques sur la totalité du login et de l’adresse e-mail. Les comparaisons conservent la collation de chaque colonne (dans le schéma standard, la casse et les accents ne distinguent pas deux valeurs). Ne l’appliquez pas à un import neuf de `QETG.sql`, qui contient déjà les index.

### Mesures quotidiennes de trafic

La migration `202611230004_create_daily_traffic.sql` ajoute `quiz_daily_traffic` aux bases existantes. Elle ne stocke que la date et les totaux de pages vues et de sessions journalières uniques ; elle ne contient aucune adresse IP, aucun user-agent ni identifiant de visiteur. Appliquez-la une seule fois après sauvegarde, en sélectionnant explicitement la base QETG avec le client MySQL. Elle est relançable et ne modifie pas les autres tables. Un import neuf de `QETG.sql` contient déjà cette table. Tant que la migration manque, le tableau d’administration conserve ses autres statistiques et fournit des métriques de trafic à zéro avec `migrationMissing = true`; l’enregistrement d’une vue échoue explicitement au lieu de masquer une erreur de stockage.

Après sauvegarde, arrêtez toutes les écritures sur la base pendant la vérification et la migration. Sur la base sélectionnée, ces requêtes de contrôle doivent toutes retourner zéro ligne :

```sql
SELECT id, CHAR_LENGTH(login) AS longueur FROM quiz_membre WHERE CHAR_LENGTH(login) > 255;
SELECT login, COUNT(*) AS doublons FROM quiz_membre GROUP BY login HAVING COUNT(*) > 1;
SELECT email, COUNT(*) AS doublons FROM quiz_membre GROUP BY email HAVING COUNT(*) > 1;
```

Utilisez le client MySQL sans `--force`, avec un compte habilité à créer, exécuter et supprimer la procédure temporaire de vérification et à modifier la table. Si le contrôle échoue, ni les lignes membres ni la table ne sont modifiées. Examinez les comptes concernés avant de corriger les données, puis relancez le fichier complet : il supprime au début la procédure de vérification éventuellement laissée par un échec. Il ne tronque, ne fusionne ni ne supprime aucun compte.

## Données de démonstration

`seed-demo.php` ajoute des quiz, questions/réponses, images, difficultés, succès, historique, favoris et réactions clairement marqués `DÉMO`, associés au membre existant dont le login est `malo`. Il n’écrase aucune entrée existante et peut être relancé sans dupliquer ses données. Il ne modifie pas le compte ou son rôle. Les tables temporaires (inscriptions en attente et réinitialisations), commentaires, signalements, abonnements et progression en cours ne sont pas artificiellement remplies.

Lancer uniquement sur une base locale de démonstration. Le script refuse de démarrer sans la variable explicite `QETG_ALLOW_DEMO_SEED=1` et s’arrête si aucun compte `malo` n’existe. Il utilise la configuration MySQL locale existante et n’est jamais appelé par le site web.

```powershell
$env:QETG_ALLOW_DEMO_SEED = '1'
php database/seed-demo.php
Remove-Item Env:QETG_ALLOW_DEMO_SEED
```
