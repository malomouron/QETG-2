(() => {
    'use strict';

    window.submitComment = () => {
        document.getElementById('home-comment-form')?.requestSubmit();
    };

    document.querySelectorAll('[data-image-upload]').forEach((upload) => {
        const input = upload.querySelector('input[type="file"]');
        const filename = upload.querySelector('[data-image-upload-name]');
        if (!(input instanceof HTMLInputElement) || !filename) return;
        const defaultName = filename.textContent;
        const updateFilename = () => { filename.textContent = input.files?.[0]?.name ?? defaultName; };
        input.addEventListener('change', updateFilename);
        input.form?.addEventListener('reset', () => requestAnimationFrame(updateFilename));
        window.addEventListener('pageshow', updateFilename);
        updateFilename();
        upload.classList.add('is-enhanced');
    });

    document.querySelectorAll('button[commandfor]').forEach((button) => {
        const dialog = document.getElementById(button.getAttribute('commandfor'));
        const command = button.getAttribute('command');
        if (!(dialog instanceof HTMLDialogElement) || typeof dialog.showModal !== 'function') return;
        if (command !== 'show-modal' && command !== 'close') return;
        button.addEventListener('click', (event) => {
            event.preventDefault();
            if (command === 'show-modal' && !dialog.open) dialog.showModal();
            if (command === 'close' && dialog.open) dialog.close();
        });
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) {
            return;
        }
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });

    document.addEventListener('change', (event) => {
        const select = event.target;
        if (!(select instanceof HTMLSelectElement) || !select.matches('[data-translate-select]')) {
            return;
        }
        if (select.value === 'fr') {
            return;
        }

        const translationUrl = new URL('https://translate.google.com/translate');
        translationUrl.searchParams.set('sl', 'fr');
        translationUrl.searchParams.set('tl', select.value);
        translationUrl.searchParams.set('u', window.location.href);
        window.location.assign(translationUrl.toString());
    });

    document.addEventListener('click', async (event) => {
        const cookieButton = event.target.closest('[data-cookie-preferences]');
        if (cookieButton) {
            if (window.tarteaucitron?.userInterface?.openPanel) {
                window.tarteaucitron.userInterface.openPanel();
            }
            return;
        }

        const shareButton = event.target.closest('[data-share-url]');
        if (!shareButton) {
            return;
        }

        const url = shareButton.dataset.shareUrl;
        if (!url) {
            return;
        }
        try {
            await navigator.clipboard.writeText(url);
            shareButton.textContent = 'Lien copié';
        } catch {
            window.prompt('Copiez le lien du quiz :', url);
        }
    });
})();
