<?php
function homeEscape(string $value): string
{
    return \QETG\Presentation\View\Shared\ViewHelpers::escape($value);
}

function homeAssetUrl(string $path, string $basePath): string
{
    return \QETG\Presentation\View\Shared\ViewHelpers::assetUrl($path, $basePath);
}

$css = array(
    $basePath . '/css.css',
    $basePath . '/assets/css/legacy-bandeau.css',
    $basePath . '/assets/css/home.css',
);
\QETG\Presentation\View\Shared\ApplicationLayout::renderDocumentStart(
    'Quiz en tout genre',
    $css,
    'home-page',
    'Découvrez et partagez des quiz en tout genre.'
);
?>
<div class="home-shell">
    <?php $currentPage = 'home'; require __DIR__ . '/Shared/site-header.php'; ?>

    <main>
        <?php if (is_array($interactionFlash) && isset($interactionFlash['message'])): ?>
            <div class="account-alert account-alert--<?= homeEscape((string) ($interactionFlash['type'] ?? 'info')) ?> home-interaction-alert" role="status"><?= homeEscape((string) $interactionFlash['message']) ?></div>
        <?php endif; ?>
        <section class="home-hero" aria-labelledby="home-title">
            <div class="home-hero-copy">
                <p class="home-eyebrow">Apprendre · Jouer · Partager</p>
                <h1 id="home-title">Bienvenue<?= $loggedIn ? ', ' . homeEscape($displayLogin) : '' ?> dans l’univers des quiz</h1>
                <p>Explorez des sujets variés, testez vos connaissances et découvrez les quiz créés par la communauté.</p>
                <div class="home-hero-actions">
                    <a class="home-button" href="#catalogue">Découvrir les quiz <span aria-hidden="true">↓</span></a>
                    <a class="home-button home-button--outline" href="<?= homeEscape($basePath) ?>/quiz-membres">Quiz de la communauté</a>
                </div>
            </div>
            <div class="home-hero-art" aria-hidden="true">
                <span class="hero-orbit hero-orbit--one"></span>
                <span class="hero-orbit hero-orbit--two"></span>
                <span class="hero-tile hero-tile--one">?</span>
                <span class="hero-tile hero-tile--two">✓</span>
                <span class="hero-tile hero-tile--three">Q</span>
            </div>
        </section>

        <div class="home-content-grid">
            <section class="home-main-column" id="catalogue" aria-labelledby="catalogue-title">
                <div class="section-heading">
                    <div>
                        <p class="home-eyebrow">À vous de jouer</p>
                        <h2 id="catalogue-title">Quiz à la une</h2>
                    </div>
                    <form class="home-search" action="<?= homeEscape($searchUrl) ?>" method="get" role="search">
                        <label class="visually-hidden" for="home-search-input">Rechercher un quiz</label>
                        <input id="home-search-input" type="search" name="search" placeholder="Rechercher un quiz…">
                        <button type="submit" aria-label="Rechercher">⌕</button>
                    </form>
                </div>

                <?php if ($quizCards === array()): ?>
                    <div class="home-empty-state">
                        <span aria-hidden="true">✦</span>
                        <h3>Les quiz arrivent bientôt</h3>
                        <p>Il n’y a pas encore de quiz à afficher.</p>
                    </div>
                <?php else: ?>
                    <div class="quiz-grid">
                        <?php foreach ($quizCards as $card): ?>
                            <?php $quiz = $card['quiz']; ?>
                            <article class="quiz-card">
                                <a class="quiz-card-image-link" href="<?= homeEscape($basePath) ?>/quiz-officiel?id=<?= (int) $quiz->id ?>">
                                    <?php if ($card['image'] !== null): ?>
                                        <img class="quiz-card-image" src="<?= homeEscape(homeAssetUrl($card['image']->source, $basePath)) ?>" alt="<?= homeEscape($card['image']->altText) ?>" loading="lazy">
                                    <?php else: ?>
                                        <div class="quiz-card-image quiz-card-image--empty" aria-hidden="true">Q?</div>
                                    <?php endif; ?>
                                </a>
                                <div class="quiz-card-body">
                                    <a class="quiz-card-title" href="<?= homeEscape($basePath) ?>/quiz-officiel?id=<?= (int) $quiz->id ?>"><?= homeEscape($quiz->name) ?></a>
                                    <?php if ($quiz->welcomeText !== ''): ?><p class="quiz-card-description"><?= homeEscape($quiz->welcomeText) ?></p><?php endif; ?>
                                    <div class="quiz-card-footer">
                                        <div class="quiz-reactions" aria-label="Réactions">
                                            <form class="reaction-form" action="<?= homeEscape($basePath) ?>/interactions/reaction" method="post">
                                                <input type="hidden" name="_csrf" value="<?= homeEscape($interactionCsrf) ?>">
                                                <input type="hidden" name="quiz_id" value="<?= (int) $quiz->id ?>">
                                                <input type="hidden" name="value" value="1">
                                                <input type="hidden" name="return_to" value="<?= homeEscape($homeUrl . '#catalogue') ?>">
                                                <button class="reaction-link<?= $card['myReaction'] === 1 ? ' is-liked' : '' ?>" type="submit" aria-label="J’aime, <?= (int) $card['likes'] ?> votes">♥ <span><?= (int) $card['likes'] ?></span></button>
                                            </form>
                                            <form class="reaction-form" action="<?= homeEscape($basePath) ?>/interactions/reaction" method="post">
                                                <input type="hidden" name="_csrf" value="<?= homeEscape($interactionCsrf) ?>">
                                                <input type="hidden" name="quiz_id" value="<?= (int) $quiz->id ?>">
                                                <input type="hidden" name="value" value="0">
                                                <input type="hidden" name="return_to" value="<?= homeEscape($homeUrl . '#catalogue') ?>">
                                                <button class="reaction-link reaction-link--down<?= $card['myReaction'] === 0 ? ' is-disliked' : '' ?>" type="submit" aria-label="Je n’aime pas, <?= (int) $card['dislikes'] ?> votes">♡ <span><?= (int) $card['dislikes'] ?></span></button>
                                            </form>
                                        </div>
                                        <?php if ($card['isFavorite']): ?><span class="favorite-label" title="Quiz favori">★ Favori</span><?php endif; ?>
                                        <a class="quiz-open-link" href="<?= homeEscape($basePath) ?>/quiz-officiel?id=<?= (int) $quiz->id ?>">Jouer <span aria-hidden="true">→</span></a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <nav class="home-pagination" aria-label="Pagination des quiz">
                    <?php if ($page > 1): ?><a href="<?= homeEscape($homeUrl) ?>?page=<?= $page - 1 ?>">← Précédent</a><?php endif; ?>
                    <span>Page <?= $page ?> sur <?= $totalPages ?></span>
                    <?php if ($page < $totalPages): ?><a href="<?= homeEscape($homeUrl) ?>?page=<?= $page + 1 ?>">Suivant →</a><?php endif; ?>
                </nav>
            </section>

            <aside class="home-sidebar" aria-label="Informations et actions">
                <section class="sidebar-card progress-card">
                    <p class="home-eyebrow">Votre parcours</p>
                    <?php if ($loggedIn): ?>
                        <h2><?= $completedOfficialQuizzes ?> <span>/ <?= $totalQuizzes ?></span></h2>
                        <p>quiz officiels réalisés</p>
                        <progress value="<?= min($completedOfficialQuizzes, $totalQuizzes) ?>" max="<?= max(1, $totalQuizzes) ?>"> <?= $completedOfficialQuizzes ?> / <?= $totalQuizzes ?></progress>
                        <a class="sidebar-text-link" href="<?= homeEscape($basePath) ?>/compte/profil">Voir mon profil →</a>
                    <?php else: ?>
                        <h2>À votre rythme</h2>
                        <p>Connectez-vous pour suivre vos résultats et retrouver votre progression.</p>
                        <a class="sidebar-text-link" href="<?= homeEscape($basePath) ?>/connexion">Me connecter →</a>
                    <?php endif; ?>
                </section>
                <section class="sidebar-card create-card">
                    <span class="create-card-icon" aria-hidden="true">＋</span>
                    <h2>Votre quiz, vos règles</h2>
                    <p>Créez un quiz et partagez-le avec les autres membres.</p>
                    <a class="home-button home-button--full" href="<?= homeEscape($basePath) ?>/quiz-membres/editeur">Créer un quiz</a>
                </section>
                <a class="help-card" href="<?= homeEscape($basePath) ?>/aide"><span aria-hidden="true">?</span><span><strong>Besoin d’aide ?</strong><small>Consulter le centre d’aide</small></span><b aria-hidden="true">→</b></a>
            </aside>
        </div>

        <section class="community-section" id="commentaires" aria-labelledby="community-title">
            <div class="section-heading">
                <div>
                    <p class="home-eyebrow">La communauté</p>
                    <h2 id="community-title">Un mot à nous laisser ?</h2>
                </div>
                <?php if (!$captchaMisconfigured): ?>
                    <p>Une idée ou un commentaire sur le site ? Écrivez-nous.</p>
                <?php endif; ?>
            </div>
            <?php if ($captchaMisconfigured): ?>
                <p role="status">L’envoi de commentaires est temporairement indisponible : la vérification anti-robot est mal configurée.</p>
            <?php else: ?>
                <form action="<?= homeEscape($basePath) ?>/interactions/commentaire" method="post" id="home-comment-form" class="home-comment-form">
                    <input type="hidden" name="_csrf" value="<?= homeEscape($interactionCsrf) ?>">
                    <?php if ($loggedIn && $member !== null): ?>
                        <input type="hidden" name="user_name" value="<?= homeEscape($member->lastName . ' (' . $member->login . ')') ?>">
                        <input type="hidden" name="user_prenom" value="<?= homeEscape($member->firstName) ?>">
                        <input type="hidden" name="user_mail" value="<?= homeEscape($member->email) ?>">
                        <div class="comment-identity">Message envoyé par <strong><?= homeEscape($displayName !== '' ? $displayName : $displayLogin) ?></strong></div>
                    <?php else: ?>
                        <label>Nom<input type="text" name="user_name" required autocomplete="family-name"></label>
                        <label>Prénom<input type="text" name="user_prenom" required autocomplete="given-name"></label>
                        <label class="comment-email">E-mail<input type="email" name="user_mail" required autocomplete="email"></label>
                    <?php endif; ?>
                    <label class="comment-message">Votre message<textarea name="user_message" rows="4" required></textarea></label>
                    <?php if ($siteKey !== ''): ?>
                        <div class="g-recaptcha" data-sitekey="<?= homeEscape($siteKey) ?>" data-callback="submitComment" data-theme="dark"></div>
                    <?php endif; ?>
                    <button class="home-button" type="submit">Envoyer le message</button>
                </form>
            <?php endif; ?>
        </section>

        <?php if ($commentCards !== array()): ?>
            <section class="recent-comments" aria-labelledby="comments-title">
                <p class="home-eyebrow">Vos retours</p>
                <h2 id="comments-title">Derniers commentaires</h2>
                <div class="comment-grid">
                    <?php foreach ($commentCards as $item): ?>
                        <article class="comment-card">
                            <p><?= nl2br(homeEscape($item['comment']->content)) ?></p>
                            <footer><?= homeEscape(trim($item['comment']->firstName . ' ' . $item['comment']->lastName)) ?> <time><?= homeEscape($item['comment']->createdAt) ?></time></footer>
                            <?php foreach ($item['replies'] as $reply): ?>
                                <div class="comment-reply"><?= nl2br(homeEscape($reply->content)) ?></div>
                            <?php endforeach; ?>
                            <?php if ($loggedIn): ?>
                                <form class="reply-form" action="<?= homeEscape($basePath) ?>/interactions/reponse-commentaire" method="post">
                                    <input type="hidden" name="_csrf" value="<?= homeEscape($interactionCsrf) ?>">
                                    <input type="hidden" name="comment_id" value="<?= (int) $item['comment']->id ?>">
                                    <label class="visually-hidden" for="reply-<?= (int) $item['comment']->id ?>">Répondre au commentaire</label>
                                    <input id="reply-<?= (int) $item['comment']->id ?>" name="reply" maxlength="2000" placeholder="Répondre…" required>
                                    <button type="submit" aria-label="Envoyer la réponse">→</button>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <?php require __DIR__ . '/Shared/site-footer.php'; ?>
</div>
<?php \QETG\Presentation\View\Shared\ApplicationLayout::renderDocumentEnd(); ?>