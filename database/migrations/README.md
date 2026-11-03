# Migrations SQL

Les fichiers de ce dossier sont des changements explicites de schéma. Ils ne sont jamais exécutés automatiquement par l’application.

Avant une migration :

1. sauvegarder la base et vérifier la restauration sur une copie ;
2. contrôler que la migration vise la bonne base et examiner les contraintes qu’elle ajoute ;
3. appliquer le fichier manuellement à un environnement de test ;
4. lancer les tests applicatifs, puis planifier l’application en production avec une sauvegarde et un retour arrière.

La migration `202611030001_create_quiz_password_reset.sql` crée la table de jetons temporaires nécessaire au parcours de réinitialisation de mot de passe routé. Appliquez-la avant d’activer ce parcours sur une base existante. Elle ne modifie pas les tables historiques et supprime les jetons associés lorsqu’un membre est supprimé.

## Données de démonstration

`seed-demo.php` ajoute des quiz, questions/réponses, images, difficultés, succès, historique, favoris et réactions clairement marqués `DÉMO`, associés au membre existant dont le login est `malo`. Il n’écrase aucune entrée existante et peut être relancé sans dupliquer ses données. Il ne modifie pas le compte ou son rôle. Les tables temporaires (inscriptions en attente et réinitialisations), commentaires, signalements, abonnements et progression en cours ne sont pas artificiellement remplies.

Lancer uniquement sur une base locale de démonstration. Le script refuse de démarrer sans la variable explicite `QETG_ALLOW_DEMO_SEED=1` et s’arrête si aucun compte `malo` n’existe. Il utilise la configuration MySQL locale existante et n’est jamais appelé par le site web.

```powershell
$env:QETG_ALLOW_DEMO_SEED = '1'
php database/seed-demo.php
Remove-Item Env:QETG_ALLOW_DEMO_SEED
```
