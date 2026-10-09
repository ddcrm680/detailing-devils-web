# Detailing Devils — current source repository map

This map describes the current Laravel checkout. The original `devils-new-web.zip` is a historical baseline; inspect the checked-out source whenever a detail here may have changed.

## Project at a glance

- Laravel 13 / PHP 8.3+, Blade, plain CSS and browser JavaScript.
- The homepage uses a shared website layout, shared Blade components, a homepage view, and CSS/JS files served from `public/assets/`.
- GSAP 3.12.5, ScrollTrigger and Lenis 1.1.13 load from CDNs on the homepage. The scroll-driven canvas uses 240 JPEGs in `public/frames/`.
- Vite/Tailwind are configured in `package.json`, `vite.config.js`, `resources/css/app.css` and `resources/js/app.js`; the current homepage does not load those Vite entrypoints.
- The backend provides enquiry submission, database storage, an email attempt, a protected enquiry list, and a token-protected deployment route.

## Directory and file ownership

| Location | Responsibility |
| --- | --- |
| `routes/web.php` | Home, enquiry submission and admin enquiry routes |
| `routes/deploy.php`, `app/Http/Controllers/DeployController.php` | Deployment-related route and handler; do not invoke during routine testing |
| `app/Http/Controllers/HomeController.php` | Passes `config('site')` to the home view |
| `app/Http/Controllers/EnquiryController.php` | Enquiry validation, persistence, mail attempt and admin listing |
| `app/Http/Middleware/AdminBasicAuth.php` | Admin enquiry-list access |
| `app/Models/Enquiry.php`, `database/migrations/` | Enquiry model and schema |
| `config/site.php` | Brand, contacts, hero settings, statistics, services, franchise application links and steps |
| `resources/views/layouts/app.blade.php` | Shared HTML document, metadata, CSS/JS order, components and main content slot |
| `resources/views/layouts/prime.blade.php` | Empty placeholder; no Prime page implementation in this checkout |
| `resources/views/components/header.blade.php` | Shared logo, social links, phone and menu button |
| `resources/views/components/navbar.blade.php` | Full-screen section menu; links return to homepage anchors from other pages |
| `resources/views/components/footer.blade.php` | Shared contact details and footer navigation |
| `resources/views/components/notification.blade.php` | Session error notification hook |
| `resources/views/home.blade.php` | Homepage sections, enquiry form, page CSS/JS stacks and server-to-browser service/frame data |
| `resources/views/admin/enquiries.blade.php` | Admin enquiry table; inspect its own layout before changing shared website assets |
| `public/assets/css/style.css` | Shared design tokens, header, menu, footer and common elements |
| `public/assets/css/home.css` | Homepage sections, responsive rules and page-specific reduced-motion styles |
| `public/assets/js/modules/navigation.js` | Shared menu open/close behavior and header scroll state |
| `public/assets/js/modules/home.js` | Hero frames/canvas, service switching, enquiry-form browser handler, Lenis and GSAP animations |
| `public/assets/js/common/` | Shared DOM, Axios/CSRF, loader, notification, validation and response helpers |
| `public/assets/vendor/` | Local Axios, SweetAlert2, Bootstrap, Bootstrap Icons and Tom Select distribution assets |
| `public/img/`, `public/frames/` | Website imagery and the sequential hero frames |
| `tests/` | Existing Laravel example tests; check actual coverage before relying on it |

## Rendering and asset order

`GET /` → `HomeController::__invoke` → `home.blade.php` → `layouts.app`. The layout emits one HTML document, `<x-header>`, `<x-navbar>`, one `<main id="top">` containing `@yield('content')`, and `<x-footer>`.

The layout loads fonts, optional non-homepage Bootstrap/Bootstrap Icons/Tom Select CSS, SweetAlert2 CSS, `style.css`, then page CSS from `@stack('css')`. At the end of the body it loads local Axios and SweetAlert2, optional non-homepage Bootstrap JS, common utilities, `<x-notification>`, `navigation.js`, then `@stack('js')`. The homepage stack adds CDN GSAP/ScrollTrigger/Lenis, `window.DD_SERVICES` and `window.DD_FRAMES`, then `home.js`. The homepage adds `home.css` through the CSS stack. Bootstrap and Tom Select are deliberately excluded from `/` by the layout's `request()->is('/')` condition.

`home.js` uses the hero frame URLs generated from `config/site.php` and `public/frames/`; it draws into `#cv` as scrolling advances. It creates six service buttons and images from `DD_SERVICES`, coordinates with the shared menu through `dd:menu-toggle`, and sets up Lenis/ScrollTrigger when motion is enabled. Keep the asset order and element IDs in mind when changing the layout or homepage.

## Homepage content and data

The homepage view owns the hero, introduction, approach, statement, statistics, services, work animation, studio cards, five-car lineup, franchise section and enquiry section. Shared header, menu and footer markup live in their components. Shared style rules live in `style.css`; section rules live in `home.css`.

`config/site.php` supplies the hero text/frame pattern, statistics, services, brand/contact details, social destinations, franchise application URLs and franchise steps. Other section copy remains in `home.blade.php`. Images are under `public/img/`; frame files follow `ezgif-frame-001.jpg` through `ezgif-frame-240.jpg`.

The Prime and FOFO franchise buttons link to the existing legacy application paths. A future launch on the same domain must preserve those paths and their dependent assets, APIs, sessions, OTP and submission handlers through deployment routing; this repository does not implement the legacy forms.

## Routes and enquiry flow

| Endpoint | Handler | Behavior |
| --- | --- | --- |
| `GET /` | `HomeController::__invoke` | Renders the homepage with site configuration |
| `POST /enquiry` | `EnquiryController::store` | Validates, stores, attempts mail, returns JSON or redirect; IP throttle is 10/min |
| `GET /admin/enquiries` | `EnquiryController::index` with `AdminBasicAuth` | Protected, paginated enquiry listing |
| `GET /deploy` | `DeployController::__invoke` | Token-protected deployment/migration action |
| `GET /up` | Laravel health route | Framework health response |

The homepage form posts through the native `fetch` handler in `home.js` with the CSRF meta token from `layouts.app`. The server validates and stores through `EnquiryController` and `Enquiry`, attempts mail, then returns a response message. The shared Axios configuration also reads the CSRF meta token, but the homepage enquiry handler uses `fetch`.

## Verification and cautions

- The shared-layout refactor was reviewed against the previous committed homepage. Local Chromium viewport checks at 1440, 768, 390 and 320 pixels confirmed section rendering, menu navigation, hero progression, service switching and no observed console/network errors. Those checks did not submit an enquiry or test a physical phone.
- Keep real enquiry data, `.env`, logs and credentials out of commits and documentation. Do not run database-writing tests, migrations or the deployment route without explicit authorization and an isolated target.
- Social destinations in `config/site.php` are currently `#`; Recent Work and Find a Studio cards lead to the contact section; Privacy/Terms/Cookies are display text. These are not dedicated pages.
- The deployment endpoint and admin credential handling warrant separate security work. Neither is changed by the frontend layout refactor.
- For shared-site changes, start with `layouts/app.blade.php`, the relevant component, `style.css` and `navigation.js`. For homepage changes, add `home.blade.php`, `home.css` and `home.js`. For enquiry changes, also inspect `routes/web.php`, `EnquiryController`, the model and migrations.
