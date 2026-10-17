/* Scroll-driven Studio Tour. Each AVIF contains two consecutive film frames. */
(() => {
  const page = document.querySelector('.tour-page');
  if (!page) return;

  const byId = (id) => document.getElementById(id);
  const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
  const lerp = (start, end, amount) => start + (end - start) * amount;
  const ease = (amount) => 1 - Math.pow(1 - amount, 3);
  const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const compact = matchMedia('(pointer:coarse)').matches || matchMedia('(max-width:900px)').matches;
  const frameCount = 660;
  const perFile = 2;
  const fileCount = frameCount / perFile;
  const base = page.dataset.framesBase;
  const source = (index) => `${base}${String(index + 1).padStart(3, '0')}.avif`;

  const tour = byId('tour');
  const canvas = byId('tour-canvas');
  const context = canvas.getContext('2d');
  if (!context) return;

  const hero = byId('tour-hero');
  const hint = byId('tour-hint');
  const progress = byId('tour-progress');
  const progressBar = byId('tour-progress-bar');
  const loader = byId('tour-loader');
  const black = byId('tour-black');
  const end = byId('tour-end');
  const captions = [...page.querySelectorAll('.tour-page__caption')].map((element) => ({
    element, start: Number(element.dataset.a), finish: Number(element.dataset.b),
  }));

  [34, 95, 203, 310, 351, 392, 440, 500, 548, 596].forEach((frame) => {
    const tick = document.createElement('i');
    tick.style.left = `${(frame / (frameCount - 1) * 100).toFixed(2)}%`;
    progress.appendChild(tick);
  });

  const files = new Array(fileCount);
  const urls = new Array(fileCount);
  const cache = new Map();
  let loaded = 0;
  let lastDraw = '';
  let currentFile = 0;
  let direction = 1;

  function ensure(index) {
    if (index < 0 || index >= fileCount || !urls[index] || cache.has(index)) return;
    const image = new Image();
    image.decoding = 'async';
    image.src = urls[index];
    cache.set(index, image);
    (image.decode ? image.decode() : new Promise((resolve) => { image.onload = resolve; }))
      .then(() => { image.ok = true; lastDraw = ''; })
      .catch(() => {});
  }

  function prime(index) {
    if (index !== currentFile) {
      direction = index > currentFile ? 1 : -1;
      currentFile = index;
    }
    for (let offset = 0; offset <= 12; offset += 1) ensure(index + direction * offset);
    for (let offset = 1; offset <= 5; offset += 1) ensure(index - direction * offset);
    if (cache.size > 24) {
      for (const [key, image] of cache) {
        if (Math.abs(key - index) > 12) {
          image.src = '';
          cache.delete(key);
        }
      }
    }
  }

  function get(index) {
    const image = compact ? cache.get(index) : files[index];
    return image?.ok ? image : null;
  }

  function frame(index) {
    const target = Math.floor(index / perFile);
    if (compact) prime(target);
    for (let distance = 0; distance < fileCount; distance += 1) {
      for (const key of distance ? [target - distance, target + distance] : [target]) {
        const image = get(key);
        if (image) return [image, key === target ? index % perFile : key < target ? perFile - 1 : 0];
      }
    }
    return null;
  }

  function resize() {
    const ratio = Math.min(devicePixelRatio || 1, 2);
    const width = Math.round(canvas.clientWidth * ratio);
    const height = Math.round(canvas.clientHeight * ratio);
    if (width === canvas.width && height === canvas.height) return;
    canvas.width = width;
    canvas.height = height;
    lastDraw = '';
  }

  function blit(image, row, width, height, zoom, index) {
    const frameWidth = image.naturalWidth;
    const frameHeight = image.naturalHeight / perFile;
    const tall = width / height < .9;
    const facade = lerp(1, 1.45, ease(clamp(index / 40, 0, 1)));
    let scale = tall ? width * facade / frameWidth : Math.max(width / frameWidth, height / frameHeight);
    scale *= 1 + zoom * .14;
    const drawWidth = frameWidth * scale;
    const drawHeight = frameHeight * scale;
    const centerY = tall ? height * .47 : height / 2;
    let x = width / 2 - (tall ? .5 : lerp(.5, .5, zoom)) * drawWidth;
    let y = centerY - lerp(.5, .46, zoom) * drawHeight;
    if (!tall) {
      x = clamp(x, width - drawWidth, 0);
      y = clamp(y, height - drawHeight, 0);
    }
    context.drawImage(image, 0, row * frameHeight, frameWidth, frameHeight, x, y, drawWidth, drawHeight);
    if (tall) {
      const fade = drawHeight * .12;
      const gradient = (start, finish) => {
        const value = context.createLinearGradient(0, start, 0, finish);
        value.addColorStop(0, '#070707');
        value.addColorStop(1, 'rgba(7,7,7,0)');
        return value;
      };
      context.fillStyle = gradient(y, y + fade);
      context.fillRect(0, y - 1, width, fade + 1);
      context.fillStyle = gradient(y + drawHeight, y + drawHeight - fade);
      context.fillRect(0, y + drawHeight - fade, width, fade + 1);
    }
  }

  function draw(progressValue) {
    const width = canvas.width;
    const height = canvas.height;
    const index = Math.round(clamp(progressValue / .86, 0, 1) * (frameCount - 1));
    const zoom = ease(clamp((progressValue - .86) / (.93 - .86), 0, 1));
    const key = [width, height, index, zoom.toFixed(3), loaded].join('|');
    if (key === lastDraw) return index;
    lastDraw = key;
    const current = frame(index);
    if (!current) { lastDraw = ''; return index; }
    context.fillStyle = '#070707';
    context.fillRect(0, 0, width, height);
    blit(current[0], current[1], width, height, zoom, index);
    return index;
  }

  function updateLoader() {
    loaded += 1;
    loader.style.width = `${loaded / fileCount * 100}%`;
    if (loaded >= fileCount) loader.style.opacity = 0;
    lastDraw = '';
  }

  function loadImage(index) {
    return new Promise((resolve) => {
      const image = new Image();
      image.decoding = 'async';
      image.onload = () => { image.ok = true; files[index] = image; updateLoader(); resolve(); };
      image.onerror = () => { updateLoader(); resolve(); };
      image.src = source(index);
    });
  }

  function loadBlob(index) {
    return fetch(source(index))
      .then((response) => response.ok ? response.blob() : null)
      .then((blob) => {
        if (blob) {
          urls[index] = URL.createObjectURL(blob);
          if (Math.abs(index - currentFile) <= 12) ensure(index);
        }
        updateLoader();
      })
      .catch(updateLoader);
  }

  const pending = new Set(Array.from({ length: fileCount }, (_, index) => index));
  async function preload(loaderFunction) {
    while (pending.size) {
      const distance = (index) => index >= currentFile ? index - currentFile : (currentFile - index) * 3;
      const nearby = [...pending].sort((a, b) => distance(a) - distance(b)).slice(0, 6);
      nearby.forEach((index) => pending.delete(index));
      await Promise.all(nearby.map(loaderFunction));
    }
  }
  preload(compact ? loadBlob : loadImage);

  function show(element, opacity, transform) {
    element.style.opacity = opacity;
    element.style.visibility = opacity <= .001 ? 'hidden' : 'visible';
    element.style.transform = transform;
  }

  let smoothProgress = 0;
  function render() {
    const bounds = tour.getBoundingClientRect();
    const range = Math.max(bounds.height - innerHeight, 1);
    const progressValue = clamp(-bounds.top / range, 0, 1);
    smoothProgress += reducedMotion ? progressValue - smoothProgress : (progressValue - smoothProgress) * .12;
    if (Math.abs(progressValue - smoothProgress) < .0001) smoothProgress = progressValue;
    const index = draw(smoothProgress);
    if (!compact) currentFile = Math.floor(index / perFile);
    progressBar.style.transform = `scaleX(${clamp(smoothProgress / .86, 0, 1)})`;
    const heroFade = ease(clamp(smoothProgress / .02, 0, 1));
    show(hero, 1 - heroFade, `translateY(${-heroFade * 40}px)`);
    hint.style.opacity = clamp(1 - smoothProgress / .015, 0, 1);
    captions.forEach(({ element, start, finish }) => {
      const span = finish - start;
      const fadeIn = clamp((index - start) / (span * .22), 0, 1);
      const fadeOut = clamp((finish - index) / (span * .22), 0, 1);
      const opacity = index >= start && index <= finish ? Math.min(fadeIn, fadeOut) : 0;
      show(element, opacity, `translateY(${(1 - fadeIn) * 28 - (1 - fadeOut) * 18}px)`);
    });
    const blackFade = clamp((smoothProgress - .915) / (.955 - .915), 0, 1);
    black.style.opacity = blackFade;
    progress.style.opacity = 1 - blackFade;
    const endFade = ease(clamp((smoothProgress - .945) / (.985 - .945), 0, 1));
    show(end, endFade, `translateY(${(1 - endFade) * 30}px)`);
    end.style.pointerEvents = endFade > .5 ? 'auto' : 'none';
    requestAnimationFrame(render);
  }

  addEventListener('resize', resize);
  resize();
  requestAnimationFrame(render);

  if (!reducedMotion && window.Lenis) {
    const lenis = new window.Lenis({ lerp: .085 });
    const animate = (time) => { lenis.raf(time); requestAnimationFrame(animate); };
    requestAnimationFrame(animate);
    addEventListener('dd:menu-toggle', (event) => { event.detail.open ? lenis.stop() : lenis.start(); });
    page.querySelectorAll('a[href^="#"]').forEach((link) => link.addEventListener('click', (event) => {
      const target = document.querySelector(link.getAttribute('href'));
      if (!target) return;
      event.preventDefault();
      lenis.scrollTo(target, { duration: link.id === 'tour-replay' ? 2 : 1.4 });
    }));
  }
})();
