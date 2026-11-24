<?php

$escape = array(\QETG\Presentation\View\Shared\ViewHelpers::class, 'escape');
$assetUrl = static fn (?string $path): string => \QETG\Presentation\View\Shared\ViewHelpers::assetUrl($path, $basePath);
\QETG\Presentation\View\Shared\ApplicationLayout::renderDocumentStart(
    'Profil de ' . $member->login,
    array($basePath . '/css.css', $basePath . '/assets/css/legacy-bandeau.css', $basePath . '/assets/css/home.css', $basePath . '/assets/css/account.css', $basePath . '/assets/css/member-quizzes.css', $basePath . '/assets/css/public-profile.css'),
    'home-page member-quizzes-page'
);
?>
<div class="home-shell">
    <?php require __DIR__ . '/Shared/site-header.php'; ?>
    <main class="member-quiz-main public-profile-main">
        <?php if (is_array($flash) && isset($flash['message'])): ?><div class="account-alert account-alert--<?= $escape((string) ($flash['type'] ?? 'info')) ?>" role="status"><?= $escape((string) $flash['message']) ?></div><?php endif; ?>
            <section class="public-profile-hero">
                <div class="public-profile-identity">
                    <?php if ($member->profileImage !== 'default' && $member->profileImage !== ''): ?><img class="public-profile-avatar" src="<?= $escape($assetUrl($member->profileImage)) ?>" alt="Avatar de <?= $escape($member->login) ?>"><?php else: ?><span class="public-profile-avatar public-profile-avatar--default" aria-hidden="true"><?= $escape(strtoupper(substr($member->login, 0, 1))) ?></span><?php endif; ?>
                    <div><p class="home-eyebrow">Membre de la communauté</p><h1><?= $escape($member->login) ?></h1><p>Niveau <?= (int) $level ?> · <?= (int) $experienceInLevel ?>/15 XP · Membre depuis <?= $escape($member->registeredAt) ?></p></div>
                </div>
                <div class="public-profile-actions">
                    <?php if (isset($_SESSION['id']) && (int) $_SESSION['id'] !== (int) $member->id): ?>
                        <form action="<?= $escape($basePath) ?>/communaute/abonnement" method="post"><input type="hidden" name="_csrf" value="<?= $escape($profileCsrf) ?>"><input type="hidden" name="member_id" value="<?= (int) $member->id ?>"><input type="hidden" name="subscribe" value="<?= $isFollowing ? '0' : '1' ?>"><input type="hidden" name="return_tab" value="profile"><input type="hidden" name="profile_id" value="<?= (int) $member->id ?>"><button class="home-button" type="submit"><?= $isFollowing ? 'Ne plus suivre' : 'Suivre ce membre' ?></button></form>
                        <button id="signaler" class="public-profile-report-toggle" type="button" aria-haspopup="dialog" command="show-modal" commandfor="report-dialog">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 21V4m0 1c5-4 9 4 14 0v10c-5 4-9-4-14 0"/></svg>
                            Signaler ce profil
                        </button>
                        <dialog id="report-dialog" class="public-profile-report-dialog" aria-labelledby="report-dialog-title" aria-describedby="report-dialog-description">
                            <p class="home-eyebrow">Modération</p>
                            <h2 id="report-dialog-title">Signaler ce profil ?</h2>
                            <p id="report-dialog-description">Le profil de <strong><?= $escape($member->login) ?></strong> sera signalé à l’équipe de modération.</p>
                            <form class="public-profile-report-actions" action="<?= $escape($basePath) ?>/communaute/signalement" method="post">
                                <input type="hidden" name="_csrf" value="<?= $escape($reportCsrf) ?>">
                                <input type="hidden" name="member_id" value="<?= (int) $member->id ?>">
                                <button class="public-profile-report-cancel" type="button" command="close" commandfor="report-dialog" autofocus>Annuler</button>
                                <button class="public-profile-report-submit" type="submit">Envoyer le signalement</button>
                            </form>
                        </dialog>
                    <?php endif; ?>
                </div>
            </section>

            <section class="public-profile-stats" aria-label="Statistiques publiques"><div><strong><?= count($playedQuizzes) ?></strong><span>quiz réalisés</span></div><div><strong><?= count($createdQuizzes) ?></strong><span>quiz publiés</span></div><div><strong><?= count($earnedAchievements) ?></strong><span>succès</span></div></section>

            <section class="member-quiz-section public-profile-section"><div class="member-quiz-section-heading"><div><p class="home-eyebrow">Activité</p><h2>Quiz réalisés</h2></div></div>
                <?php if ($playedQuizzes === array()): ?><div class="member-quiz-empty"><h3>Aucun quiz affiché</h3><p>Les résultats des quiz officiels et des quiz membres publics apparaîtront ici.</p></div><?php else: ?><div class="public-profile-quiz-list"><?php foreach ($playedQuizzes as $item): ?><?php $quiz = $item['quiz']; $image = $item['image']; $quizUrl = $basePath . ($quiz->isMemberCreated ? '/quiz-membres/jouer?id=' : '/quiz-officiel?id=') . (int) $quiz->id; ?><article class="public-profile-quiz-row"><?php if ($image !== null): ?><img src="<?= $escape($assetUrl($image->source)) ?>" alt="<?= $escape($image->altText) ?>" loading="lazy"><?php elseif ($quiz->source !== null): ?><img src="<?= $escape($assetUrl($quiz->source)) ?>" alt="Image de <?= $escape($quiz->name) ?>" loading="lazy"><?php else: ?><span class="public-profile-quiz-placeholder">Q?</span><?php endif; ?><div><a href="<?= $escape($quizUrl) ?>"><?= $escape($quiz->name) ?></a><span><?= $escape($item['statistic']->playedAt) ?> · <?= (int) $item['statistic']->score ?> point(s)</span></div></article><?php endforeach; ?></div><?php endif; ?>
            </section>

            <section class="member-quiz-section public-profile-section"><div class="member-quiz-section-heading"><div><p class="home-eyebrow">Créations</p><h2>Quiz publiés</h2></div></div>
                <?php if ($createdQuizzes === array()): ?><div class="member-quiz-empty"><h3>Aucun quiz public</h3><p>Ce membre n’a pas encore publié de quiz public.</p></div><?php else: ?><div class="member-quiz-grid"><?php foreach ($createdQuizzes as $item): ?><?php $quiz = $item['quiz']; $image = $item['image']; ?><article class="member-quiz-card"><a class="member-quiz-image" href="<?= $escape($basePath) ?>/quiz-membres/jouer?id=<?= (int) $quiz->id ?>"><?php if ($image !== null): ?><img src="<?= $escape($assetUrl($image->source)) ?>" alt="<?= $escape($image->altText) ?>" loading="lazy"><?php elseif ($quiz->source !== null): ?><img src="<?= $escape($assetUrl($quiz->source)) ?>" alt="Image de <?= $escape($quiz->name) ?>" loading="lazy"><?php else: ?><span>Q?</span><?php endif; ?></a><div class="member-quiz-content"><div class="member-quiz-card-heading"><a href="<?= $escape($basePath) ?>/quiz-membres/jouer?id=<?= (int) $quiz->id ?>"><?= $escape($quiz->name) ?></a><span>+<?= (int) $quiz->experiencePoints ?> XP</span></div><p><?= $escape($quiz->welcomeText) ?></p><div class="member-quiz-meta"><span><?= (int) $quiz->questionCount ?> question(s)</span><span><?= (int) $quiz->viewCount ?> vue(s)</span></div></div></article><?php endforeach; ?></div><?php endif; ?>
            </section>

            <section class="member-quiz-section public-profile-section"><div class="member-quiz-section-heading"><div><p class="home-eyebrow">Progression</p><h2>Succès</h2></div></div>
                <?php if ($earnedAchievements === array()): ?><div class="member-quiz-empty"><h3>Aucun succès débloqué</h3></div><?php else: ?><div class="achievement-grid"><?php foreach ($earnedAchievements as $item): ?><article class="achievement-card"><span class="achievement-icon" aria-hidden="true">✦</span><div><h3><?= $escape($item['achievement']->name) ?></h3><p><?= $escape($item['achievement']->description) ?></p><small>+<?= (int) $item['achievement']->experienceReward ?> XP · <?= $escape($item['grant']->awardedAt) ?></small></div></article><?php endforeach; ?></div><?php endif; ?>
            </section>
    </main>
    <?php require __DIR__ . '/Shared/site-footer.php'; ?>
</div>
<?php \QETG\Presentation\View\Shared\ApplicationLayout::renderDocumentEnd(); ?>
