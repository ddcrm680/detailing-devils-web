/** Main website menu — shared across all app-layout pages. */
(() => {
  const hdr = document.getElementById('hdr');
  const burger = document.getElementById('burger');
  const menu = document.getElementById('menu');

  if (!hdr || !burger || !menu) return;

  const setMenu = (open) => {
    document.body.classList.toggle('menu-open', open);
    burger.setAttribute('aria-expanded', String(open));
    menu.setAttribute('aria-hidden', String(!open));
    window.dispatchEvent(new CustomEvent('dd:menu-toggle', { detail: { open } }));
  };

  burger.addEventListener('click', () => {
    setMenu(!document.body.classList.contains('menu-open'));
  });

  menu.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => setMenu(false));
  });

  window.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') setMenu(false);
  });

  window.addEventListener('scroll', () => {
    hdr.classList.toggle('solid', scrollY > innerHeight * .5);
  }, { passive: true });
})();
