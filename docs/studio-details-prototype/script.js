'use strict';

// Representative data only. No CMS, database, API or form submission is connected.
const imageRoot = '../../public/img/';
const studio = {
    name: 'Detailing Devils Jaipur',
    city: 'Jaipur',
    area: 'C-Scheme, Jaipur',
    location: 'C-Scheme · Rajasthan, India',
    address: '21, Sample Road, C-Scheme, Jaipur, Rajasthan 302001',
    phones: ['+91 00000 00000', '+91 00000 00001'],
    hoursSummary: 'Mon–Sat, 10am–7pm',
    directionsUrl: 'https://www.google.com/maps/search/?api=1&query=Jaipur%2C%20Rajasthan',
    selectedServices: ['ceramic', 'ppf', 'correction', 'interior'],
    photos: [
        { file: 'studio-locator.png', title: 'The studio atmosphere', alt: 'Reference image of a Porsche inside a red-lit Detailing Devils studio', width: 1983, height: 793 },
        { file: 'studio-tour-menu.webp', title: 'A space built for detail', alt: 'Reference image of vehicles in a bright Detailing Devils workshop', width: 960, height: 540 },
        { file: 'svc-ceramic.webp', title: 'Care in every layer', alt: 'A detailer applying ceramic coating to black paint', width: 1200, height: 912 },
        { file: 'svc-ppf.webp', title: 'Protection, precisely applied', alt: 'An installer fitting paint protection film to a vehicle', width: 1200, height: 912 },
        { file: 'recent-work.webp', title: 'Made for a great arrival', alt: 'Reference image of vehicles outside a Detailing Devils studio', width: 1254, height: 1254 },
    ],
    hours: [
        { day: 'Monday', time: '10:00 am – 7:00 pm' },
        { day: 'Tuesday', time: '10:00 am – 7:00 pm' },
        { day: 'Wednesday', time: '10:00 am – 7:00 pm' },
        { day: 'Thursday', time: '10:00 am – 7:00 pm' },
        { day: 'Friday', time: '10:00 am – 7:00 pm' },
        { day: 'Saturday', time: '10:00 am – 7:00 pm' },
        { day: 'Sunday', time: 'Closed', closed: true },
    ],
};

// Shared catalogue. A future Blade view can select these by stable service ID.
// All links use the existing Services section; no new service routes are assumed.
const serviceCatalogue = {
    ceramic: { title: 'Ceramic Coating', description: 'A protective layer for a deeper gloss, easier cleaning and a beautifully maintained finish.', image: 'svc-ceramic.webp', alt: 'Ceramic coating being applied to a car' },
    ppf: { title: 'Paint Protection Film', description: 'A clear layer of defence against road wear, with a finish that lets your paint do the talking.', image: 'svc-ppf.webp', alt: 'Clear paint protection film being carefully fitted' },
    correction: { title: 'Paint Correction', description: 'Careful machine polishing to refine swirl marks, improve clarity and bring the finish back to life.', image: 'svc-correction.webp', alt: 'A detailer machine polishing a vehicle' },
    interior: { title: 'Interior Detailing', description: 'Thoughtful care for seats, carpets and cabin surfaces. A fresher feeling, from the inside out.', image: 'svc-interior.webp', alt: 'A vehicle interior being cleaned with steam' },
    window: { title: 'Window Film', description: 'Heat and UV protection options selected with visibility and comfort in mind.', image: 'svc-window.webp', alt: 'An installer applying window film' },
    wheel: { title: 'Wheel & Trim', description: 'Detailed cleaning and finishing for wheels, calipers and exterior trim.', image: 'svc-wheel.webp', alt: 'A detailer cleaning a vehicle wheel' },
};

document.querySelectorAll('[data-studio]').forEach((element) => {
    element.textContent = studio[element.dataset.studio];
});
const primaryCallUrl = `tel:${studio.phones[0].replace(/[^+0-9]/g, '')}`;
document.querySelectorAll('[data-call]').forEach((link) => { link.href = primaryCallUrl; });
document.querySelectorAll('[data-phone]').forEach((link) => { link.textContent = studio.phones[0]; });
studio.phones.forEach((phone) => {
    const link = document.createElement('a');
    link.textContent = phone;
    link.href = `tel:${phone.replace(/[^+0-9]/g, '')}`;
    document.querySelector('[data-phone-list]').append(link);
});
document.querySelectorAll('[data-directions]').forEach((link) => { link.href = studio.directionsUrl; });
document.querySelectorAll('[data-photo-count]').forEach((element) => { element.textContent = studio.photos.length; });
document.querySelector('.gallery-count').textContent = `${String(studio.photos.length).padStart(2, '0')} photos`;

const photoTemplate = document.querySelector('#photo-template');
const gallery = document.querySelector('[data-gallery]');
const mobileGallery = document.querySelector('[data-mobile-gallery]');
const mobileSlides = document.querySelector('[data-mobile-slides]');
const mobileBreakpoint = window.matchMedia('(max-width: 600px)');
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
let mobileGlide = null;

function photoCard(photo, position) {
    const card = photoTemplate.content.firstElementChild.cloneNode(true);
    const image = card.querySelector('img');
    image.src = imageRoot + photo.file;
    image.alt = photo.alt;
    image.width = photo.width;
    image.height = photo.height;
    card.querySelector('.photo-caption > span').textContent = photo.title;
    card.setAttribute('aria-label', `View photo: ${photo.title}`);
    card.addEventListener('click', () => openPhoto(position));
    return card;
}

const desktopPhotos = studio.photos.length > 1 ? studio.photos.slice(1) : studio.photos;
gallery.dataset.count = desktopPhotos.length;
desktopPhotos.forEach((photo) => gallery.append(photoCard(photo, studio.photos.indexOf(photo))));
studio.photos.forEach((photo, position) => {
    const slide = document.createElement('li');
    slide.className = 'glide__slide';
    slide.setAttribute('aria-label', `Photo ${position + 1} of ${studio.photos.length}`);
    slide.append(photoCard(photo, position));
    mobileSlides.append(slide);
});

const hasPhotos = studio.photos.length > 0;
document.querySelector('[data-gallery-empty]').hidden = hasPhotos;
gallery.hidden = !hasPhotos;
mobileGallery.hidden = !hasPhotos;
document.querySelectorAll('[data-open-gallery]').forEach((button) => { button.hidden = !hasPhotos; });
document.querySelector('.gallery-foot').hidden = !hasPhotos;
document.querySelector('[data-gallery-controls]').hidden = studio.photos.length < 2;
if (!hasPhotos) {
    document.querySelector('.hero-image').hidden = true;
    document.querySelector('.hero').classList.add('hero-without-photo');
} else {
    const cover = document.querySelector('[data-cover]');
    cover.src = imageRoot + studio.photos[0].file;
    cover.alt = studio.photos[0].alt;
}

function updateGalleryControls() {
    const index = mobileGlide?.index ?? 0;
    const moveFocus = mobileSlides.contains(document.activeElement);
    document.querySelector('[data-gallery-position]').textContent = `${String(index + 1).padStart(2, '0')} / ${String(studio.photos.length).padStart(2, '0')}`;
    document.querySelector('[data-gallery-previous]').disabled = index === 0;
    document.querySelector('[data-gallery-next]').disabled = index === studio.photos.length - 1;
    mobileSlides.querySelectorAll('li').forEach((slide, position) => {
        slide.querySelector('button').tabIndex = position === index ? 0 : -1;
        slide.setAttribute('aria-hidden', String(position !== index));
    });
    if (moveFocus) mobileSlides.children[index]?.querySelector('button').focus({ preventScroll: true });
}

function syncMobileGallery() {
    if (!mobileBreakpoint.matches || studio.photos.length < 2) {
        mobileGlide?.destroy();
        mobileGlide = null;
        return;
    }
    if (mobileGlide || !window.Glide) return;
    mobileGlide = new window.Glide(mobileGallery, {
        type: 'slider', perView: 1, gap: 12, rewind: false, bound: true,
        keyboard: false, autoplay: false, animationDuration: reducedMotion.matches ? 0 : 380,
    });
    mobileGlide.on(['mount.after', 'run.after'], updateGalleryControls);
    mobileGlide.mount();
}

document.querySelector('[data-gallery-previous]').addEventListener('click', () => mobileGlide?.go('<'));
document.querySelector('[data-gallery-next]').addEventListener('click', () => mobileGlide?.go('>'));
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

const serviceTemplate = document.querySelector('#service-template');
const services = document.querySelector('[data-services]');
studio.selectedServices.forEach((id, position) => {
    const service = serviceCatalogue[id];
    if (!service) return;
    const card = serviceTemplate.content.firstElementChild.cloneNode(true);
    const image = card.querySelector('img');
    image.src = imageRoot + service.image;
    image.alt = service.alt;
    card.querySelector('h3').textContent = service.title;
    card.querySelector('p').textContent = service.description;
    card.querySelector('.service-number').textContent = String(position + 1).padStart(2, '0');
    services.append(card);
});

const hoursTemplate = document.querySelector('#hours-template');
const hoursList = document.querySelector('[data-hours]');
studio.hours.forEach((hours) => {
    const row = hoursTemplate.content.firstElementChild.cloneNode(true);
    row.querySelector('.hours-day').textContent = hours.day;
    row.querySelector('.hours-time').textContent = hours.time;
    row.classList.toggle('is-closed', Boolean(hours.closed));
    hoursList.append(row);
});

// A small native image viewer keeps the prototype independent of libraries.
const viewer = document.querySelector('.photo-viewer');
const viewerImage = document.querySelector('[data-viewer-image]');
let activePhoto = 0;
let galleryTrigger = null;
let previousOverflow = '';
let touchStart = null;

function showPhoto(position) {
    if (!studio.photos.length) return;
    activePhoto = (position + studio.photos.length) % studio.photos.length;
    const photo = studio.photos[activePhoto];
    viewerImage.src = imageRoot + photo.file;
    viewerImage.alt = photo.alt;
    viewerImage.width = photo.width;
    viewerImage.height = photo.height;
    document.querySelector('[data-viewer-caption]').textContent = photo.title;
    document.querySelector('[data-viewer-count]').textContent = `${activePhoto + 1} / ${studio.photos.length}`;
}

function openPhoto(position) {
    if (!studio.photos.length || viewer.open) return;
    galleryTrigger = document.activeElement;
    showPhoto(position);
    previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    viewer.showModal();
}

document.querySelectorAll('[data-open-gallery]').forEach((button) => button.addEventListener('click', () => openPhoto(0)));
document.querySelectorAll('[data-previous], [data-next]').forEach((button) => { button.hidden = studio.photos.length < 2; });
document.querySelector('[data-close-viewer]').addEventListener('click', () => viewer.close());
document.querySelector('[data-previous]').addEventListener('click', () => showPhoto(activePhoto - 1));
document.querySelector('[data-next]').addEventListener('click', () => showPhoto(activePhoto + 1));
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

const menuToggle = document.querySelector('.menu-toggle');
const pageMenu = document.querySelector('#page-menu');
function closeMenu(returnFocus = false) {
    pageMenu.hidden = true;
    menuToggle.setAttribute('aria-expanded', 'false');
    if (returnFocus) menuToggle.focus();
}
menuToggle.addEventListener('click', () => {
    const open = menuToggle.getAttribute('aria-expanded') !== 'true';
    pageMenu.hidden = !open;
    menuToggle.setAttribute('aria-expanded', String(open));
});
pageMenu.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => closeMenu()));
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !pageMenu.hidden) closeMenu(true);
});
document.addEventListener('click', (event) => {
    if (!pageMenu.hidden && !event.target.closest('.site-header')) closeMenu();
});
