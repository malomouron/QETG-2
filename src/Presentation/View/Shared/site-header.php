<?php

$sharedHeaderEscape = array(\QETG\Presentation\View\Shared\ViewHelpers::class, 'escape');
$sharedHeaderBasePath = rtrim((string) ($basePath ?? ''), '/');
$sharedHeaderCurrentPage = (string) ($currentPage ?? '');
$sharedHeaderLoggedIn = isset($_SESSION['login']);
$sharedHeaderLogin = (string) ($_SESSION['login'] ?? '');
if ($sharedHeaderLoggedIn && (!is_string($_SESSION['csrf_token'] ?? null) || $_SESSION['csrf_token'] === '')) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$sharedHeaderIsAdmin = isset($_SESSION['admin']) && (int) $_SESSION['admin'] === 1;
?>
<header class="bandeau home-header">
    <a class="home-brand" href="<?= $sharedHeaderEscape($sharedHeaderBasePath) ?>/accueil" aria-label="Accueil Quiz en tout genre">
        <img src="<?= $sharedHeaderEscape($sharedHeaderBasePath) ?>/assets/images/community/icon.png" alt="" class="home-brand-icon">
        <span>Quiz en tout genre</span>
    </a>
    <nav class="home-nav" aria-label="Navigation principale">
        <a class="<?= $sharedHeaderCurrentPage === 'home' ? 'is-current' : '' ?>" href="<?= $sharedHeaderEscape($sharedHeaderBasePath) ?>/accueil">Accueil</a>
        <a class="<?= $sharedHeaderCurrentPage === 'community' ? 'is-current' : '' ?>" href="<?= $sharedHeaderEscape($sharedHeaderBasePath) ?>/quiz-membres">Quiz membres</a>
        <a class="<?= $sharedHeaderCurrentPage === 'create-quiz' ? 'is-current' : '' ?>" href="<?= $sharedHeaderEscape($sharedHeaderBasePath) ?>/quiz-membres/editeur">Créer un quiz</a>
        <a class="<?= $sharedHeaderCurrentPage === 'about' ? 'is-current' : '' ?>" href="<?= $sharedHeaderEscape($sharedHeaderBasePath) ?>/a-propos">À propos</a>
        <a class="<?= $sharedHeaderCurrentPage === 'help' ? 'is-current' : '' ?>" href="<?= $sharedHeaderEscape($sharedHeaderBasePath) ?>/aide">Aide</a>
    </nav>
    <div class="home-account">
        <?php if ($sharedHeaderLoggedIn): ?>
            <a class="home-account-link" href="<?= $sharedHeaderEscape($sharedHeaderBasePath) ?>/compte/profil"><?= $sharedHeaderEscape($sharedHeaderLogin) ?></a>
            <?php if ($sharedHeaderIsAdmin): ?><a class="home-account-link" href="<?= $sharedHeaderEscape($sharedHeaderBasePath) ?>/administration"<?= $sharedHeaderCurrentPage === 'administration' ? ' aria-current="page"' : '' ?>>Administration</a><?php endif; ?>
            <form class="home-logout" action="<?= $sharedHeaderEscape($sharedHeaderBasePath) ?>/deconnexion" method="post">
                <input type="hidden" name="_csrf" value="<?= $sharedHeaderEscape($_SESSION['csrf_token']) ?>">
                <button class="home-button home-button--small" type="submit">Déconnexion</button>
            </form>
        <?php else: ?>
            <a class="home-account-link" href="<?= $sharedHeaderEscape($sharedHeaderBasePath) ?>/connexion">Connexion</a>
            <a class="home-button home-button--small" href="<?= $sharedHeaderEscape($sharedHeaderBasePath) ?>/inscription">Créer un compte</a>
        <?php endif; ?>
    </div>
</header>
