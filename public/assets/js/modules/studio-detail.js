(() => {
    'use strict';

    function mountStudioDetail(root, win = window) {
        if (!root) return;
        const reducedMotion = win.matchMedia('(prefers-reduced-motion: reduce)');
        root.querySelectorAll('[data-service-image]').forEach((image) => {
            const fallback = () => {
                if (image.dataset.failed) return;
                image.dataset.failed = 'true';
                image.src = image.dataset.fallback;
                image.alt = 'Detailing Devils';
                image.parentElement.classList.add('service-image--fallback');
            };
            image.addEventListener('error', fallback);
            if (image.complete && image.naturalWidth === 0) fallback();
        });
        const services = root.querySelector('[data-services-slider]');
        if (services && win.Glide) {
            const slides = [...services.querySelectorAll('.glide__slide')];
            const navigation = services.querySelector('[data-services-navigation]');
            if (slides.length > 1) {
                const previous = services.querySelector('[data-services-previous]');
                const next = services.querySelector('[data-services-next]');
                const tablet = win.matchMedia('(max-width: 1024px)');
                const mobile = win.matchMedia('(max-width: 600px)');
                const layout = () => ({ perView: mobile.matches ? 1 : tablet.matches ? 2 : 4, gap: mobile.matches ? 14 : 20 });
                const slider = new win.Glide(services, {
                    type: 'slider', ...layout(), bound: true, rewind: false,
                    keyboard: false, autoplay: false, animationDuration: reducedMotion.matches ? 0 : 380,
                });
                const sync = () => {
                    const visible = Number(slider.settings.perView);
                    const last = Math.max(0, slides.length - visible);
                    navigation.hidden = slides.length <= visible;
                    previous.disabled = slider.index === 0;
                    next.disabled = slider.index >= last;
                    slides.forEach((slide, index) => {
                        slide.setAttribute('aria-hidden', String(index < slider.index || index >= slider.index + visible));
                    });
                };
                slider.on(['mount.after', 'run.after', 'resize', 'update'], sync);
                previous.addEventListener('click', () => slider.go('<'));
                next.addEventListener('click', () => slider.go('>'));
                services.addEventListener('keydown', (event) => {
                    if (!['ArrowLeft', 'ArrowRight'].includes(event.key) || navigation.hidden) return;
                    event.preventDefault();
                    slider.go(event.key === 'ArrowRight' ? '>' : '<');
                });
                reducedMotion.addEventListener('change', () => {
                    slider.update({ animationDuration: reducedMotion.matches ? 0 : 380 });
                });
                const resize = () => {
                    const settings = layout();
                    slider.update({ ...settings, startAt: Math.min(slider.index, Math.max(0, slides.length - settings.perView)) });
                };
                tablet.addEventListener('change', resize);
                mobile.addEventListener('change', resize);
                slider.mount();
            }
        }
        const data = root.querySelector('[data-studio-photos]');
        if (!data) return;
        const photos = JSON.parse(data.textContent);
        if (!photos.length) return;
        const mobileGallery = root.querySelector('[data-mobile-gallery]');
        const mobileSlides = root.querySelector('[data-mobile-slides]');
        const mobileBreakpoint = win.matchMedia('(max-width: 600px)');
        let mobileGlide = null;

        const viewer = root.querySelector('.photo-viewer');
        const viewerImage = root.querySelector('[data-viewer-image]');
        let activePhoto = 0;
        let galleryTrigger = null;
        let previousOverflow = '';
        let touchStart = null;

        function showPhoto(position) {
            if (!photos.length) return;
            activePhoto = (position + photos.length) % photos.length;
            const photo = photos[activePhoto];
            viewerImage.src = photo.url;
            viewerImage.alt = photo.alt;
            viewerImage.width = photo.width;
            viewerImage.height = photo.height;
            root.querySelector('[data-viewer-caption]').textContent = photo.alt;
            root.querySelector('[data-viewer-count]').textContent = `${activePhoto + 1} / ${photos.length}`;
        }

        function openPhoto(position) {
            if (!photos.length || viewer.open) return;
            galleryTrigger = document.activeElement;
            showPhoto(position);
            previousOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            viewer.showModal();
        }

        root.querySelectorAll('[data-open-gallery]').forEach((button) => button.addEventListener('click', () => openPhoto(0)));
        root.querySelectorAll('[data-previous], [data-next]').forEach((button) => { button.hidden = photos.length < 2; });
        root.querySelector('[data-close-viewer]').addEventListener('click', () => viewer.close());
        root.querySelector('[data-previous]').addEventListener('click', () => showPhoto(activePhoto - 1));
        root.querySelector('[data-next]').addEventListener('click', () => showPhoto(activePhoto + 1));
        viewer.addEventListener('close', () => {
            document.body.style.overflow = previousOverflow;
            galleryTrigger?.focus({ preventScroll: true });
        });
        viewer.addEventListener('click', (event) => {
            if (event.target !== viewer) return;
            const bounds = viewer.getBoundingClientRect();
            if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) viewer.close();
        });
        viewer.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                event.preventDefault();
                showPhoto(activePhoto + (event.key === 'ArrowRight' ? 1 : -1));
            }
        });
        viewerImage.addEventListener('touchstart', (event) => {
            const touch = event.changedTouches[0];
            touchStart = { x: touch.clientX, y: touch.clientY };
        }, { passive: true });
        viewerImage.addEventListener('touchend', (event) => {
            if (!touchStart) return;
            const touch = event.changedTouches[0];
            const distance = touch.clientX - touchStart.x;
            if (Math.abs(distance) > 45 && Math.abs(distance) > Math.abs(touch.clientY - touchStart.y)) showPhoto(activePhoto + (distance < 0 ? 1 : -1));
            touchStart = null;
        }, { passive: true });

        root.querySelectorAll('[data-photo-index]').forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                openPhoto(Number(link.dataset.photoIndex));
            });
        });

        if (photos.length > 1) {
            function updateGalleryControls() {
                const index = mobileGlide?.index ?? 0;
                const moveFocus = mobileSlides.contains(document.activeElement);
                root.querySelector('[data-gallery-position]').textContent = `${String(index + 1).padStart(2, '0')} / ${String(photos.length).padStart(2, '0')}`;
                root.querySelector('[data-gallery-previous]').disabled = index === 0;
                root.querySelector('[data-gallery-next]').disabled = index === photos.length - 1;
                mobileSlides.querySelectorAll('li').forEach((slide, position) => {
                    slide.querySelector('a').tabIndex = position === index ? 0 : -1;
                    slide.setAttribute('aria-hidden', String(position !== index));
                });
                if (moveFocus) mobileSlides.children[index]?.querySelector('a').focus({ preventScroll: true });
            }

            function syncMobileGallery() {
                if (!mobileBreakpoint.matches || photos.length < 2) {
                    mobileGlide?.destroy();
                    mobileGlide = null;
                    return;
                }
                if (mobileGlide || !win.Glide) return;
                mobileGlide = new win.Glide(mobileGallery, {
                    type: 'slider', perView: 1, gap: 12, rewind: false, bound: true,
                    keyboard: false, autoplay: false, animationDuration: reducedMotion.matches ? 0 : 380,
                });
                mobileGlide.on(['mount.after', 'run.after'], updateGalleryControls);
                mobileGlide.mount();
            }

            root.querySelector('[data-gallery-previous]').addEventListener('click', () => mobileGlide?.go('<'));
            root.querySelector('[data-gallery-next]').addEventListener('click', () => mobileGlide?.go('>'));
            mobileGallery.addEventListener('keydown', (event) => {
                if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
                event.preventDefault();
                mobileGlide?.go(event.key === 'ArrowRight' ? '>' : '<');
            });
            mobileBreakpoint.addEventListener('change', syncMobileGallery);
            reducedMotion.addEventListener('change', () => {
                mobileGlide?.update({ animationDuration: reducedMotion.matches ? 0 : 380 });
            });
            syncMobileGallery();

        }
    }

    if (typeof document !== 'undefined') mountStudioDetail(document.querySelector('[data-studio-detail]'));
})();
