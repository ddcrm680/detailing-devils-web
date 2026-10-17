# Detailing Devils — current source repository map

This map describes the current Laravel checkout. The original `devils-new-web.zip` is a historical baseline; inspect the checked-out source whenever a detail here may have changed.

## Project at a glance

- Laravel 13 / PHP 8.3+, Blade, plain CSS and browser JavaScript.
- The homepage uses a shared website layout, shared Blade components, a homepage view, and CSS/JS files served from `public/assets/`.
- GSAP 3.12.5, ScrollTrigger and Lenis 1.1.13 load from CDNs on the homepage. The scroll-driven canvas uses 240 JPEGs in `public/frames/`.
- Vite/Tailwind are configured in `package.json`, `vite.config.js`, `resources/css/app.css` and `resources/js/app.js`; the current homepage does not load those Vite entrypoints.
- The backend provides disabled-by-default CRM enquiry submission, optional email notification, and a token-protected deployment route.

## Directory and file ownership

| Location | Responsibility |
| --- | --- |
| `routes/web.php` | Home and shared email-validation routes; central entry point for the franchise, location, OTP, enquiry, legal, and studio route files |
| `routes/forms.php`, `routes/locations.php`, `routes/otp.php`, `routes/enquiry.php`, `routes/legal.php` | Prime and FOFO form routes, shared location GET routes, shared OTP routes, homepage enquiry route, and four named legal policy pages |
| `routes/deploy.php`, `app/Http/Controllers/DeployController.php` | Deployment-related route and handler; do not invoke during routine testing |
| `routes/studios.php`, `app/Http/Controllers/StudioController.php`, `resources/data/studios.json` | Public studio locator using the approved static studio list; no CRM query is made; also provides the presentation-only Studio Tour route |
| `app/Http/Controllers/HomeController.php` | Passes `config('site')` to the home view |
| `app/Http/Controllers/EnquiryController.php` | Disabled-by-default CRM enquiry validation, insert, and optional mail attempt |
| `app/Http/Requests/Franchise/StorePrimeRequest.php` | Prime server-side validation, CRM column-length checks, and normalized phone numbers |
| `app/Actions/Franchise/SaveFranchiseLeadAction.php` | Prime and FOFO CRM mappings plus a shared transactional save path; Prime requires OTP, while FOFO requires the submitted email's session-bound ZeroBounce proof |
| `app/Services/Franchise/PhoneNumberNormalizer.php` | Shared libphonenumber validation, E.164 normalization, and visible-number consistency checks for franchise forms |
| `app/Http/Requests/Franchise/SendOtpRequest.php`, `VerifyOtpRequest.php` | Shared OTP payload validation for the controller endpoints |
| `app/Services/Franchise/OtpService.php`, `OtpChallenge.php`, `OtpDeliveryService.php`, `SmsAlertDelivery.php`, `app/Contracts/Franchise/SmsDelivery.php` | Cache-backed OTP core and cancellation; OTP delivery builds the message before passing it to the generic SMS Alert transport; live delivery is disabled by default |
| `config/site.php` | Brand, contacts (including legal-policy support email), hero settings, statistics, services and franchise steps |
| `config/form_options.php` | Shared state options and Prime choice values, labels, and JavaScript-dependent IDs |
| `resources/views/layouts/app.blade.php` | Shared HTML document, metadata, CSS/JS order, components and main content slot |
| `resources/views/legal/` | Four source-controlled policy pages using the shared website layout |
| `resources/views/studios/index.blade.php`, `public/assets/css/studios.css`, `public/assets/js/modules/studios.js` | Shared-layout studio locator with Blade-rendered records, browser search/state filtering, 50-per-page pagination, phone and Google Maps links |
| `resources/views/studios/tour.blade.php`, `public/assets/css/studio-tour.css`, `public/assets/js/modules/studio-tour.js`, `public/frames/studio-tour/` | Shared-layout Studio Tour with scroll-driven AVIF frames and links to the existing Prime and FOFO applications |
| `resources/views/layouts/prime.blade.php`, `resources/views/forms/prime.blade.php` | Independent Prime layout and five-step application view |
| `app/Http/Controllers/Franchise/FofoController.php`, `resources/views/forms/fofo.blade.php` | FOFO page and disabled-by-default submission; new leads schedule team/applicant emails and an optional calling callback |
| `app/Http/Controllers/LocationController.php` | Shared read-only CRM country, state and city queries |
| `app/Http/Controllers/EmailValidationController.php` | Shared ZeroBounce email-deliverability endpoint; API key stays in `config/services.php` |
| `resources/views/emails/franchise/prime-team.blade.php`, `prime-applicant.blade.php`, `fofo-team.blade.php`, `fofo-applicant.blade.php` | Form-specific team notifications and applicant acknowledgements |
| `config/mail.php` | Existing Laravel mail transport/sender settings and configurable Prime and FOFO team recipients |
| `app/Http/Controllers/Franchise/` | Prime rendering/submission and shared OTP endpoints; OTP controller requires explicit provider and storage-readiness configuration |
| `resources/views/components/header.blade.php` | Shared logo, social links, phone and menu button |
| `resources/views/components/navbar.blade.php` | Full-screen section menu; links return to homepage anchors from other pages |
| `resources/views/components/footer.blade.php` | Shared contact details and footer navigation |
| `resources/views/components/notification.blade.php` | Session error notification hook |
| `resources/views/home.blade.php` | Homepage sections, enquiry form, page CSS/JS stacks and server-to-browser service/frame data |
| `public/assets/css/style.css` | Shared design tokens, header, menu, footer and common elements |
| `public/assets/css/home.css` | Homepage sections, responsive rules and page-specific reduced-motion styles |
| `public/assets/css/prime.css`, `public/assets/js/modules/prime.js` | Prime wizard styling, client-side validation, OTP modal, and AJAX requests |
| `public/assets/css/fofo.css`, `public/assets/js/modules/fofo.js` | FOFO page styling, conditional fields, manual location fallback, email blur check, client-side review and Axios submission |
| `public/assets/js/common/location.js` | Reusable Axios-backed country, state and city dropdown initializer |
| `public/assets/js/modules/navigation.js` | Shared menu open/close behavior and header scroll state |
| `public/assets/js/modules/home.js`, `public/assets/js/modules/enquiry.js` | Homepage animation/service behavior and the separate CRM enquiry-form browser handler |
| `public/assets/js/common/` | Shared DOM, Axios/CSRF, loader, notification, validation and response helpers |
| `public/assets/vendor/` | Local Axios, SweetAlert2, Bootstrap, Bootstrap Icons and Tom Select distribution assets |
| `public/img/`, `public/frames/` | Website imagery and the sequential hero frames |
| `tests/` | Existing Laravel example tests; check actual coverage before relying on it |

## Rendering and asset order

`GET /` → `HomeController::__invoke` → `home.blade.php` → `layouts.app`. The layout emits one HTML document, `<x-header>`, `<x-navbar>`, one `<main id="top">` containing `@yield('content')`, and `<x-footer>`.

The layout loads fonts, optional non-homepage Bootstrap/Bootstrap Icons/Tom Select CSS, SweetAlert2 CSS, `style.css`, then page CSS from `@stack('css')`. At the end of the body it loads local Axios and SweetAlert2, optional non-homepage Bootstrap JS, common utilities (including `location.js` after shared Axios), `<x-notification>`, `navigation.js`, then `@stack('js')`. The homepage stack adds CDN GSAP/ScrollTrigger/Lenis, `window.DD_SERVICES` and `window.DD_FRAMES`, then `home.js`. The homepage adds `home.css` through the CSS stack. Bootstrap and Tom Select are deliberately excluded from `/` by the layout's `request()->is('/')` condition.

`home.js` uses the hero frame URLs generated from `config/site.php` and `public/frames/`; it draws into `#cv` as scrolling advances. It creates six service buttons and images from `DD_SERVICES`, coordinates with the shared menu through `dd:menu-toggle`, and sets up Lenis/ScrollTrigger when motion is enabled. Keep the asset order and element IDs in mind when changing the layout or homepage.

## Homepage content and data

The homepage view owns the hero, introduction, approach, statement, statistics, services, work animation, studio cards, five-car lineup, franchise section and enquiry section. Shared header, menu and footer markup live in their components. Shared style rules live in `style.css`; section rules live in `home.css`.

`config/site.php` supplies the hero text/frame pattern, statistics, services, brand/contact details, social destinations and franchise steps. The franchise CTAs and other section copy are in `home.blade.php`. Images are under `public/img/`; frame files follow `ezgif-frame-001.jpg` through `ezgif-frame-240.jpg`.

The Prime button uses the named `prime.show` route, which renders the migrated five-step frontend at `/franchise/prime-form`. The homepage FOFO button uses the named `fofo.show` route; the FOFO form is available directly at `/franchise/fofo-form`, but its submit button and POST handler remain disabled until `FOFO_SUBMISSION_ENABLED=true` is explicitly configured. Shared read-only location GET endpoints use `tbl_country_phone.id` → `tbl_states.country_id` → `tbl_cities.state_id`; tests use isolated SQLite fixtures. Prime submission is disabled by default through PRIME_SUBMISSION_ENABLED. When enabled, it validates the form, consumes a purpose/mobile/session-bound OTP proof, and saves a new legacy CRM lead or returns an existing-lead result. OTP endpoints require SMS Alert credentials and both readiness flags; Prime-purpose OTP sends also return HTTP 503 while Prime submission is disabled. Only newly created Prime leads schedule two independent, best-effort emails through Laravel termination callbacks after the save commits; no queue or durable retry is used. The team recipient comes from `PRIME_FRANCHISE_TEAM_EMAIL`, and the sender uses Laravel's `MAIL_FROM_*` settings. The `log` mailer skips Prime emails so applicant details are not written to application logs.

`PrimeController::create` passes `config/form_options.php` choices to the Prime view; `StorePrimeRequest` reads the same allowed values and uses `PhoneNumberNormalizer` to verify mobile and WhatsApp inputs. Validated requests expose canonical E.164 values through `normalizedPhoneNumbers()`. `PrimeController::store` passes those values to `SaveFranchiseLeadAction`. Prime selects its record type and mandatory mobile OTP verification in backend code; its field mapping stays separate from the shared duplicate check and insert. The shared path invokes the form-defined verification callback inside the locked transaction, including for duplicate leads. The action locks by `(record_type, phone)`, but the legacy CRM has no unique constraint on that pair, so other writers that do not use the lock can still create duplicates. The current database queue uses the default database unless separately configured; do not dispatch email jobs to the CRM connection.

## Routes and enquiry flow

| Endpoint | Handler | Behavior |
| --- | --- | --- |
| `GET /` | `HomeController::__invoke` | Renders the homepage with site configuration |
| `GET /terms-of-use`, `/privacy-policy`, `/refund-policy`, `/cancellation-policy` | Named `Route::view` routes | Render the four legal policies in the shared website layout |
| `GET /locate-a-store` | `StudioController::index` | Loads the source-controlled studio list; Blade renders every entry and vanilla JavaScript filters and paginates it |
| `GET /studio-tour` | Named `Route::view` | Renders the cinematic Studio Tour without a database query or submission endpoint |
| `GET /franchise/prime-form` | `PrimeController::create` | Renders the independent Prime application view |
| `GET /franchise/fofo-form` | `FofoController::create` | Renders the FOFO form in the shared website layout; submit button is disabled by default |
| `POST /franchise/fofo` | `FofoController::store` | Throttled and disabled by default; validates the form and email proof, saves a FOFO lead, returns `success`/`created`, and schedules follow-up only for a new lead |
| `GET /locations/countries`, `/locations/states?country_id=…`, `/locations/cities?state_id=…` | `LocationController` | Shared read-only country, state and city lists from the CRM location tables |
| `POST /email/validate` | `EmailValidationController::__invoke` | Throttled ZeroBounce deliverability check; returns boolean `success` plus `result` (`valid`, `invalid` or `unavailable`), without saving an application |
| `POST /franchise/prime` | `PrimeController::store` | Returns HTTP 503 while `PRIME_SUBMISSION_ENABLED=false`; when enabled, validates and consumes Prime OTP proof and returns `success`/`created` after saving or detecting a duplicate |
| `POST /otp/send`, `POST /otp/verify` | `OtpController::send`, `OtpController::verify` | Validated, purpose-bound OTP workflow; unavailable by default until provider and storage readiness are enabled |
| `POST /enquiry` | `EnquiryController::store` | Disabled by default; validates and inserts into legacy CRM `tbl_SaveContactForm7_8` through the default connection, optionally attempts mail, and returns JSON or redirect; IP throttle is 10/min |
| `GET /deploy` | `DeployController::__invoke` | Token-protected deployment/migration action |
| `GET /up` | Laravel health route | Framework health response |

After a ZeroBounce `valid` result, the email endpoint stores `email_validation.proof` in the current Laravel session: an HMAC of the trimmed, lowercase email and an expiry ten minutes later. Each new check clears the previous proof. The FOFO save action checks the submitted email against this server-side proof and enforces the expiry; browser state is not proof. Public FOFO submission remains disabled by default.

The FOFO save entry point maps validated location IDs to CRM names and stores a conditional image filename through the `fofo_images` disk rooted at `public/uploads/images/`. Its record-type-scoped duplicate check compares four exact historical Indian phone representations (`XXXXXXXXXX`, `0XXXXXXXXXX`, `91XXXXXXXXXX`, `+91XXXXXXXXXX`); non-Indian phones retain exact normalized-digit matching. Newly created leads still store normalized digits without `+`. A newly created lead schedules separate FOFO team and applicant emails and, only if `FOFO_CALL_ENABLED=true`, one best-effort HTTPS GET to the legacy calling service using `cust_mob`; these callbacks run after the response and are not durably retried. `FOFO_CALL_URL` and `FOFO_FRANCHISE_TEAM_EMAIL` require deployment configuration. Production image-path persistence still needs confirmation before activation; the calling endpoint's legacy format supports eleven-digit Indian numbers prefixed with zero.

The homepage form posts through the shared Axios client in `enquiry.js`, which uses the CSRF meta token from `layouts.app` and shared validation/response helpers. `EnquiryController::store` inserts into the legacy CRM only when `ENQUIRY_SUBMISSION_ENABLED=true`. `ENQUIRY_EMAIL_ENABLED` independently controls the optional mail attempt. Both flags default to false. Existing local `enquiries` tables and data remain untouched; the obsolete migration file and former admin listing have been removed.

## Verification and cautions

- The shared-layout refactor was reviewed against the previous committed homepage. Local Chromium viewport checks at 1440, 768, 390 and 320 pixels confirmed section rendering, menu navigation, hero progression, service switching and no observed console/network errors. Those checks did not submit an enquiry or test a physical phone.
- Keep real enquiry data, `.env`, logs and credentials out of commits and documentation. Do not run database-writing tests, migrations or the deployment route without explicit authorization and an isolated target.
- The Recent Work card leads to the contact section. The homepage Find a Studio card and shared Studios navigation links lead to the locator. The footer links to the four legal policy pages; Cookies links to the Cookies section of Privacy rather than a separate page.
- The studio locator uses `resources/data/studios.json`, extracted from the owner-supplied HTML. Its private `tbl_studio` backups remain Git-ignored and are not used by the page.
- The deployment endpoint warrants separate security work; this cleanup does not change it.
- For shared-site changes, start with `layouts/app.blade.php`, the relevant component, `style.css` and `navigation.js`. For homepage changes, add `home.blade.php`, `home.css` and `home.js`. For enquiry changes, also inspect `routes/enquiry.php`, `EnquiryController` and `enquiry.js`.
