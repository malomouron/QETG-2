<?php

use QETG\Domain\Entity\Difficulty;
use QETG\Presentation\View\Shared\ApplicationLayout;
use QETG\Presentation\View\Shared\ViewHelpers;

/** @var list<Difficulty> $difficulties */
$escape = array(ViewHelpers::class, 'escape');
$basePath = rtrim($basePath, '/');
$dashboardUrl = $basePath . '/administration';
$pageSize = 20;
$pageUrl = static fn (int $quiz, int $report, string $anchor): string => $dashboardUrl . '?' . http_build_query(array(
    'quiz_page' => $quiz,
    'report_page' => $report,
)) . '#' . $anchor;
$renderPager = static function (string $anchor, string $label, int $current, int $total) use ($escape, $pageUrl, $pageSize, $quizPage, $reportPage): void {
    $last = $total > 0 ? intdiv($total - 1, $pageSize) + 1 : 1;
    if ($last === 1 && $current === 1) {
        return;
    }
    $url = static fn (int $page): string => $anchor === 'quiz'
        ? $pageUrl($page, $reportPage, $anchor)
        : $pageUrl($quizPage, $page, $anchor);
    ?>
    <nav class="admin-pagination" aria-label="<?= $escape($label) ?>">
        <?php if ($current > $last): ?>
            <a href="<?= $escape($url($last)) ?>">Revenir à la dernière page</a>
        <?php else: ?>
            <?php if ($current > 1): ?><a href="<?= $escape($url($current - 1)) ?>">← Précédente</a><?php endif; ?>
            <span>Page <?= $current ?> sur <?= $last ?></span>
            <?php if ($current < $last): ?><a href="<?= $escape($url($current + 1)) ?>">Suivante →</a><?php endif; ?>
        <?php endif; ?>
    </nav>
    <?php
};

$statsLabels = array(
    'members' => 'Membres',
    'official' => 'Quiz officiels',
    'community' => 'Quiz communautaires',
    'reports' => 'Signalements',
    'completions' => 'Quiz terminés',
);
$formatCount = static fn (int $count): string => number_format($count, 0, ',', "\u{202F}");
$traffic = $data['traffic'] ?? null;
$trafficMaximum = is_array($traffic) ? max(1, ...array_map(static fn (array $day): int => max($day['pageViews'], $day['visitorSessions']), $traffic['days'])) : 1;
$flashType = $flash['type'] ?? 'info';
if (!in_array($flashType, array('error', 'success', 'info'), true)) {
    $flashType = 'info';
}
$draftQuestions = array_values($draft['questions'] ?? array());
$editorDifficulties = array_map(static fn (Difficulty $difficulty): array => array('id' => $difficulty->id, 'label' => $difficulty->label), $difficulties);
$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR;

ApplicationLayout::renderDocumentStart('Administration', array(
    $basePath . '/css.css',
    $basePath . '/assets/css/legacy-bandeau.css',
    $basePath . '/assets/css/home.css',
    $basePath . '/assets/css/member-quizzes.css',
    $basePath . '/assets/css/administration.css',
), 'home-page administration-page');
?>
<div class="home-shell">
    <?php $currentPage = 'administration'; require __DIR__ . '/Shared/site-header.php'; ?>
    <main class="member-quiz-main admin-dashboard">
        <section class="member-quiz-hero admin-hero" aria-labelledby="admin-title">
            <p class="home-eyebrow">Espace de gestion</p>
            <h1 id="admin-title">Tableau de bord</h1>
            <p>Suivez l’activité du site, publiez des quiz officiels et traitez les signalements de comptes.</p>
            <nav class="home-hero-actions" aria-label="Sections du tableau de bord">
                <a class="home-button" href="#creer-quiz">Créer un quiz officiel</a>
                <a class="home-button home-button--outline" href="#quiz">Quiz officiels</a>
                <a class="home-button home-button--outline" href="#signalements">Signalements</a>
            </nav>
        </section>

        <?php if ($flash !== null): ?>
            <p class="admin-notice admin-notice--<?= $flashType ?>" role="<?= $flashType === 'error' ? 'alert' : 'status' ?>"><?= $escape((string) $flash['message']) ?></p>
        <?php endif; ?>

        <section class="admin-overview" aria-labelledby="admin-overview-title">
            <div class="admin-section-heading">
                <h2 id="admin-overview-title">Vue d’ensemble</h2>
                <span class="admin-badge <?= $data === null ? 'admin-badge--warning' : 'admin-badge--ready' ?>"><?= $data === null ? 'Données indisponibles' : 'Données disponibles' ?></span>
            </div>
            <?php if ($data === null): ?>
                <p class="admin-notice admin-notice--info">Les statistiques et les listes de gestion n’ont pas pu être chargées. <a href="<?= $escape($pageUrl($quizPage, $reportPage, 'admin-overview-title')) ?>">Réessayer</a>.</p>
            <?php else: ?>
                <dl class="admin-stats">
                    <?php foreach ($statsLabels as $key => $label): ?>
                        <div class="admin-stat">
                            <dt><?= $label ?></dt>
                            <dd><?= $formatCount($data['stats'][$key]) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </section>

        <section class="admin-panel member-quiz-section admin-health" aria-labelledby="admin-health-title">
            <div class="admin-section-heading">
                <div><p class="home-eyebrow">Surveillance</p><h2 id="admin-health-title">Santé du site</h2></div>
                <span class="admin-badge <?= count(array_filter($siteHealth, static fn (array $check): bool => $check['level'] === 'error')) > 0 ? 'admin-badge--warning' : 'admin-badge--ready' ?>"><?= count(array_filter($siteHealth, static fn (array $check): bool => $check['level'] === 'error')) > 0 ? 'Incident détecté' : 'Vérifications effectuées' ?></span>
            </div>
            <ul class="admin-health-grid" role="list">
                <?php foreach ($siteHealth as $check): ?>
                    <li class="admin-health-card admin-health-card--<?= $escape($check['level']) ?>">
                        <div><h3><?= $escape($check['label']) ?></h3><span class="admin-health-state"><?= $escape($check['state']) ?></span></div>
                        <p><?= $escape($check['detail']) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="admin-panel member-quiz-section admin-traffic" aria-labelledby="admin-traffic-title">
            <div class="admin-section-heading">
                <div><p class="home-eyebrow">Fréquentation</p><h2 id="admin-traffic-title">Visiteurs et pages vues</h2></div>
                <span class="admin-badge">7 derniers jours</span>
            </div>
            <?php if ($traffic === null || $traffic['migrationMissing']): ?>
                <p class="admin-notice admin-notice--info">Les statistiques de fréquentation ne sont pas encore disponibles. Appliquez la migration <strong>202611230004_create_daily_traffic.sql</strong> pour activer le comptage agrégé.</p>
            <?php else: ?>
                <dl class="admin-traffic-summary">
                    <div><dt>Sessions visiteuses aujourd’hui</dt><dd><?= $formatCount($traffic['today']['visitorSessions']) ?></dd></div>
                    <div><dt>Pages vues aujourd’hui</dt><dd><?= $formatCount($traffic['today']['pageViews']) ?></dd></div>
                    <div><dt>Sessions visiteuses · 7 jours</dt><dd><?= $formatCount($traffic['sevenDayTotals']['visitorSessions']) ?></dd></div>
                    <div><dt>Pages vues · 7 jours</dt><dd><?= $formatCount($traffic['sevenDayTotals']['pageViews']) ?></dd></div>
                </dl>
                <div class="admin-traffic-chart" role="group" aria-label="Fréquentation quotidienne : sessions visiteuses et pages vues sur les sept derniers jours">
                    <?php foreach ($traffic['days'] as $day): ?>
                        <div class="admin-traffic-day">
                            <div class="admin-traffic-day-label"><time datetime="<?= $escape($day['date']) ?>"><?= $escape((new DateTimeImmutable($day['date']))->format('d/m')) ?></time><span><?= $formatCount($day['visitorSessions']) ?> sessions · <?= $formatCount($day['pageViews']) ?> pages</span></div>
                            <div class="admin-traffic-bars" aria-hidden="true">
                                <span class="admin-traffic-bar admin-traffic-bar--visitors" style="width: <?= round($day['visitorSessions'] / $trafficMaximum * 100, 2) ?>%"></span>
                                <span class="admin-traffic-bar admin-traffic-bar--pages" style="width: <?= round($day['pageViews'] / $trafficMaximum * 100, 2) ?>%"></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="admin-traffic-privacy">Une session est comptée une seule fois par jour. Ce chiffre estime les sessions du site, pas les personnes uniques ; aucune adresse IP ni aucun agent utilisateur n’est conservé.</p>
            <?php endif; ?>
        </section>

        <section class="member-quiz-section admin-panel" id="quiz" aria-labelledby="admin-quizzes-title">
            <div class="admin-section-heading">
                <div><p class="home-eyebrow">Catalogue</p><h2 id="admin-quizzes-title">Quiz officiels publiés</h2></div>
                <?php if ($data !== null): ?><span class="admin-badge"><?= $formatCount($data['stats']['official']) ?> au total</span><?php endif; ?>
            </div>
            <?php if ($data === null): ?>
                <p class="admin-empty">La liste des quiz n’est pas disponible pour le moment.</p>
            <?php elseif ($data['quizzes'] === array()): ?>
                <p class="admin-empty"><?= $data['stats']['official'] === 0 ? 'Aucun quiz officiel publié pour le moment.' : 'Aucun quiz officiel sur cette page.' ?></p>
            <?php else: ?>
                <ul class="admin-records" role="list">
                    <?php foreach ($data['quizzes'] as $quiz): ?>
                        <li class="admin-record">
                            <div>
                                <h3><?php if ($quiz['public']): ?><a href="<?= $escape($basePath) ?>/quiz-officiel?id=<?= (int) $quiz['id'] ?>"><?= $escape($quiz['title']) ?></a><?php else: ?><?= $escape($quiz['title']) ?><?php endif; ?></h3>
                                <p class="admin-meta"><span>#<?= (int) $quiz['id'] ?></span><span><?= $formatCount($quiz['questions']) ?> <?= $quiz['questions'] === 1 ? 'question' : 'questions' ?></span><span class="admin-badge"><?= $quiz['public'] ? 'Public' : 'Non public' ?></span></p>
                            </div>
                            <details class="admin-confirm">
                                <summary>Supprimer le quiz<span class="visually-hidden"> : <?= $escape($quiz['title']) ?></span></summary>
                                <form action="<?= $escape($dashboardUrl) ?>/quiz/supprimer" method="post">
                                    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                                    <input type="hidden" name="quiz_id" value="<?= (int) $quiz['id'] ?>">
                                    <p>Cette action supprime définitivement le quiz et ses questions.</p>
                                    <button class="admin-danger" type="submit">Confirmer la suppression du quiz</button>
                                </form>
                            </details>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($data !== null) { $renderPager('quiz', 'Pagination des quiz officiels', $quizPage, $data['stats']['official']); } ?>
        </section>

        <section class="member-quiz-section admin-panel" id="signalements" aria-labelledby="admin-reports-title">
            <div class="admin-section-heading">
                <div><p class="home-eyebrow">Modération</p><h2 id="admin-reports-title">Comptes signalés</h2></div>
                <?php if ($data !== null): ?><span class="admin-badge"><?= $formatCount($data['reportTargets']) ?> au total</span><?php endif; ?>
            </div>
            <?php if ($data === null): ?>
                <p class="admin-empty">La liste des signalements n’est pas disponible pour le moment.</p>
            <?php elseif ($data['reports'] === array()): ?>
                <p class="admin-empty"><?= $data['reportTargets'] === 0 ? 'Aucun compte signalé pour le moment.' : 'Aucun compte signalé sur cette page.' ?></p>
            <?php else: ?>
                <ul class="admin-records" role="list">
                    <?php foreach ($data['reports'] as $report): ?>
                        <li class="admin-record">
                            <div>
                                <h3><a href="<?= $escape($basePath) ?>/profil-public?id=<?= (int) $report['target_id'] ?>"><?= $escape($report['target_login']) ?></a></h3>
                                <p class="admin-meta"><span>#<?= (int) $report['target_id'] ?></span><span><?= $formatCount($report['report_count']) ?> <?= $report['report_count'] === 1 ? 'signalement' : 'signalements' ?></span></p>
                                <?php if (trim($report['reporters'] ?? '') !== ''): ?>
                                    <p class="admin-sources"><strong>Signalé par :</strong> <?= $escape($report['reporters']) ?></p>
                                <?php endif; ?>
                            </div>
                            <?php if ($report['target_admin']): ?>
                                <span class="admin-badge">Administrateur · compte protégé</span>
                            <?php else: ?>
                                <details class="admin-confirm">
                                    <summary>Supprimer le compte<span class="visually-hidden"> : <?= $escape($report['target_login']) ?></span></summary>
                                    <form action="<?= $escape($dashboardUrl) ?>/signalements/supprimer-compte" method="post">
                                        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                                        <input type="hidden" name="member_id" value="<?= (int) $report['target_id'] ?>">
                                        <p>Le compte et ses contenus communautaires seront supprimés définitivement. Si ce membre possède un quiz officiel, la suppression sera refusée.</p>
                                        <button class="admin-danger" type="submit">Confirmer la suppression du compte</button>
                                    </form>
                                </details>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($data !== null) { $renderPager('signalements', 'Pagination des comptes signalés', $reportPage, $data['reportTargets']); } ?>
        </section>

        <section class="member-quiz-section admin-panel" id="creer-quiz" aria-labelledby="admin-create-title">
            <div class="admin-section-heading">
                <div><p class="home-eyebrow">Éditeur</p><h2 id="admin-create-title">Créer un quiz officiel</h2></div>
            </div>
            <p class="admin-intro">Préparez de 1 à 100 questions. Choisissez une difficulté, ajoutez de 2 à 5 propositions et indiquez la bonne réponse pour chaque question.</p>
            <?php if ($editorDifficulties === array()): ?><p class="admin-notice admin-notice--info">Aucune difficulté n’est disponible. La publication est temporairement désactivée.</p><?php endif; ?>
            <noscript><p class="admin-notice admin-notice--info">Activez JavaScript pour ajouter des questions et publier le quiz.</p></noscript>
            <form class="member-editor-form admin-editor" action="<?= $escape($dashboardUrl) ?>/quiz/creer" method="post">
                <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                <div class="admin-editor-fields">
                    <label class="member-editor-field">Titre du quiz<input type="text" name="title" maxlength="50" value="<?= $escape((string) ($draft['title'] ?? '')) ?>" required></label>
                    <label class="member-editor-field">XP à gagner<input type="number" name="xp" min="0" step="1" value="<?= $escape((string) ($draft['xp'] ?? '')) ?>" required></label>
                    <label class="member-editor-field admin-editor-description">Description<textarea name="description" maxlength="5000" rows="3"><?= $escape((string) ($draft['description'] ?? '')) ?></textarea></label>
                </div>
                <div class="member-editor-question-heading">
                    <h3>Questions</h3>
                    <button class="home-button home-button--small" id="add-question" type="button">＋ Ajouter une question</button>
                </div>
                <div id="question-list" class="member-editor-questions"></div>
                <button class="home-button admin-publish" type="submit"<?= $editorDifficulties === array() ? ' disabled' : '' ?>>Créer et publier le quiz</button>
            </form>
        </section>
    </main>
    <?php require __DIR__ . '/Shared/site-footer.php'; ?>
</div>
<script type="application/json" id="initial-question-data"><?= json_encode($draftQuestions, $jsonFlags) ?></script>
<script type="application/json" id="difficulty-data"><?= json_encode($editorDifficulties, $jsonFlags) ?></script>
<?php
ApplicationLayout::renderScript($basePath . '/assets/js/member-quiz-editor.js');
ApplicationLayout::renderDocumentEnd();
?>