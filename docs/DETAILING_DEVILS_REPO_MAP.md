# Detailing Devils — current source repository map

**Snapshot reviewed:** `devils-new-web.zip` (uploaded 2026-10-08). This is a read-only code analysis. The original ZIP has not been changed.

## 1. Project at a glance

- **Framework:** Laravel 13 (`composer.json`: PHP `^8.3`, Laravel `^13.17`)
- **Frontend:** one large Blade landing page, containing most HTML, CSS, and browser-side JavaScript inline
- **Animation:** GSAP 3.12.5 + ScrollTrigger, Lenis 1.1.13, Canvas scroll-driven sequence
- **Database:** default SQLite (`database/database.sqlite`), configurable through Laravel environment
- **Build tooling:** Vite 8 and Tailwind CSS 4 are configured but the main landing page uses inline CSS/JS and externally hosted scripts rather than Vite entrypoints
- **Backend functionality:** enquiry submission, database storage, email attempt, protected enquiry listing, token-protected deploy/migration endpoint
- **Archive composition:** 370 ZIP entries; 240 JPEG animation frames, 17 files under `public/img`

## 2. Directory map and responsibilities

```text
/                                Laravel project root (inside ZIP)
├── app/
│   ├── Http/Controllers/
│   │   ├── HomeController.php      renders home with config('site')
│   │   ├── EnquiryController.php   validates/saves enquiries; sends alert; admin listing
│   │   └── DeployController.php    token-checks and runs migrations
│   ├── Http/Middleware/
│   │   └── AdminBasicAuth.php      HTTP Basic authentication for admin enquiries
│   └── Models/
│       ├── Enquiry.php
│       └── User.php
├── bootstrap/
│   └── app.php                    configures routing and loads deploy.php
├── config/
│   └── site.php                   central editable brand, contact, hero, statistics,
│                                  service entries, franchise steps, footer text
├── database/
│   ├── database.sqlite            included SQLite database (contains one enquiry)
│   └── migrations/
│       └── 2026_10_01_000000_create_enquiries_table.php
├── public/
│   ├── index.php                  Laravel web entrypoint
│   ├── img/                       17 logo/photos/service graphics
│   ├── frames/                    ezgif-frame-001.jpg through -240.jpg
│   ├── robots.txt
│   └── favicon.ico                zero-byte file
├── resources/
│   ├── views/
│   │   ├── home.blade.php         681-line single-page site + inline CSS/JS
│   │   └── admin/enquiries.blade.php
│   ├── css/app.css                minimal Tailwind file, not referenced by home view
│   └── js/app.js                  placeholder file, not referenced by home view
├── routes/
│   ├── web.php                    /, POST /enquiry, GET /admin/enquiries
│   └── deploy.php                 GET /deploy
├── storage/
│   ├── framework/views/           generated cached Blade views included
│   └── logs/laravel.log           log included in snapshot
├── tests/Feature/ExampleTest.php  example GET / returns 200 test
├── tests/Unit/ExampleTest.php     stock example unit test
├── composer.json                  backend dependencies and scripts
├── package.json                   Vite/Tailwind dependencies and build scripts
├── vite.config.js
├── .env.example                   environment template only
└── README.md                      setup, customization, hosting instructions
```

This archive has **no `.git` directory**, no `vendor` directory, no `node_modules` directory, no compiled `public/build`, and no `package-lock.json`; it is a source snapshot, not a complete preinstalled environment.

## 3. Exact runtime routes and data flow

| Endpoint | Handler | Behavior |
|---|---|---|
| `GET /` | `HomeController::__invoke` | Renders `resources/views/home.blade.php` using `config/site.php` |
| `POST /enquiry` | `EnquiryController::store` | Requires name/phone; optional city/car/interest; throttled to 10/min by IP; persists enquiry; attempts email; responds with JSON or redirect |
| `GET /admin/enquiries` | `EnquiryController::index` and `AdminBasicAuth` | Basic-auth protected enquiry table, newest first, 50 per page |
| `GET /deploy?token=...` | `DeployController::__invoke` | Checks DEPLOY_TOKEN from env and invokes `artisan migrate --force` |
| `GET /up` | Framework health route configured in bootstrap | Laravel health endpoint |

Form data: `resources/views/home.blade.php` `#form` -> JS fetch `POST /enquiry` with CSRF -> `EnquiryController` validation -> `Enquiry` model -> `enquiries` database table -> `Mail::raw` to `config('site.email')` -> response message. Email delivery requires SMTP settings; example environment uses `MAIL_MAILER=log`.

## 4. Frontend landing-page map (all inside `resources/views/home.blade.php`)

| Site feature | Primary source |
|---|---|
| Navigation, full-screen menu, footer | `resources/views/home.blade.php` |
| Hero heading/scroll sequence settings | `config/site.php` keys `hero.*` |
| Scroll-driven hero image frames and canvas | `public/frames/*` and inline JS in `home.blade.php` |
| Approach (Inspect / Correct / Protect) | Inline content/markup in `home.blade.php` |
| Numbers/statistics | `config/site.php` key `numbers` |
| Six services (titles, descriptions, pictures) | `config/site.php` key `services` + `public/img/svc-*.webp` |
| Work section / cars animation | `home.blade.php` + `public/img/top-cars.webp` |
| Recent Work / Find a Studio cards | `home.blade.php` + `public/img/recent-work.webp`, `find-studio.webp` |
| Five-car lineup visual | `home.blade.php` + `public/img/five-cars.webp` |
| Franchise steps | `config/site.php` key `franchise_steps` |
| Enquiry form markup/JavaScript | `home.blade.php`, server logic in `EnquiryController.php` |
| Theme colors and typography | `:root` CSS variables near start of `home.blade.php` |
| Smooth scrolling and page animations | final inline script in `home.blade.php`, GSAP/Lenis CDN URLs |

The homepage uses client-side JS to render the service accordions and visuals from the PHP config-sourced `window.DD_SERVICES` array.

## 5. Checks performed (static, not live integration tests)

- Opened and extracted ZIP safely; original untouched.
- PHP lint: **35** PHP files in application/config/routes/database/tests: **0 syntax errors**.
- JavaScript syntax check of the main `@verbatim` inline script: **passed** using Node.
- Verified frames `001`–`240` are all present, no missing indices; example frames 1920 × 1080.
- Verified 17 referenced image paths exist in `public/img`.
- Verified the SQLite file contains an `enquiries` table with **one existing row** (no personal data examined or reproduced).
- Did **not** run Laravel PHPUnit feature/integration tests because `vendor` is absent and Composer is not installed in this environment. Did not test the page in a real browser.

## 6. Issues and design considerations before Codex work

1. **Protect included data:** the source ZIP contains a populated `database/database.sqlite` (one enquiry) and `storage/logs/laravel.log`. Don't publish or commit production database/logs. Treat the database as potentially containing personal information.
2. **Deployment endpoint:** `GET /deploy` executes migrations using a token in the URL query. Query tokens may appear in logs, browser history, and intermediaries. Prefer secured deployment over an externally invokable GET route; check that the token is nonempty and kept secret.
3. **Environment config usage:** admin and deploy credentials are read via `env()` at request time. This can be unreliable after Laravel configuration caching; prefer explicit configuration keys and `config()` reads.
4. **Basic Auth strength:** change `ADMIN_USER` and `ADMIN_PASSWORD` from example values; use HTTPS, secure credential storage and ideally standard authenticated admin access if expanding the admin area.
5. **Performance:** desktop code preloads/holds all 240 1920x1080 frames in `imgs`; test actual memory/network demand on representative devices. Mobile code uses a restricted decoded-image cache, but holds blob URLs for each fetched frame throughout the session.
6. **Maintainability:** landing-page HTML, CSS and JS coexist in one 681-line Blade file. For substantial changes, factor out components/styles/scripts carefully to preserve the existing choreography.
7. **Incomplete destinations:** the Recent Work and Find a Studio cards point to `#contact` instead of distinct pages; social links in `config/site.php` default to `#`; Privacy/Terms/Cookies are not links. Treat them as unimplemented destinations, not confirmed full features.
8. **Backend testing coverage:** only stock example tests exist for home success and generic unit behavior; add form validation, persistence, mail, authentication and route tests when changing backend behavior.
9. **Build pathway:** Vite/Tailwind are configured but the landing page doesn't reference `@vite`. Do not assume editing `resources/css/app.css` or `resources/js/app.js` will change the rendered homepage unless the view is updated to use them.
10. **Deployment readiness:** source snapshot lacks installed PHP/npm dependencies and production compiled assets, which is normal for a source archive. A valid build/deploy process is required before running live.

## 7. Working agreement for future Codex tasks

- Treat this uploaded ZIP as the unchanged baseline; the extracted working copy used for this audit was placed in `/mnt/data/devils-new-web-source`.
- For visual/content changes, start at `resources/views/home.blade.php` and `config/site.php`.
- For enquiries/admin, start at `routes/web.php`, `app/Http/Controllers/EnquiryController.php`, `app/Http/Middleware/AdminBasicAuth.php`, `resources/views/admin/enquiries.blade.php`, and `database/migrations/`.
- For hero animation, inspect inline JS plus `public/frames/` before adjusting timing or frame count.
- Make changes in small reviewable sets; verify PHP/JS syntax, frontend assets, CSRF, routes, and mobile behavior after each change.
- Don't change routes, auth, database schema, contact settings, current visuals or animations without an explicit requirement or a justified compatibility fix.
