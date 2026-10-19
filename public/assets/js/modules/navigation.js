/** Main website menu — shared across all app-layout pages. */
(() => {
  const hdr = document.getElementById('hdr');
  const burger = document.getElementById('burger');
  const menu = document.getElementById('menu');

  if (!hdr || !burger || !menu) return;

  let isOpen = false;
  const backgroundState = new Map();

  const setMenu = (open) => {
    if (open === isOpen) return;
    isOpen = open;

    if (open) {
      menu.inert = false;
      menu.querySelector('nav').scrollTop = 0;
      Array.from(document.body.children).forEach((element) => {
        if (element === hdr || element === menu || element.tagName === 'SCRIPT') return;
        backgroundState.set(element, element.inert);
        element.inert = true;
      });
    } else {
      backgroundState.forEach((inert, element) => { element.inert = inert; });
      backgroundState.clear();
      burger.focus({ preventScroll: true });
      menu.inert = true;
    }

    document.documentElement.classList.toggle('menu-open', open);
    document.body.classList.toggle('menu-open', open);
    burger.setAttribute('aria-expanded', String(open));
    menu.setAttribute('aria-hidden', String(!open));
    if (open) menu.querySelector('a').focus({ preventScroll: true });
    window.dispatchEvent(new CustomEvent('dd:menu-toggle', { detail: { open } }));
  };

  burger.addEventListener('click', () => {
    setMenu(!document.body.classList.contains('menu-open'));
  });

  [...hdr.querySelectorAll('a'), ...menu.querySelectorAll('a')].forEach((link) => {
    link.addEventListener('click', () => setMenu(false));
  });

  window.addEventListener('keydown', (event) => {
    if (!isOpen) return;
    if (event.key === 'Escape') setMenu(false);
    if (event.key !== 'Tab') return;

    const focusable = [...hdr.querySelectorAll('a[href], button:not([disabled])'), ...menu.querySelectorAll('a[href]')]
      .filter((element) => element.getClientRects().length > 0);
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  window.addEventListener('scroll', () => {
    hdr.classList.toggle('solid', scrollY > innerHeight * .5);
  }, { passive: true });
})();
