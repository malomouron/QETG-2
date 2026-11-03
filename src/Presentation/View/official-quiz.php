<?php

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$assetUrl = static function (string $path) use ($basePath): string {
    if (preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $path) === 1) {
        return $path;
    }
    return rtrim($basePath, '/') . '/' . ltrim($path, '/');
};
$flash = $_SESSION['official_quiz_flash'] ?? null;
unset($_SESSION['official_quiz_flash']);
$css = array(
    $basePath . '/css.css',
    $basePath . '/commun/bandeau.css',
    $basePath . '/resources/assets/css/home.css',
    $basePath . '/resources/assets/css/official-quiz.css',
);
$title = $page === 'presentation' && isset($quiz) ? $quiz->name : 'Quiz officiel';
?>
<!doctype html>
<html lang="fr">
<head>
<?php afficher_head($title, $css, 'UTF-8'); ?>
<meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body class="home-page official-quiz-page">
<div class="home-shell">
    <header class="bandeau home-header">
        <a class="home-brand" href="<?= $escape($basePath) ?>/accueil"><img src="<?= $escape($basePath) ?>/assets/images/community/icon.png" alt="" class="home-brand-icon"><span>Quiz en tout genre</span></a>
        <nav class="home-nav" aria-label="Navigation principale">
            <a href="<?= $escape($basePath) ?>/accueil">Accueil</a>
            <a href="<?= $escape($basePath) ?>/quiz_utilisateur.php">Quiz membres</a>
            <a href="<?= $escape($basePath) ?>/aide">Aide</a>
        </nav>
        <div class="home-account">
            <?php if (isset($_SESSION['login'])): ?>
                <a class="home-account-link" href="<?= $escape($basePath) ?>/compte/profil"><?= $escape((string) $_SESSION['login']) ?></a>
            <?php else: ?>
                <a class="home-account-link" href="<?= $escape($basePath) ?>/connexion">Connexion</a>
            <?php endif; ?>
        </div>
    </header>

    <main class="official-quiz-main">
        <?php if (is_array($flash) && isset($flash['message'])): ?>
            <div class="account-alert account-alert--<?= $escape((string) ($flash['type'] ?? 'info')) ?>" role="status"><?= $escape((string) $flash['message']) ?></div>
        <?php endif; ?>

        <?php if ($page === 'presentation' && $quiz !== null): ?>
            <a class="official-back-link" href="<?= $escape($basePath) ?>/accueil#catalogue">← Retour aux quiz</a>
            <section class="official-presentation">
                <div class="official-presentation-copy">
                    <p class="home-eyebrow">Quiz officiel · <?= (int) $totalQuestions ?> questions · +<?= (int) $quiz->experiencePoints ?> XP</p>
                    <h1><?= $escape($quiz->name) ?></h1>
                    <p class="official-description"><?= nl2br($escape($quiz->welcomeText)) ?></p>
                    <p class="official-note">Répondez à toutes les questions. Une mauvaise réponse vous ramène au début ; si vous êtes connecté, votre progression est enregistrée entre les questions.</p>
                    <a class="home-button official-start-button" href="<?= $escape($basePath) ?>/quiz/question?id_quiz=<?= (int) $quiz->id ?>&amp;numero_question=<?= (int) $resumeNumber ?>">
                        <?= $resumeNumber > 1 ? 'Reprendre à la question ' . (int) $resumeNumber : 'Commencer le quiz' ?> <span aria-hidden="true">→</span>
                    </a>
                    <?php if (!isset($_SESSION['id'])): ?><p class="official-login-hint"><a href="<?= $escape($basePath) ?>/connexion">Connectez-vous</a> pour enregistrer votre progression et votre résultat.</p><?php endif; ?>
                </div>
                <div class="official-presentation-art">
                    <?php if ($image !== null): ?><img src="<?= $escape($assetUrl($image->source)) ?>" alt="<?= $escape($image->altText) ?>"><?php else: ?><span aria-hidden="true">Q?</span><?php endif; ?>
                </div>
            </section>
            <div class="official-benefits"><span>✓ Progression enregistrée pour les membres</span><span>✓ Succès et XP à gagner</span><span>✓ Recommencez à tout moment</span></div>

        <?php elseif ($page === 'question' && $quiz !== null && $question !== null): ?>
            <a class="official-back-link" href="<?= $escape($basePath) ?>/quiz-officiel?id=<?= (int) $quiz->id ?>">Quitter le quiz</a>
            <section class="question-card">
                <div class="question-card-top">
                    <div><p class="home-eyebrow"><?= $escape($quiz->name) ?></p><h1>Question <?= (int) $question->number ?> <span>/ <?= (int) $totalQuestions ?></span></h1></div>
                    <?php if ($difficulty !== null): ?><span class="difficulty-badge"><?= $escape($difficulty->label) ?></span><?php endif; ?>
                </div>
                <div class="question-progress" role="progressbar" aria-label="Progression du quiz" aria-valuemin="1" aria-valuemax="<?= (int) $totalQuestions ?>" aria-valuenow="<?= (int) $question->number ?>"><span style="width: <?= (int) round($question->number / max(1, $totalQuestions) * 100) ?>%"></span></div>
                <?php if ($question->welcomeText !== null && trim($question->welcomeText) !== ''): ?><p class="question-welcome"><?= $escape($question->welcomeText) ?></p><?php endif; ?>
                <div class="question-content">
                    <?php if ($image !== null): ?><img class="question-image" src="<?= $escape($assetUrl($image->source)) ?>" alt="<?= $escape($image->altText) ?>"><?php endif; ?>
                    <h2><?= nl2br($escape($question->text)) ?></h2>
                </div>
                <form action="<?= $escape($basePath) ?>/quiz/reponse" method="post" class="answer-form">
                    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="id_quiz" value="<?= (int) $quiz->id ?>">
                    <input type="hidden" name="id_question" value="<?= (int) $question->id ?>">
                    <fieldset>
                        <legend>Choisissez une réponse</legend>
                        <?php foreach ($answers as $index => $answer): ?>
                            <label class="answer-option">
                                <input type="radio" name="id_reponse" value="<?= (int) $answer->id ?>" required>
                                <span class="answer-letter"><?= chr(65 + $index) ?></span>
                                <span class="answer-choice"><?= $escape($answer->choice) ?></span>
                                <span class="answer-check" aria-hidden="true">✓</span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <button class="home-button official-submit-button" type="submit">Valider ma réponse <span aria-hidden="true">→</span></button>
                </form>
            </section>

        <?php elseif ($page === 'result' && $quiz !== null && $question !== null && is_array($result)): ?>
            <a class="official-back-link" href="<?= $escape($basePath) ?>/accueil#catalogue">← Retour aux quiz</a>
            <section class="answer-result<?= $result['correct'] ? ' answer-result--correct' : ' answer-result--wrong' ?>">
                <div class="result-icon" aria-hidden="true"><?= $result['correct'] ? '✓' : '↻' ?></div>
                <p class="home-eyebrow"><?= $result['correct'] ? 'Bien joué' : 'On réessaie' ?></p>
                <h1><?= $result['correct'] ? 'Bonne réponse !' : 'Ce n’est pas la bonne réponse.' ?></h1>
                <?php if ($result['correct'] && !empty($result['completed'])): ?>
                    <p class="result-lead">Quiz terminé : <?= (int) $result['totalQuestions'] ?> réponses justes sur <?= (int) $result['totalQuestions'] ?>.</p>
                <?php elseif (!$result['correct']): ?>
                    <p class="result-lead">La bonne réponse était : <strong><?= $escape($result['correctAnswer']?->choice ?? 'Indisponible') ?></strong></p>
                <?php else: ?>
                    <p class="result-lead">Question <?= (int) $question->number ?> validée. Continuez quand vous êtes prêt.</p>
                <?php endif; ?>
                <?php $explanation = $result['correctAnswer']?->explanation ?? $result['selectedAnswer']->explanation; ?>
                <?php if ($explanation !== null && trim($explanation) !== ''): ?><p class="result-explanation"><?= nl2br($escape($explanation)) ?></p><?php endif; ?>
                <?php if ($image !== null): ?><img class="result-image" src="<?= $escape($assetUrl($image->source)) ?>" alt="<?= $escape($image->altText) ?>"><?php endif; ?>
                <div class="result-actions">
                    <?php if (!empty($result['restart'])): ?>
                        <a class="home-button" href="<?= $escape($basePath) ?>/quiz-officiel?id=<?= (int) $quiz->id ?>">Recommencer depuis le début</a>
                    <?php elseif (!empty($result['completed'])): ?>
                        <?php if (isset($_SESSION['id'])): ?><a class="home-button" href="<?= $escape($basePath) ?>/compte/profil#historique">Voir mon profil</a><?php endif; ?>
                        <a class="home-button home-button--outline" href="<?= $escape($basePath) ?>/accueil#catalogue">Choisir un autre quiz</a>
                        <a class="result-retry-link" href="<?= $escape($basePath) ?>/quiz-officiel?id=<?= (int) $quiz->id ?>">Rejouer ce quiz</a>
                    <?php else: ?>
                        <a class="home-button" href="<?= $escape($basePath) ?>/quiz/question?id_quiz=<?= (int) $quiz->id ?>&amp;numero_question=<?= (int) $result['nextNumber'] ?>">Question suivante <span aria-hidden="true">→</span></a>
                    <?php endif; ?>
                </div>
            </section>

        <?php else: ?>
            <section class="quiz-not-found"><p class="home-eyebrow">Quiz introuvable</p><h1>Ce quiz n’est pas disponible.</h1><p>Il a peut-être été retiré ou le lien est incorrect.</p><a class="home-button" href="<?= $escape($basePath) ?>/accueil#catalogue">Retour aux quiz</a></section>
        <?php endif; ?>
    </main>

    <footer class="home-footer"><p>Quiz en tout genre · Créé par Malo MOURON</p><nav aria-label="Navigation de pied de page"><a href="<?= $escape($basePath) ?>/accueil">Accueil</a><a href="<?= $escape($basePath) ?>/aide">Aide</a><a href="<?= $escape($basePath) ?>/a-propos">À propos</a></nav></footer>
</div>
</body>
</html>
