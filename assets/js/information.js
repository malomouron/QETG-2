(() => {
    'use strict';

    const input = document.getElementById('help-search-input');
    const items = Array.from(document.querySelectorAll('.help-faq'));
    const empty = document.getElementById('help-no-results');
    if (!input || items.length === 0 || !empty) {
        return;
    }

    input.addEventListener('input', () => {
        const query = input.value.trim().toLocaleLowerCase('fr');
        let visibleCount = 0;
        items.forEach((item) => {
            const searchable = `${item.dataset.search || ''} ${item.textContent}`.toLocaleLowerCase('fr');
            item.hidden = query !== '' && !searchable.includes(query);
            if (!item.hidden) {
                visibleCount += 1;
            }
        });
        empty.hidden = visibleCount > 0;
    });
})();
