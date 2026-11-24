<?php

$uploadEscape = array(\QETG\Presentation\View\Shared\ViewHelpers::class, 'escape');
$uploadId = $uploadEscape($imageUpload['id']);
?>
<div class="image-upload" data-image-upload>
    <label class="image-upload-label" for="<?= $uploadId ?>"><?= $uploadEscape($imageUpload['label']) ?></label>
    <div class="image-upload-control">
        <input id="<?= $uploadId ?>" type="file" name="<?= $uploadEscape($imageUpload['name']) ?>" accept="image/jpeg,image/png,image/gif,image/webp" aria-describedby="<?= $uploadId ?>-hint"<?= !empty($imageUpload['required']) ? ' required' : '' ?>>
        <span class="image-upload-presentation" aria-hidden="true">
            <span class="image-upload-button"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4m-5 5 5-5 5 5M4 16v4h16v-4"/></svg> Choisir une image</span>
            <span class="image-upload-filename" data-image-upload-name>Aucune image sélectionnée</span>
        </span>
    </div>
    <p class="image-upload-hint" id="<?= $uploadId ?>-hint">JPEG, PNG, GIF ou WebP · <?= (int) $imageUpload['maxMb'] ?> Mo maximum<?php if (!empty($imageUpload['hint'])): ?>. <?= $uploadEscape($imageUpload['hint']) ?><?php endif; ?></p>
</div>