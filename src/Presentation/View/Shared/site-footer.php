<?php

$sharedFooterEscape = array(\QETG\Presentation\View\Shared\ViewHelpers::class, 'escape');
$sharedFooterBasePath = rtrim((string) ($basePath ?? ''), '/');
?>
<footer class="home-footer">
    <p>Quiz en tout genre · Créé par Malo MOURON · Copyright © 2018</p>
    <nav aria-label="Liens d’information">
        <a href="<?= $sharedFooterEscape($sharedFooterBasePath) ?>/a-propos">À propos</a>
        <a href="<?= $sharedFooterEscape($sharedFooterBasePath) ?>/a-propos#2">Contact</a>
        <a href="<?= $sharedFooterEscape($sharedFooterBasePath) ?>/a-propos#3">Règles</a>
        <a href="<?= $sharedFooterEscape($sharedFooterBasePath) ?>/a-propos#5">Confidentialité</a>
        <a href="<?= $sharedFooterEscape($sharedFooterBasePath) ?>/aide">Aide</a>
    </nav>
</footer>
