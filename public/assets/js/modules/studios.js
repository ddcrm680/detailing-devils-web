(() => {
    const form = document.getElementById('studio-filters');
    if (!form) return;

    const search = document.getElementById('studio-search');
    const state = document.getElementById('studio-state');
    const results = document.getElementById('studio-results');
    const cards = Array.from(results.querySelectorAll('.studio-card'));
    const count = document.getElementById('studio-count');
    const empty = document.getElementById('studio-empty');
    const popular = document.getElementById('studio-popular');
    const presenceStates = document.getElementById('studio-presence-states');
    const viewAll = document.getElementById('studio-view-all');
    const directory = document.getElementById('studio-directory');

    function showDirectory() {
        directory?.scrollIntoView?.({ behavior: 'smooth', block: 'start' });
    }

    function render() {
        const term = search.value.trim().toLocaleLowerCase();
        const matches = cards.filter((card) =>
            (!term || card.dataset.search.toLocaleLowerCase().includes(term)) &&
            (!state.value || card.dataset.state === state.value)
        );
        const visible = new Set(matches);

        cards.forEach((card) => { card.hidden = !visible.has(card); });
        matches.forEach((card, index) => {
            card.querySelector('.studio-card__index').textContent = String(index + 1).padStart(2, '0');
        });
        count.textContent = `${matches.length} ${matches.length === 1 ? 'location' : 'locations'} found`;
        results.hidden = matches.length === 0;
        empty.hidden = matches.length !== 0;
    }

    form.addEventListener('submit', (event) => { event.preventDefault(); render(); });
    search.addEventListener('input', render);
    state.addEventListener('change', render);
    popular?.addEventListener('click', (event) => {
        const chip = event.target.closest('[data-studio-search]');
        if (!chip) return;
        search.value = chip.dataset.studioSearch;
        state.value = '';
        render();
        showDirectory();
    });
    presenceStates?.addEventListener('click', (event) => {
        const choice = event.target.closest('[data-studio-state]');
        if (!choice) return;
        search.value = '';
        state.value = choice.dataset.studioState;
        render();
        showDirectory();
    });
    viewAll?.addEventListener('click', () => {
        search.value = '';
        state.value = '';
        render();
        showDirectory();
    });
    render();
})();
