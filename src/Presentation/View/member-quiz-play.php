<?php

$escape = array(\QETG\Presentation\View\Shared\ViewHelpers::class, 'escape');
$assetUrl = static fn (?string $path): string => \QETG\Presentation\View\Shared\ViewHelpers::assetUrl($path, $basePath);
\QETG\Presentation\View\Shared\ApplicationLayout::renderDocumentStart(
    $quiz->name . ' - Quiz de la communauté',
    array($basePath . '/css.css', $basePath . '/assets/css/legacy-bandeau.css', $basePath . '/assets/css/home.css', $basePath . '/assets/css/member-quizzes.css'),
    'home-page member-quizzes-page',
    'Jouez à un quiz créé par la communauté.'
);
?>
<div class="home-shell">
    <?php $currentPage = 'community'; require __DIR__ . '/Shared/site-header.php'; ?>
    <main class="member-quiz-main">
        <section class="member-quiz-hero"><p class="home-eyebrow">Quiz de la communauté</p><h1><?= $escape($quiz->name) ?></h1><p><?= $escape($quiz->welcomeText) ?></p><?php if ($owner !== null): ?><p>Créé par <a href="<?= $escape($basePath) ?>/profil-public?id=<?= (int) $owner->id ?>"><?= $escape($owner->login) ?></a></p><?php endif; ?><?php if ($image !== null): ?><img class="member-play-cover" src="<?= $escape($assetUrl($image->source)) ?>" alt="<?= $escape($image->altText) ?>"><?php elseif ($quiz->source !== null): ?><img class="member-play-cover" src="<?= $escape($assetUrl($quiz->source)) ?>" alt="Image de présentation de <?= $escape($quiz->name) ?>"><?php endif; ?></section>
        <?php if (is_array($error) && isset($error['message'])): ?><div class="account-alert account-alert--<?= $escape((string) ($error['type'] ?? 'info')) ?>" role="status"><?= $escape((string) $error['message']) ?></div><?php endif; ?>
        <section class="member-quiz-section member-play-section">
            <?php if ($result === null): ?>
                <div class="member-quiz-section-heading"><div><p class="home-eyebrow">À vous de jouer</p><h2><?= count($questions) ?> question<?= count($questions) > 1 ? 's' : '' ?> · +<?= (int) $quiz->experiencePoints ?> XP</h2></div></div>
                <form class="member-play-form" action="<?= $escape($basePath) ?>/quiz-membres/reponse" method="post">
                    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="quiz_id" value="<?= (int) $quiz->id ?>">
                    <?php foreach ($questions as $question): ?>
                        <fieldset class="member-play-question"><legend><?= (int) $question->number ?>. <?= $escape($question->text) ?></legend>
                            <?php if ($question->welcomeText !== null && $question->welcomeText !== ''): ?><p><?= $escape($question->welcomeText) ?></p><?php endif; ?>
                            <?php foreach ($answersByQuestion[(int) $question->id] as $answer): ?>
                                <label class="member-play-answer"><input type="radio" name="answers[<?= (int) $question->id ?>]" value="<?= (int) $answer->id ?>" required><span><?= $escape($answer->choice) ?></span><?php $answerImage = $imagesByAnswer[(int) $answer->id] ?? null; ?><?php if ($answerImage !== null): ?><img src="<?= $escape($assetUrl($answerImage->source)) ?>" alt="<?= $escape($answerImage->altText) ?>" loading="lazy"><?php endif; ?></label>
                            <?php endforeach; ?>
                        </fieldset>
                    <?php endforeach; ?>
                    <button class="home-button" type="submit">Valider mes réponses</button>
                </form>
            <?php else: ?>
                <div class="member-quiz-result"><p class="home-eyebrow">Résultat</p><h2>Votre score : <?= (int) $result['correctCount'] ?>/<?= (int) $result['totalQuestions'] ?></h2><p><?= $result['correctCount'] === $result['totalQuestions'] ? 'Bravo, toutes vos réponses sont correctes !' : 'Merci d’avoir participé à ce quiz.' ?></p></div>
                <?php foreach ($result['answers'] as $item): ?>
                    <?php $question = $item['question']; $selectedAnswer = $item['selected']; $correctAnswer = $item['correct']; ?>
                    <article class="member-play-feedback"><h3><?= (int) $question->number ?>. <?= $escape($question->text) ?></h3><p class="<?= $item['isCorrect'] ? 'is-correct' : 'is-incorrect' ?>"><?= $item['isCorrect'] ? 'Bonne réponse' : 'Votre réponse : ' . $escape($selectedAnswer->choice) ?></p><?php if (!$item['isCorrect']): ?><p>Bonne réponse : <strong><?= $escape($correctAnswer->choice) ?></strong></p><?php endif; ?><?php if ($correctAnswer->explanation !== null && $correctAnswer->explanation !== ''): ?><p class="member-play-explanation">Explication : <?= $escape($correctAnswer->explanation) ?></p><?php endif; ?></article>
                <?php endforeach; ?>
                <a class="home-button" href="<?= $escape($basePath) ?>/quiz-membres">Retour aux quiz membres</a>
                <a class="member-play-retry" href="<?= $escape($basePath) ?>/quiz-membres/jouer?id=<?= (int) $quiz->id ?>">Rejouer ce quiz</a>
            <?php endif; ?>
        </section>
    </main>
    <?php require __DIR__ . '/Shared/site-footer.php'; ?>
</div>
<?php \QETG\Presentation\View\Shared\ApplicationLayout::renderDocumentEnd(); ?>
