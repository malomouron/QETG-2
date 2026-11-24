<?php

$escape = array(\QETG\Presentation\View\Shared\ViewHelpers::class, 'escape');
$editing = $quiz !== null;
$initialJson = json_encode($initialQuestions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
\QETG\Presentation\View\Shared\ApplicationLayout::renderDocumentStart(
    ($editing ? 'Modifier' : 'Créer') . ' un quiz membre',
    array($basePath . '/css.css', $basePath . '/assets/css/legacy-bandeau.css', $basePath . '/assets/css/home.css', $basePath . '/assets/css/member-quizzes.css'),
    'home-page member-quizzes-page'
);
?>
<div class="home-shell">
    <?php $currentPage = 'create-quiz'; require __DIR__ . '/Shared/site-header.php'; ?>
    <main class="member-quiz-main">
        <section class="member-quiz-hero"><p class="home-eyebrow">Création communautaire</p><h1><?= $editing ? 'Modifier votre quiz' : 'Créer un nouveau quiz' ?></h1><p>Ajoutez une image, choisissez la visibilité et préparez une à cent questions avec leurs propositions et explications.</p></section>
        <?php if (is_array($flash) && isset($flash['message'])): ?><div class="account-alert account-alert--<?= $escape((string) ($flash['type'] ?? 'info')) ?>" role="status"><?= $escape((string) $flash['message']) ?></div><?php endif; ?>
        <form class="member-quiz-section member-editor-form" action="<?= $escape($basePath) ?>/quiz-membres/enregistrer" method="post" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="quiz_id" value="<?= $editing ? (int) $quiz->id : '' ?>">
            <label class="member-editor-field">Nom du quiz<input type="text" name="title" maxlength="50" value="<?= $escape($quiz?->name ?? '') ?>" required></label>
            <label class="member-editor-field">Description<textarea name="description" maxlength="5000" rows="3"><?= $escape($quiz?->welcomeText ?? '') ?></textarea></label>
            <fieldset class="member-editor-visibility"><legend>Visibilité</legend><label><input type="radio" name="visibility" value="public"<?= $quiz === null || $quiz->isPublic ? ' checked' : '' ?>> Public</label><label><input type="radio" name="visibility" value="private"<?= $quiz !== null && !$quiz->isPublic ? ' checked' : '' ?>> Privé (accessible avec son identifiant)</label></fieldset>
            <?php
            $imageUpload = array(
                'id' => 'member-quiz-cover', 'name' => 'cover', 'label' => 'Image de présentation',
                'maxMb' => 4, 'required' => !$editing,
                'hint' => $editing ? 'Sans nouvelle image, la couverture actuelle est conservée.' : '',
            );
            require __DIR__ . '/Shared/image-upload.php';
            ?>
            <div class="member-editor-question-heading"><div><p class="home-eyebrow">Contenu</p><h2>Questions</h2></div><button class="home-button home-button--small" id="add-question" type="button">＋ Ajouter une question</button></div>
            <div id="question-list" class="member-editor-questions"></div>
            <button class="home-button" type="submit"><?= $editing ? 'Enregistrer les modifications' : 'Créer et publier le quiz' ?></button>
            <a class="member-play-retry" href="<?= $escape($basePath) ?>/quiz-membres?tab=mine">Annuler</a>
        </form>
    </main>
    <?php require __DIR__ . '/Shared/site-footer.php'; ?>
</div>
<script type="application/json" id="initial-question-data"><?= $initialJson ?></script>
<script type="application/json" id="difficulty-data"><?= json_encode(array_map(static fn ($difficulty): array => array('id' => $difficulty->id, 'label' => $difficulty->label), $difficulties), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<?php \QETG\Presentation\View\Shared\ApplicationLayout::renderScript($basePath . '/assets/js/member-quiz-editor.js'); ?>
<?php \QETG\Presentation\View\Shared\ApplicationLayout::renderDocumentEnd(); ?>
