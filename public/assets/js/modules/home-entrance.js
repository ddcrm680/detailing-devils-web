(() => {
    const entrance = document.getElementById('home-entrance');
    if (!entrance || entrance.dataset.initialized) return;
    entrance.dataset.initialized = 'true';

    const storageKey = 'dd.home-entrance.dismissed.v1';
    try {
        if (window.localStorage.getItem(storageKey) === '1') {
            entrance.remove();
            return;
        }
    } catch {
        entrance.remove();
        return;
    }

    // Fail open if its stylesheet did not load.
    if (getComputedStyle(entrance).position !== 'fixed') return;

    const button = entrance.querySelector('button');
    const typed = entrance.querySelector('.home-entrance__typed');
    const quote = entrance.querySelector('.home-entrance__accessible-quote').textContent;
    const bar = entrance.querySelector('.home-entrance__bar b');
    const percentage = entrance.querySelector('.home-entrance__percentage');
    const loader = document.getElementById('loader');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const previousFocus = document.activeElement;
    const progressDuration = 4000;
    const started = performance.now();
    let dismissed = false;
    let progress = 0;

    function displayProgress(value) {
        progress = Math.min(100, Math.max(progress, value));
        bar.style.transform = `scaleX(${progress / 100})`;
        percentage.textContent = `${String(Math.floor(progress)).padStart(3, '0')}%`;
    }

    function preventScroll(event) {
        event.preventDefault();
    }

    function handleKey(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            button.click();
        }
        if (event.key === 'Tab') {
            event.preventDefault();
            button.focus({ preventScroll: true });
        }
    }

    function dismiss() {
        if (dismissed) return;
        dismissed = true;
        clearInterval(animation);
        try {
            window.localStorage.setItem(storageKey, '1');
        } catch {
            // Storage failure must never prevent access to the homepage.
        }
        displayProgress(100);
        entrance.classList.add('home-entrance--leaving');
        document.documentElement.classList.remove('home-entrance-active');
        entrance.removeEventListener('wheel', preventScroll);
        document.removeEventListener('keydown', handleKey);
        previousFocus?.focus({ preventScroll: true });
        setTimeout(() => entrance.remove(), reducedMotion ? 0 : 800);
    }

    const animation = setInterval(() => {
        const elapsed = performance.now() - started;
        const characters = reducedMotion ? quote.length : Math.floor(elapsed / 32);
        typed.textContent = quote.slice(0, characters);
        typed.classList.toggle('home-entrance__typed--complete', characters >= quote.length);
        const loaded = Number.parseFloat(loader?.style.width) || 0;
        const revealed = Math.min(100, characters / quote.length * 100);
        displayProgress(Math.min(revealed, Math.max(loaded, elapsed / progressDuration * 100)));
        if (characters >= quote.length && progress === 100) clearInterval(animation);
    }, 50);

    if (reducedMotion) typed.textContent = quote;
    button.addEventListener('click', dismiss, { once: true });
    entrance.addEventListener('wheel', preventScroll, { passive: false });
    document.addEventListener('keydown', handleKey);
    entrance.hidden = false;
    entrance.style.removeProperty('display');
    document.documentElement.classList.add('home-entrance-active');
    entrance.focus({ preventScroll: true });
})();
