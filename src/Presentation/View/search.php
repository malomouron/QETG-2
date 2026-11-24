<?php

$escape = array(\QETG\Presentation\View\Shared\ViewHelpers::class, 'escape');
$assetUrl = static fn (?string $path): string => \QETG\Presentation\View\Shared\ViewHelpers::assetUrl($path, $basePath);
$css = array(
    $basePath . '/css.css',
    $basePath . '/assets/css/legacy-bandeau.css',
    $basePath . '/assets/css/home.css',
    $basePath . '/assets/css/search.css',
);
\QETG\Presentation\View\Shared\ApplicationLayout::renderDocumentStart(
    'Recherche - Quiz en tout genre',
    $css,
    'home-page search-page',
    'Recherchez des quiz officiels et des quiz publics créés par la communauté.'
);
?>
<div class="home-shell">
    <?php require __DIR__ . '/Shared/site-header.php'; ?>

    <main class="search-main">
        <section class="search-hero">
            <p class="home-eyebrow">Explorer le catalogue</p>
            <h1>Rechercher un quiz</h1>
            <p>Parcourez les quiz officiels et les créations publiques de la communauté.</p>
            <form class="search-form" action="<?= $escape($basePath) ?>/recherche" method="get" role="search">
                <label class="visually-hidden" for="search-query">Nom du quiz</label>
                <input id="search-query" type="search" name="search" maxlength="200" placeholder="Ex. géographie, HTML, cinéma…" value="<?= $escape($searchTerm) ?>" autofocus>
                <button class="home-button" type="submit">Rechercher</button>
            </form>
        </section>

        <section class="search-results" aria-live="polite">
            <?php if ($error !== null): ?>
                <div class="search-empty"><h2>Recherche trop longue</h2><p><?= $escape($error) ?></p></div>
            <?php elseif ($searchTerm === ''): ?>
                <div class="search-empty"><h2>Que souhaitez-vous découvrir ?</h2><p>Saisissez un sujet ou un titre pour lancer la recherche.</p></div>
            <?php elseif ($quizCards === array()): ?>
                <div class="search-empty"><h2>Aucun résultat pour « <?= $escape($searchTerm) ?> »</h2><p>Essayez un autre mot ou une recherche plus courte.</p></div>
            <?php else: ?>
                <div class="search-results-heading"><h2>Résultats pour « <?= $escape($searchTerm) ?> »</h2><span><?= count($quizCards) ?> quiz<?= count($quizCards) > 1 ? '' : '' ?></span></div>
                <div class="quiz-grid">
                    <?php foreach ($quizCards as $card): ?>
                        <?php $quiz = $card['quiz']; $image = $card['image']; ?>
                        <?php $quizUrl = $basePath . ($quiz->isMemberCreated ? '/quiz-membres/jouer?id=' : '/quiz-officiel?id=') . (int) $quiz->id; ?>
                        <article class="quiz-card">
                            <a class="quiz-card-image-link" href="<?= $escape($quizUrl) ?>">
                                <?php if ($image !== null): ?>
                                    <img class="quiz-card-image" src="<?= $escape($assetUrl($image->source)) ?>" alt="<?= $escape($image->altText) ?>" loading="lazy">
                                <?php elseif ($quiz->source !== null && $quiz->source !== ''): ?>
                                    <img class="quiz-card-image" src="<?= $escape($assetUrl($quiz->source)) ?>" alt="Illustration du quiz <?= $escape($quiz->name) ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="quiz-card-image quiz-card-image--empty" aria-hidden="true">Q?</div>
                                <?php endif; ?>
                            </a>
                            <div class="quiz-card-body">
                                <a class="quiz-card-title" href="<?= $escape($quizUrl) ?>"><?= $escape($quiz->name) ?></a>
                                <p class="quiz-card-description"><?= $escape($quiz->welcomeText) ?></p>
                                <div class="search-card-footer"><span><?= (int) $quiz->questionCount ?> question<?= $quiz->questionCount > 1 ? 's' : '' ?></span><span>+<?= (int) $quiz->experiencePoints ?> XP</span><a class="quiz-open-link" href="<?= $escape($quizUrl) ?>">Jouer →</a></div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <a class="search-back-link" href="<?= $escape($basePath) ?>/accueil">← Retour à l’accueil</a>
        </section>
    </main>

    <?php require __DIR__ . '/Shared/site-footer.php'; ?>
</div>
<?php \QETG\Presentation\View\Shared\ApplicationLayout::renderDocumentEnd(); ?>