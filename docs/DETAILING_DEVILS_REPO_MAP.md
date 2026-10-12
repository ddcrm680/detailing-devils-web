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
| `routes/web.php` | Home route and central entry point for the franchise, OTP, and enquiry route files |
| `routes/forms.php`, `routes/otp.php`, `routes/enquiry.php` | Prime form routes, shared OTP routes, and existing enquiry/admin routes |
| `routes/deploy.php`, `app/Http/Controllers/DeployController.php` | Deployment-related route and handler; do not invoke during routine testing |
| `app/Http/Controllers/HomeController.php` | Passes `config('site')` to the home view |
| `app/Http/Controllers/EnquiryController.php` | Enquiry validation, persistence, mail attempt and admin listing |
| `app/Http/Requests/Franchise/StorePrimeRequest.php` | Prime server-side validation, CRM column-length checks, and normalized phone numbers |
| `app/Actions/Franchise/SaveFranchiseLeadAction.php` | Prime-specific CRM mapping and OTP check plus a shared transactional save path for backend-selected record type, normalized phone, mapped columns and verification callback; FOFO mapping is not implemented yet |
| `app/Services/Franchise/PhoneNumberNormalizer.php` | Shared libphonenumber validation, E.164 normalization, and visible-number consistency checks for franchise forms |
| `app/Http/Requests/Franchise/SendOtpRequest.php`, `VerifyOtpRequest.php` | Shared OTP payload validation for the controller endpoints |
| `app/Services/Franchise/OtpService.php`, `OtpChallenge.php`, `OtpDeliveryService.php`, `SmsAlertDelivery.php`, `app/Contracts/Franchise/SmsDelivery.php` | Cache-backed OTP core and cancellation; OTP delivery builds the message before passing it to the generic SMS Alert transport; live delivery is disabled by default |
| `app/Http/Middleware/AdminBasicAuth.php` | Admin enquiry-list access |
| `app/Models/Enquiry.php`, `database/migrations/` | Enquiry model and schema |
| `config/site.php` | Brand, contacts, hero settings, statistics, services and franchise steps |
| `config/form_options.php` | Shared state options and Prime choice values, labels, and JavaScript-dependent IDs |
| `resources/views/layouts/app.blade.php` | Shared HTML document, metadata, CSS/JS order, components and main content slot |
| `resources/views/layouts/prime.blade.php`, `resources/views/forms/prime.blade.php` | Independent Prime layout and five-step application view |
| `resources/views/emails/franchise/prime-team.blade.php`, `prime-applicant.blade.php` | Prime team notification and applicant thank-you email content |
| `config/mail.php` | Existing Laravel mail transport/sender settings and configurable Prime team recipient |
| `app/Http/Controllers/Franchise/` | Prime rendering/submission and shared OTP endpoints; OTP controller requires explicit provider and storage-readiness configuration |
| `resources/views/components/header.blade.php` | Shared logo, social links, phone and menu button |
| `resources/views/components/navbar.blade.php` | Full-screen section menu; links return to homepage anchors from other pages |
| `resources/views/components/footer.blade.php` | Shared contact details and footer navigation |
| `resources/views/components/notification.blade.php` | Session error notification hook |
| `resources/views/home.blade.php` | Homepage sections, enquiry form, page CSS/JS stacks and server-to-browser service/frame data |
| `resources/views/admin/enquiries.blade.php` | Admin enquiry table; inspect its own layout before changing shared website assets |
| `public/assets/css/style.css` | Shared design tokens, header, menu, footer and common elements |
| `public/assets/css/home.css` | Homepage sections, responsive rules and page-specific reduced-motion styles |
| `public/assets/css/prime.css`, `public/assets/js/modules/prime.js` | Prime wizard styling, client-side validation, OTP modal, and AJAX requests |
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

`config/site.php` supplies the hero text/frame pattern, statistics, services, brand/contact details, social destinations and franchise steps. The franchise CTAs and other section copy are in `home.blade.php`. Images are under `public/img/`; frame files follow `ezgif-frame-001.jpg` through `ezgif-frame-240.jpg`.

The Prime button uses the named `prime.show` route, which renders the migrated five-step frontend at `/franchise/prime-form`. The FOFO button is temporarily `href="#"` until its Laravel form and named route are built. Prime submission validates the form, consumes a purpose/mobile/session-bound OTP proof, and saves a new legacy CRM lead or returns an existing-lead result. OTP endpoints are wired but return HTTP 503 until SMS Alert credentials and both explicit readiness flags are configured. Only newly created Prime leads schedule two independent, best-effort emails through Laravel termination callbacks after the save commits; no queue or durable retry is used. The team recipient comes from `PRIME_FRANCHISE_TEAM_EMAIL`, and the sender uses Laravel's `MAIL_FROM_*` settings. The `log` mailer skips Prime emails so applicant details are not written to application logs.

`PrimeController::create` passes `config/form_options.php` choices to the Prime view; `StorePrimeRequest` reads the same allowed values and uses `PhoneNumberNormalizer` to verify mobile and WhatsApp inputs. Validated requests expose canonical E.164 values through `normalizedPhoneNumbers()`. `PrimeController::store` passes those values to `SaveFranchiseLeadAction`. Prime selects its record type and mandatory mobile OTP verification in backend code; its field mapping stays separate from the shared duplicate check and insert. The shared path invokes the form-defined verification callback inside the locked transaction, including for duplicate leads. The action locks by `(record_type, phone)`, but the legacy CRM has no unique constraint on that pair, so other writers that do not use the lock can still create duplicates. The current database queue uses the default database unless separately configured; do not dispatch email jobs to the CRM connection.

## Routes and enquiry flow

| Endpoint | Handler | Behavior |
| --- | --- | --- |
| `GET /` | `HomeController::__invoke` | Renders the homepage with site configuration |
| `GET /franchise/prime-form` | `PrimeController::create` | Renders the independent Prime application view |
| `POST /franchise/prime` | `PrimeController::store` | Validates and consumes Prime OTP proof; returns `success: true` with `created: true` for a new CRM row or `created: false` for an existing row; generic HTTP 503 on storage failure |
| `POST /otp/send`, `POST /otp/verify` | `OtpController::send`, `OtpController::verify` | Validated, purpose-bound OTP workflow; unavailable by default until provider and storage readiness are enabled |
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
- For shared-site changes, start with `layouts/app.blade.php`, the relevant component, `style.css` and `navigation.js`. For homepage changes, add `home.blade.php`, `home.css` and `home.js`. For enquiry changes, also inspect `routes/enquiry.php`, `EnquiryController`, the model and migrations.
