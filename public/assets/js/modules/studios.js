(() => {
    const form = document.getElementById('studio-filters');
    if (!form) return;

    const search = document.getElementById('studio-search');
    const state = document.getElementById('studio-state');
    const results = document.getElementById('studio-results');
    const cards = Array.from(results.querySelectorAll('.studio-card'));
    const count = document.getElementById('studio-count');
    const empty = document.getElementById('studio-empty');
    const pagination = document.getElementById('studio-pagination');
    const previous = document.getElementById('studio-previous');
    const next = document.getElementById('studio-next');
    const pageLabel = document.getElementById('studio-page');
    const pageSize = 50;
    let page = 1;

    function render() {
        const term = search.value.trim().toLocaleLowerCase();
        const matches = cards.filter((card) =>
            (!term || card.dataset.search.toLocaleLowerCase().includes(term)) &&
            (!state.value || card.dataset.state === state.value)
        );
        const pageCount = Math.ceil(matches.length / pageSize);
        page = Math.min(page, Math.max(pageCount, 1));
        const visible = new Set(matches.slice((page - 1) * pageSize, page * pageSize));

        cards.forEach((card) => { card.hidden = !visible.has(card); });
        matches.forEach((card, index) => {
            card.querySelector('.studio-card__index').textContent = String(index + 1).padStart(2, '0');
        });
        count.textContent = `${matches.length} ${matches.length === 1 ? 'location' : 'locations'} found`;
        results.hidden = matches.length === 0;
        empty.hidden = matches.length !== 0;
        pagination.hidden = pageCount <= 1;
        previous.disabled = page === 1;
        next.disabled = page === pageCount;
        pageLabel.textContent = `Page ${page} of ${pageCount}`;
    }

    form.addEventListener('submit', (event) => { event.preventDefault(); page = 1; render(); });
    search.addEventListener('input', () => { page = 1; render(); });
    state.addEventListener('change', () => { page = 1; render(); });
    previous.addEventListener('click', () => { page--; render(); });
    next.addEventListener('click', () => { page++; render(); });
    render();
})();
