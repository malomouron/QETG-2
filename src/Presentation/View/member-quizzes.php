<?php

$escape = array(\QETG\Presentation\View\Shared\ViewHelpers::class, 'escape');
$assetUrl = static fn (?string $path): string => \QETG\Presentation\View\Shared\ViewHelpers::assetUrl($path, $basePath);
$tabs = array('catalog' => 'Quiz publics', 'ranking' => 'Classements');
if ($memberId !== null) {
    $tabs += array('mine' => 'Mes quiz', 'liked' => 'Mes appréciations', 'following' => 'Comptes suivis', 'members' => 'Découvrir des membres');
}
$activeTabLabel = $tabs[$tab] ?? 'Quiz publics';
$renderQuizCards = static function (array $cards, string $context) use ($escape, $assetUrl, $basePath, $interactionCsrf, $accountCsrf, $memberId): void {
    if ($cards === array()) {
        echo '<div class="member-quiz-empty"><h3>Aucun quiz à afficher</h3><p>Essayez une autre recherche ou créez le premier quiz de la communauté.</p></div>';
        return;
    }
    echo '<div class="member-quiz-grid">';
    foreach ($cards as $card) {
        $quiz = $card['quiz'];
        $owner = $card['owner'];
        $image = $card['image'];
        $quizUrl = $context === 'mine' && !$quiz->isComplete
            ? $basePath . '/quiz-membres/editeur?id=' . (int) $quiz->id
            : $basePath . '/quiz-membres/jouer?id=' . (int) $quiz->id;
        $canReact = $quiz->isComplete && $quiz->isPublic;
        echo '<article class="member-quiz-card">';
        echo '<a class="member-quiz-image" href="' . $escape($quizUrl) . '">';
        if ($image !== null) {
            echo '<img src="' . $escape($assetUrl($image->source)) . '" alt="' . $escape($image->altText) . '" loading="lazy">';
        } elseif ($quiz->source !== null) {
            echo '<img src="' . $escape($assetUrl($quiz->source)) . '" alt="Illustration du quiz ' . $escape($quiz->name) . '" loading="lazy">';
        } else {
            echo '<span aria-hidden="true">Q?</span>';
        }
        echo '</a><div class="member-quiz-content">';
        echo '<div class="member-quiz-card-heading"><a href="' . $escape($quizUrl) . '">' . $escape($quiz->name) . '</a><span>+' . (int) $quiz->experiencePoints . ' XP</span></div>';
        echo '<p>' . $escape($quiz->welcomeText) . '</p>';
        echo '<div class="member-quiz-meta"><span>' . (int) $quiz->questionCount . ' question' . ($quiz->questionCount > 1 ? 's' : '') . '</span><span>' . (int) $quiz->viewCount . ' vue' . ($quiz->viewCount > 1 ? 's' : '') . '</span>';
        if ($owner !== null) {
            echo '<a href="' . $escape($basePath) . '/profil-public?id=' . (int) $owner->id . '">par ' . $escape($owner->login) . '</a>';
        }
        echo '</div>';
        if ($canReact) {
            $myReaction = $card['myReaction'] ?? null;
            $likeSelected = $myReaction === 1;
            $dislikeSelected = $myReaction === 0;
            $returnTo = $escape($basePath . '/quiz-membres?tab=' . $context);
            echo '<div class="member-quiz-reactions" role="group" aria-label="Votre appréciation du quiz">';
            echo '<form class="reaction-form" action="' . $escape($basePath) . '/interactions/reaction" method="post"><input type="hidden" name="_csrf" value="' . $escape($interactionCsrf) . '"><input type="hidden" name="quiz_id" value="' . (int) $quiz->id . '"><input type="hidden" name="value" value="1"><input type="hidden" name="return_to" value="' . $returnTo . '"><button class="reaction-link' . ($likeSelected ? ' is-liked' : '') . '" type="submit" aria-pressed="' . ($likeSelected ? 'true' : 'false') . '" aria-label="' . ($likeSelected ? 'Retirer votre J’aime' : 'J’aime ce quiz') . '">♥ <span>' . (int) $card['likes'] . '</span></button></form>';
            echo '<form class="reaction-form" action="' . $escape($basePath) . '/interactions/reaction" method="post"><input type="hidden" name="_csrf" value="' . $escape($interactionCsrf) . '"><input type="hidden" name="quiz_id" value="' . (int) $quiz->id . '"><input type="hidden" name="value" value="0"><input type="hidden" name="return_to" value="' . $returnTo . '"><button class="reaction-link reaction-link--down' . ($dislikeSelected ? ' is-disliked' : '') . '" type="submit" aria-pressed="' . ($dislikeSelected ? 'true' : 'false') . '" aria-label="' . ($dislikeSelected ? 'Retirer votre Je n’aime pas' : 'Je n’aime pas ce quiz') . '">♡ <span>' . (int) $card['dislikes'] . '</span></button></form>';
            echo '</div>';
        }
        if ($memberId !== null && $context === 'mine') {
            echo '<div class="member-quiz-owner-actions">';
            if ($quiz->isComplete) {
                echo '<a href="' . $escape($basePath) . '/quiz-membres/editeur?id=' . (int) $quiz->id . '">Modifier</a>';
            } else {
                echo '<a href="' . $escape($basePath) . '/quiz-membres/editeur?id=' . (int) $quiz->id . '">Continuer la création</a>';
            }
            echo '<form action="' . $escape($basePath) . '/compte/quiz/supprimer" method="post" data-confirm="Supprimer ce quiz ?"><input type="hidden" name="_csrf" value="' . $escape($accountCsrf) . '"><input type="hidden" name="quiz_id" value="' . (int) $quiz->id . '"><button type="submit">Supprimer</button></form>';
            echo '</div>';
        }
        echo '</div></article>';
    }
    echo '</div>';
};
\QETG\Presentation\View\Shared\ApplicationLayout::renderDocumentStart(
    'Quiz de la communauté - Quiz en tout genre',
    array($basePath . '/css.css', $basePath . '/assets/css/legacy-bandeau.css', $basePath . '/assets/css/home.css', $basePath . '/assets/css/member-quizzes.css'),
    'home-page member-quizzes-page',
    'Découvrez les quiz créés par la communauté de Quiz en tout genre.'
);
?>
<div class="home-shell">
    <?php $currentPage = 'community'; require __DIR__ . '/Shared/site-header.php'; ?>
    <main class="member-quiz-main">
        <section class="member-quiz-hero"><p class="home-eyebrow">La communauté</p><h1>Des quiz créés par les membres</h1><p>Explorez les créations, découvrez les auteurs et partagez vos appréciations.</p><a class="home-button" href="<?= $escape($basePath) ?>/quiz-membres/editeur">＋ Créer un quiz</a></section>
        <?php if (is_array($flash) && isset($flash['message'])): ?><div class="account-alert account-alert--<?= $escape((string) ($flash['type'] ?? 'info')) ?>" role="status"><?= $escape((string) $flash['message']) ?></div><?php endif; ?>
        <nav class="member-quiz-tabs" aria-label="Sections des quiz membres"><?php foreach ($tabs as $tabKey => $label): ?><a class="<?= $tab === $tabKey ? 'is-active' : '' ?>" href="<?= $escape($basePath) ?>/quiz-membres?tab=<?= $escape($tabKey) ?>"><?= $escape($label) ?></a><?php endforeach; ?></nav>
        <section class="member-quiz-section"><div class="member-quiz-section-heading"><div><p class="home-eyebrow"><?= $escape($activeTabLabel) ?></p><h2><?= $escape($activeTabLabel) ?></h2></div></div>
            <?php if ($tab === 'catalog'): ?>
                <form class="member-quiz-search" action="<?= $escape($basePath) ?>/quiz-membres" method="get"><input type="hidden" name="tab" value="catalog"><label for="community-search">Rechercher un quiz par nom</label><div><input id="community-search" type="search" name="s" maxlength="100" value="<?= $escape($searchTerm) ?>" placeholder="Ex. HTML ou géographie"><button class="home-button" type="submit">Rechercher</button></div><label class="member-private-search"><input type="checkbox" name="rech_by_id" value="on"<?= $privateId !== null ? ' checked' : '' ?>> Rechercher aussi un quiz privé par son identifiant</label></form>
                <?php if ($privateId !== null && $catalogCards === array()): ?><p class="member-quiz-notice">Aucun quiz terminé ne correspond à cet identifiant.</p><?php endif; ?>
                <?php $renderQuizCards($catalogCards, 'catalog'); ?>
            <?php elseif ($tab === 'mine'): ?>
                <p class="member-quiz-intro">Retrouvez vos brouillons et vos quiz publiés. Le lien Modifier vous permet de reprendre leur contenu.</p><?php $renderQuizCards($mineCards, 'mine'); ?>
            <?php elseif ($tab === 'ranking'): ?>
                <h3 class="member-ranking-title">Les plus appréciés (likes moins dislikes)</h3><?php $renderQuizCards(array_slice($rankingCards, 0, 20), 'ranking'); ?>
                <h3 class="member-ranking-title">Les plus consultés</h3><?php $renderQuizCards(array_slice($viewRankingCards, 0, 20), 'ranking'); ?>
            <?php elseif ($tab === 'liked'): ?>
                <p class="member-quiz-intro">Les quiz que vous avez aimés depuis cette adresse réseau.</p><?php $renderQuizCards($likedCards, 'liked'); ?>
            <?php elseif ($tab === 'following'): ?>
                <form class="member-quiz-search" action="<?= $escape($basePath) ?>/quiz-membres" method="get"><input type="hidden" name="tab" value="following"><label for="following-search">Rechercher parmi les comptes suivis</label><div><input id="following-search" type="search" name="a" value="<?= $escape((string) ($_GET['a'] ?? '')) ?>" placeholder="Nom du membre"><button class="home-button" type="submit">Rechercher</button></div></form>
                <?php if ($followingMembers === array()): ?><div class="member-quiz-empty"><h3>Aucun compte suivi</h3><p>Découvrez des membres pour commencer à les suivre.</p><a href="<?= $escape($basePath) ?>/quiz-membres?tab=members">Découvrir des membres →</a></div><?php else: ?><div class="member-profile-grid"><?php foreach ($followingMembers as $followedMember): ?><article class="member-profile-card"><img src="<?= $escape($followedMember->profileImage === 'default' ? $basePath . '/assets/images/community/bouton-profil.png' : $assetUrl($followedMember->profileImage)) ?>" alt=""><a href="<?= $escape($basePath) ?>/profil-public?id=<?= (int) $followedMember->id ?>"><?= $escape($followedMember->login) ?></a><form action="<?= $escape($basePath) ?>/communaute/abonnement" method="post"><input type="hidden" name="_csrf" value="<?= $escape($communityCsrf) ?>"><input type="hidden" name="member_id" value="<?= (int) $followedMember->id ?>"><input type="hidden" name="subscribe" value="0"><input type="hidden" name="return_tab" value="following"><button type="submit">Ne plus suivre</button></form></article><?php endforeach; ?></div><?php endif; ?>
            <?php else: ?>
                <form class="member-quiz-search" action="<?= $escape($basePath) ?>/quiz-membres" method="get"><input type="hidden" name="tab" value="members"><label for="member-search">Rechercher un membre à suivre</label><div><input id="member-search" type="search" name="b" maxlength="100" value="<?= $escape($memberSearch) ?>" placeholder="Nom du membre"><button class="home-button" type="submit">Rechercher</button></div></form>
                <?php if ($discoverMembers === array()): ?><div class="member-quiz-empty"><h3>Aucun membre trouvé</h3><p>Essayez une autre recherche.</p></div><?php else: ?><div class="member-profile-grid"><?php foreach ($discoverMembers as $discoverMember): ?><?php $isFollowing = isset($followedIds[(int) $discoverMember->id]); ?><article class="member-profile-card"><img src="<?= $escape($discoverMember->profileImage === 'default' ? $basePath . '/assets/images/community/bouton-profil.png' : $assetUrl($discoverMember->profileImage)) ?>" alt=""><a href="<?= $escape($basePath) ?>/profil-public?id=<?= (int) $discoverMember->id ?>"><?= $escape($discoverMember->login) ?></a><form action="<?= $escape($basePath) ?>/communaute/abonnement" method="post"><input type="hidden" name="_csrf" value="<?= $escape($communityCsrf) ?>"><input type="hidden" name="member_id" value="<?= (int) $discoverMember->id ?>"><input type="hidden" name="subscribe" value="<?= $isFollowing ? '0' : '1' ?>"><input type="hidden" name="return_tab" value="members"><button type="submit"><?= $isFollowing ? 'Ne plus suivre' : 'Suivre' ?></button></form></article><?php endforeach; ?></div><?php endif; ?>
            <?php endif; ?>
        </section>
    </main>
    <?php require __DIR__ . '/Shared/site-footer.php'; ?>
</div>
<?php \QETG\Presentation\View\Shared\ApplicationLayout::renderDocumentEnd(); ?>
