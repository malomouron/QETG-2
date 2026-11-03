<?php

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$assetUrl = static function (string $path) use ($basePath): string {
    if (preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $path) === 1) {
        return $path;
    }
    return rtrim($basePath, '/') . '/' . ltrim($path, '/');
};
$css = array(
    $basePath . '/css.css',
    $basePath . '/commun/bandeau.css',
    $basePath . '/resources/assets/css/home.css',
    $basePath . '/resources/assets/css/account.css',
);
?>
<!doctype html>
<html lang="fr">
<head>
<?php afficher_head('Espace membre - Quiz en tout genre', $css, 'UTF-8'); ?>
<meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body class="home-page account-page<?= $page === 'profile' ? ' account-page--profile' : '' ?>">
<div class="home-shell">
    <header class="bandeau home-header">
        <a class="home-brand" href="<?= $escape($basePath) ?>/accueil"><img src="<?= $escape($basePath) ?>/assets/images/community/icon.png" alt="" class="home-brand-icon"><span>Quiz en tout genre</span></a>
        <nav class="home-nav" aria-label="Navigation principale">
            <a href="<?= $escape($basePath) ?>/accueil">Accueil</a>
            <a href="<?= $escape($basePath) ?>/quiz_utilisateur.php">Quiz membres</a>
            <a href="<?= $escape($basePath) ?>/a-propos">À propos</a>
        </nav>
        <div class="home-account"><a class="home-account-link" href="<?= $escape($basePath) ?>/aide">Aide</a></div>
    </header>

    <main class="account-main">
        <section class="account-panel">
            <a class="account-back-link" href="<?= $escape($basePath) ?>/accueil">← Retour à l’accueil</a>
            <?php if ($page === 'login'): ?>
                <p class="home-eyebrow">Heureux de vous revoir</p>
                <h1>Connexion</h1>
                <p class="account-intro">Connectez-vous pour retrouver votre profil, vos favoris et votre progression.</p>
            <?php elseif ($page === 'register'): ?>
                <p class="home-eyebrow">Rejoignez la communauté</p>
                <h1>Créer un compte</h1>
                <p class="account-intro">Un compte vous permet de suivre votre progression et de partager vos quiz.</p>
            <?php elseif ($page === 'password'): ?>
                <p class="home-eyebrow">Sécurité du compte</p>
                <h1>Modifier le mot de passe</h1>
                <p class="account-intro">Choisissez un nouveau mot de passe que vous n’utilisez pas ailleurs.</p>
            <?php elseif ($page === 'profile'): ?>
                <p class="home-eyebrow">Votre espace membre</p>
                <h1>Mes informations</h1>
                <p class="account-intro">Mettez à jour votre nom et vos préférences. L’identifiant et l’adresse e-mail ne sont pas modifiables depuis ce formulaire.</p>
            <?php elseif ($page === 'forgot-password'): ?>
                <p class="home-eyebrow">Récupération du compte</p>
                <h1>Mot de passe oublié</h1>
                <p class="account-intro">Saisissez l’adresse e-mail associée au compte. Si elle correspond, un lien temporaire vous sera envoyé.</p>
            <?php elseif ($page === 'reset-password'): ?>
                <p class="home-eyebrow">Récupération du compte</p>
                <h1>Nouveau mot de passe</h1>
                <p class="account-intro"><?= $escape($message ?? 'Choisissez un nouveau mot de passe. Le lien expirera après une heure.') ?></p>
            <?php else: ?>
                <p class="home-eyebrow">Espace membre</p>
                <h1>Confirmation du compte</h1>
                <p class="account-intro"><?= $escape($message ?? 'La confirmation de votre compte a été traitée.') ?></p>
            <?php endif; ?>

            <?php if (is_array($flash) && isset($flash['message'])): ?>
                <div class="account-alert account-alert--<?= $escape((string) ($flash['type'] ?? 'info')) ?>" role="status"><?= $escape((string) $flash['message']) ?></div>
            <?php endif; ?>

            <?php if ($page === 'profile' && $accountMember !== null): ?>
                <div class="profile-summary">
                    <?php if ($accountMember->profileImage !== 'default' && $accountMember->profileImage !== ''): ?>
                        <img class="profile-avatar" src="<?= $escape($assetUrl($accountMember->profileImage)) ?>" alt="Avatar de <?= $escape($accountMember->login) ?>">
                    <?php else: ?>
                        <span class="profile-avatar profile-avatar--default" aria-hidden="true"><?= $escape(strtoupper(substr($accountMember->login, 0, 1))) ?></span>
                    <?php endif; ?>
                    <div><strong><?= $escape($accountMember->login) ?></strong><span>Membre depuis <?= $escape($accountMember->registeredAt) ?></span></div>
                </div>
                <div class="profile-stat-grid" aria-label="Statistiques du compte">
                    <div><strong><?= (int) ($profileStats['played'] ?? 0) ?></strong><span>quiz réalisés</span></div>
                    <div><strong><?= (int) ($profileStats['favorites'] ?? 0) ?></strong><span>favoris</span></div>
                    <div><strong><?= (int) ($profileStats['memberQuizzes'] ?? 0) ?></strong><span>quiz membres</span></div>
                    <div><strong><?= (int) ($profileData['level'] ?? 1) ?></strong><span>niveau</span></div>
                    <div><strong><?= (int) ($profileData['experienceInLevel'] ?? 0) ?>/15</strong><span>XP du niveau</span></div>
                </div>
            <?php endif; ?>

            <?php if ($page === 'login'): ?>
                <form class="account-form" action="<?= $escape($basePath) ?>/connexion" method="post">
                    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                    <label for="identity">Nom d’utilisateur ou adresse e-mail</label>
                    <input id="identity" name="identity" type="text" value="<?= $escape($loginValue) ?>" autocomplete="username" required autofocus>
                    <label for="login-password">Mot de passe</label>
                    <input id="login-password" name="password" type="password" autocomplete="current-password" required>
                    <div class="account-form-meta"><a href="<?= $escape($basePath) ?>/mot-de-passe-oublie">Mot de passe oublié ?</a></div>
                    <button class="home-button home-button--full" type="submit">Se connecter</button>
                </form>
                <p class="account-switch">Pas encore de compte ? <a href="<?= $escape($basePath) ?>/inscription">Créer un compte</a></p>
            <?php elseif ($page === 'register'): ?>
                <form class="account-form" action="<?= $escape($basePath) ?>/inscription" method="post">
                    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                    <div class="account-form-grid">
                        <div><label for="register-first-name">Prénom</label><input id="register-first-name" name="first_name" type="text" maxlength="50" value="<?= $escape((string) ($registerValues['first_name'] ?? '')) ?>" autocomplete="given-name" required></div>
                        <div><label for="register-last-name">Nom</label><input id="register-last-name" name="last_name" type="text" maxlength="50" value="<?= $escape((string) ($registerValues['last_name'] ?? '')) ?>" autocomplete="family-name" required></div>
                    </div>
                    <label for="register-login">Nom d’utilisateur</label>
                    <input id="register-login" name="login" type="text" minlength="3" maxlength="30" pattern="[A-Za-z0-9_.-]+" value="<?= $escape((string) ($registerValues['login'] ?? '')) ?>" autocomplete="username" required>
                    <label for="register-email">Adresse e-mail</label>
                    <input id="register-email" name="email" type="email" maxlength="100" value="<?= $escape((string) ($registerValues['email'] ?? '')) ?>" autocomplete="email" required>
                    <label for="register-password">Mot de passe <span class="account-field-hint">10 caractères minimum</span></label>
                    <input id="register-password" name="password" type="password" minlength="10" autocomplete="new-password" required>
                    <label for="register-password-confirmation">Confirmer le mot de passe</label>
                    <input id="register-password-confirmation" name="password_confirmation" type="password" minlength="10" autocomplete="new-password" required>
                    <label class="account-checkbox"><input type="checkbox" name="newsletter" value="1"<?= !empty($registerValues['newsletter']) ? ' checked' : '' ?>><span>Recevoir la newsletter</span></label>
                    <button class="home-button home-button--full" type="submit">Créer mon compte</button>
                </form>
                <p class="account-switch">Déjà membre ? <a href="<?= $escape($basePath) ?>/connexion">Se connecter</a></p>
            <?php elseif ($page === 'password'): ?>
                <form class="account-form" action="<?= $escape($basePath) ?>/compte/mot-de-passe" method="post">
                    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                    <label for="current-password">Mot de passe actuel</label><input id="current-password" name="current_password" type="password" autocomplete="current-password" required>
                    <label for="new-password">Nouveau mot de passe <span class="account-field-hint">10 caractères minimum</span></label><input id="new-password" name="new_password" type="password" minlength="10" autocomplete="new-password" required>
                    <label for="new-password-confirmation">Confirmer le nouveau mot de passe</label><input id="new-password-confirmation" name="password_confirmation" type="password" minlength="10" autocomplete="new-password" required>
                    <button class="home-button home-button--full" type="submit">Mettre à jour</button>
                </form>
                <p class="account-switch"><a href="<?= $escape($basePath) ?>/compte/profil">Modifier mes informations</a></p>
            <?php elseif ($page === 'profile' && $accountMember !== null): ?>
                <form class="account-form" action="<?= $escape($basePath) ?>/compte/profil" method="post">
                    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                    <label for="profile-login">Nom d’utilisateur</label>
                    <input id="profile-login" type="text" value="<?= $escape($accountMember->login) ?>" disabled>
                    <label for="profile-email">Adresse e-mail</label>
                    <input id="profile-email" type="email" value="<?= $escape($accountMember->email) ?>" disabled>
                    <label for="profile-first-name">Prénom</label>
                    <input id="profile-first-name" name="first_name" type="text" maxlength="50" value="<?= $escape($accountMember->firstName) ?>" autocomplete="given-name" required>
                    <label for="profile-last-name">Nom</label>
                    <input id="profile-last-name" name="last_name" type="text" maxlength="50" value="<?= $escape($accountMember->lastName) ?>" autocomplete="family-name" required>
                    <label class="account-checkbox"><input type="checkbox" name="newsletter" value="1"<?= $accountMember->newsletterEnabled ? ' checked' : '' ?>><span>Recevoir la newsletter</span></label>
                    <button class="home-button home-button--full" type="submit">Enregistrer</button>
                </form>
                <section class="profile-section" id="avatar">
                    <div class="profile-section-heading"><div><p class="home-eyebrow">Personnalisation</p><h2>Mon avatar</h2></div></div>
                    <form class="avatar-form" action="<?= $escape($basePath) ?>/compte/avatar" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                        <?php if (($profileData['avatarChoices'] ?? array()) !== array()): ?>
                            <fieldset class="avatar-choice-fieldset"><legend>Choisir un avatar du site</legend><div class="avatar-choice-grid">
                                <?php foreach ($profileData['avatarChoices'] as $avatar): ?>
                                    <?php $avatarSelected = $accountMember->profileImage === 'assets/images/avatars/presets/' . $avatar || $accountMember->profileImage === 'image-profil/' . $avatar; ?>
                                    <label class="avatar-choice<?= $avatarSelected ? ' is-selected' : '' ?>"><input type="radio" name="avatar_preset" value="<?= $escape($avatar) ?>"<?= $avatarSelected ? ' checked' : '' ?>><img src="<?= $escape($assetUrl('assets/images/avatars/presets/' . $avatar)) ?>" alt="Avatar proposé"></label>
                                <?php endforeach; ?>
                            </div></fieldset>
                        <?php endif; ?>
                        <label class="avatar-upload-label" for="profile-avatar-upload">Ou importer un avatar (JPEG, PNG, GIF ou WebP · 2 Mo maximum)</label>
                        <input id="profile-avatar-upload" type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp">
                        <button class="home-button" type="submit">Mettre à jour l’avatar</button>
                    </form>
                </section>

                <section class="profile-section" id="historique">
                    <div class="profile-section-heading"><div><p class="home-eyebrow">Vos résultats</p><h2>Quiz effectués</h2></div></div>
                    <?php if (($profileData['playedQuizzes'] ?? array()) === array()): ?>
                        <p class="profile-empty">Vous n’avez pas encore terminé de quiz. <a href="<?= $escape($basePath) ?>/accueil">En choisir un</a></p>
                    <?php else: ?>
                        <div class="profile-quiz-list">
                            <?php foreach ($profileData['playedQuizzes'] as $item): ?>
                                <?php $playedQuiz = $item['quiz']; $playedStatistic = $item['statistic']; ?>
                                <article class="profile-quiz-row">
                                    <?php if ($item['image'] !== null): ?><img src="<?= $escape($assetUrl($item['image']->source)) ?>" alt="<?= $escape($item['image']->altText) ?>" loading="lazy"><?php else: ?><span class="profile-quiz-placeholder" aria-hidden="true">Q?</span><?php endif; ?>
                                    <div class="profile-quiz-details"><a href="<?= $escape($basePath) ?>/<?= $playedQuiz->isMemberCreated ? 'makeQuiz.php?id_quiz=' . (int) $playedQuiz->id : 'quiz-officiel?id=' . (int) $playedQuiz->id ?>"><?= $escape($playedQuiz->name) ?></a><span><?= $escape($playedStatistic->playedAt) ?> · <?= (int) $playedStatistic->score ?> point(s)</span></div>
                                    <div class="profile-quiz-actions">
                                        <?php if ($playedStatistic->isFavorite): ?><a href="<?= $escape($basePath) ?>/changer-en-non-favoris.php?id_quiz=<?= (int) $playedQuiz->id ?>" aria-label="Retirer des favoris">★ Favori</a><?php else: ?><a href="<?= $escape($basePath) ?>/changer-en-favoris.php?id_quiz=<?= (int) $playedQuiz->id ?>" aria-label="Ajouter aux favoris">☆ Ajouter aux favoris</a><?php endif; ?>
                                        <span>+<?= (int) $playedQuiz->experiencePoints ?> XP</span>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="profile-section" id="succes">
                    <div class="profile-section-heading"><div><p class="home-eyebrow">Progression</p><h2>Mes succès</h2></div><span class="profile-section-count"><?= count($profileData['achievements'] ?? array()) ?></span></div>
                    <?php if (($profileData['achievements'] ?? array()) === array()): ?>
                        <p class="profile-empty">Aucun succès débloqué pour le moment. Continuez à jouer pour en gagner.</p>
                    <?php else: ?>
                        <div class="achievement-grid">
                            <?php foreach ($profileData['achievements'] as $item): ?>
                                <article class="achievement-card"><span class="achievement-icon" aria-hidden="true">✦</span><div><h3><?= $escape($item['achievement']->name) ?></h3><p><?= $escape($item['achievement']->description) ?></p><small>+<?= (int) $item['achievement']->experienceReward ?> XP · <?= $escape($item['grant']->awardedAt) ?></small></div></article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="profile-section" id="mes-quiz">
                    <div class="profile-section-heading"><div><p class="home-eyebrow">Création</p><h2>Mes quiz</h2></div><a class="profile-inline-link" href="<?= $escape($basePath) ?>/poster_quiz.php">＋ Créer un quiz</a></div>
                    <?php if (($profileData['createdQuizzes'] ?? array()) === array()): ?>
                        <p class="profile-empty">Vous n’avez pas encore créé de quiz.</p>
                    <?php else: ?>
                        <div class="profile-created-grid">
                            <?php foreach ($profileData['createdQuizzes'] as $createdQuiz): ?>
                                <?php $createdQuizEntity = $createdQuiz['quiz']; ?>
                                <article class="created-quiz-card">
                                    <div class="created-quiz-card-top"><span class="created-quiz-status<?= $createdQuizEntity->isComplete ? ' is-published' : '' ?>"><?= $createdQuizEntity->isComplete ? 'Publié' : 'Brouillon' ?></span><span><?= (int) $createdQuizEntity->questionCount ?> question(s) · <?= (int) $createdQuizEntity->viewCount ?> vue(s)</span></div>
                                    <h3><?= $escape($createdQuizEntity->name) ?></h3>
                                    <p><?= $escape($createdQuizEntity->welcomeText) ?></p>
                                    <div class="created-quiz-reactions"><span>♥ <?= (int) $createdQuiz['likes'] ?></span><span>♡ <?= (int) $createdQuiz['dislikes'] ?></span><span>+<?= (int) $createdQuizEntity->experiencePoints ?> XP</span></div>
                                    <div class="created-quiz-actions">
                                        <a href="<?= $escape($basePath) ?>/<?= $createdQuizEntity->isComplete ? 'makeQuiz.php?id_quiz=' . (int) $createdQuizEntity->id : 'poster_quiz.php?q=' . (int) $createdQuizEntity->id ?>"><?= $createdQuizEntity->isComplete ? 'Voir le quiz' : 'Reprendre' ?> →</a>
                                        <a href="<?= $escape($basePath) ?>/poster_quiz.php?q=<?= (int) $createdQuizEntity->id ?>">Modifier</a>
                                        <?php if ($createdQuizEntity->isComplete): ?><button type="button" class="profile-share-button" data-share-url="<?= $escape($shareBaseUrl . '/makeQuiz.php?id_quiz=' . (int) $createdQuizEntity->id) ?>">Partager</button><?php endif; ?>
                                        <form action="<?= $escape($basePath) ?>/compte/quiz/supprimer" method="post" onsubmit="return confirm('Supprimer ce quiz et ses questions ?');"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="quiz_id" value="<?= (int) $createdQuizEntity->id ?>"><button type="submit">Supprimer</button></form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="profile-account-actions" id="securite">
                    <div><strong>Sécurité du compte</strong><span>Modifiez votre mot de passe en confirmant d’abord l’actuel.</span></div>
                    <a href="<?= $escape($basePath) ?>/compte/mot-de-passe">Modifier le mot de passe →</a>
                </section>
                <details class="profile-delete-account">
                    <summary>Supprimer mon compte</summary>
                    <p>Cette action supprime votre compte et vos quiz personnels. Elle est irréversible.</p>
                    <form action="<?= $escape($basePath) ?>/compte/supprimer" method="post" class="account-form" onsubmit="return confirm('Confirmer la suppression définitive du compte ?');">
                        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                        <label for="delete-account-password">Confirmez avec votre mot de passe</label>
                        <input id="delete-account-password" type="password" name="current_password" autocomplete="current-password" required>
                        <button class="delete-account-button" type="submit">Supprimer définitivement mon compte</button>
                    </form>
                </details>
                <div class="profile-extra-links">
                    <label for="profile-language">Langue de consultation
                        <select id="profile-language" onchange="if (this.value !== 'fr') { window.location.href = 'https://translate.google.com/translate?sl=fr&tl=' + encodeURIComponent(this.value) + '&u=' + encodeURIComponent(window.location.href); }">
                            <option value="fr">Français</option><option value="en">English</option><option value="de">Deutsch</option><option value="es">Español</option><option value="zh-CN">中文</option>
                        </select>
                    </label>
                    <a href="https://paypal.me/MaloMouron" target="_blank" rel="noopener noreferrer">Soutenir le site ↗</a>
                </div>
            <?php elseif ($page === 'forgot-password'): ?>
                <form class="account-form" action="<?= $escape($basePath) ?>/mot-de-passe-oublie" method="post">
                    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                    <label for="reset-email">Adresse e-mail</label>
                    <input id="reset-email" name="email" type="email" maxlength="100" autocomplete="email" required autofocus>
                    <button class="home-button home-button--full" type="submit">Envoyer le lien</button>
                </form>
                <p class="account-switch"><a href="<?= $escape($basePath) ?>/connexion">Retour à la connexion</a></p>
            <?php elseif ($page === 'reset-password' && $resetAllowed): ?>
                <form class="account-form" action="<?= $escape($basePath) ?>/reinitialiser-mot-de-passe" method="post">
                    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="token" value="<?= $escape($resetToken) ?>">
                    <label for="reset-new-password">Nouveau mot de passe <span class="account-field-hint">10 caractères minimum</span></label>
                    <input id="reset-new-password" name="new_password" type="password" minlength="10" autocomplete="new-password" required autofocus>
                    <label for="reset-password-confirmation">Confirmer le mot de passe</label>
                    <input id="reset-password-confirmation" name="password_confirmation" type="password" minlength="10" autocomplete="new-password" required>
                    <button class="home-button home-button--full" type="submit">Réinitialiser le mot de passe</button>
                </form>
            <?php else: ?>
                <a class="home-button home-button--full" href="<?= $escape($basePath) ?>/accueil">Continuer vers le site</a>
            <?php endif; ?>
        </section>
        <?php if ($page !== 'profile'): ?>
        <aside class="account-aside">
            <span class="account-aside-icon" aria-hidden="true">Q?</span>
            <p class="home-eyebrow">Jouez, apprenez, partagez</p>
            <h2>Chaque quiz est une nouvelle découverte.</h2>
            <p>Retrouvez les quiz officiels et ceux de la communauté, et gardez une trace de vos résultats.</p>
            <a href="<?= $escape($basePath) ?>/quiz_utilisateur.php">Explorer les quiz →</a>
        </aside>
        <?php endif; ?>
    </main>

    <footer class="home-footer"><p>Quiz en tout genre · Une communauté de curieux.</p><nav aria-label="Liens d’information"><a href="<?= $escape($basePath) ?>/a-propos">À propos</a><a href="<?= $escape($basePath) ?>/aide">Aide</a></nav></footer>
</div>
<?php if ($page === 'profile'): ?>
<script>
    document.querySelectorAll('.profile-share-button').forEach(function (button) {
        button.addEventListener('click', async function () {
            const url = button.dataset.shareUrl;
            try {
                await navigator.clipboard.writeText(url);
                button.textContent = 'Lien copié';
            } catch (error) {
                window.prompt('Copiez le lien du quiz :', url);
            }
        });
    });
</script>
<?php endif; ?>
</body>
</html>
