# AGENTS.md — Detailing Devils website

## Project and working agreement

This repository is the existing **Detailing Devils** website, built with **Laravel 13 / PHP 8.3+**, Blade, JavaScript, CSS, and Vite/Tailwind tooling. Treat the current code and assets as the baseline. **Do not implement new features, redesign pages, or refactor unrelated code unless the user explicitly requests it.**

Read `docs/DETAILING_DEVILS_REPO_MAP.md` before making substantial changes. It is an architectural map from the initial ZIP snapshot, not a substitute for inspecting current source files. If the map and current source disagree, follow the current source and update the map when appropriate.

## Project Isolation

- Work only within the current Git repository unless explicitly instructed otherwise.
- Identify the repository root dynamically using `git rev-parse --show-toplevel`. Never hardcode local filesystem paths.
- Do not modify unrelated repositories, projects, or their running services.
- Keep environment configuration and Git operations specific to the current project.
- Preserve unrelated changes and existing functionality.
- Follow the project's existing development and testing conventions.

## Codebase navigation

| Task | Start here |
| --- | --- |
| Homepage markup, responsive styling, animations, enquiry-form browser JS | `resources/views/home.blade.php` |
| Brand text, contacts, hero settings, service details, franchise steps | `config/site.php` |
| Images and branding | `public/img/` |
| Hero animation | `public/frames/` (240 sequential JPEG frames in the initial snapshot) |
| Home route, enquiry route, admin route | `routes/web.php` |
| Homepage rendering | `app/Http/Controllers/HomeController.php` |
| Enquiry validation, persistence, mail, admin list | `app/Http/Controllers/EnquiryController.php` |
| Enquiry model and schema | `app/Models/Enquiry.php`, `database/migrations/` |
| Enquiry admin template and authentication | `resources/views/admin/enquiries.blade.php`, `app/Http/Middleware/AdminBasicAuth.php` |
| Deployment-related route and handler | `routes/deploy.php`, `app/Http/Controllers/DeployController.php` |
| Build/dependencies/tests | `composer.json`, `package.json`, `tests/` |

The homepage currently includes most of its CSS and JS **inline in `home.blade.php`**, including GSAP/ScrollTrigger and Lenis behaviors. `resources/css/app.css` and `resources/js/app.js` are not automatically used by that homepage; inspect template references before moving code to those files. Do not break the scroll-driven canvas sequence or image paths.

## How Codex should work on each request

1. Read the specific task and inspect the affected code plus nearby dependencies. Do not assume that features named in content/navigation already have dedicated routes.
2. Explain the intended changes briefly when useful and keep edits confined to the requested behavior. Prefer a small, reviewable diff over wholesale rewrites.
3. Preserve the existing visual identity, section order, copy, typography, animation choreography, mobile layout, and enquiry flow unless explicitly asked to change them.
4. Prefer existing Laravel/Blade patterns and `config/site.php` for editable site text and metadata. Escape untrusted output; preserve CSRF protection, validation, rate limiting, and authentication.
5. When updating hero assets or behavior, verify the frame count and file names against the sequence logic. Account for desktop and mobile performance.
6. Never silently change production configuration, domains, contact details, mail recipients, credentials, authentication policies, database schema, or deployment routes.
7. Add focused tests for changed behavior when practical. Never report tests as passed unless they actually ran.
8. At the end, summarize changed files, the reason for each change, verification performed, and any limitations or manual steps.

## Security and data protections

- Do **not** read, reveal, copy into documentation, commit, or publish real enquiry records or confidential information. The source snapshot contains `database/database.sqlite` and `storage/logs/laravel.log`; treat both as potentially sensitive. Use isolated test data for development.
- Never commit `.env`, API keys, SMTP secrets, admin passwords, deployment tokens, or any sensitive credentials. Preserve appropriate ignore rules.
- The snapshot contains a token-protected `GET /deploy` endpoint. Do not invoke it, broaden its access, or make production deployment changes without an explicit request. Flag security risks rather than silently modifying deployment behavior.
- Do not run migrations against production or a populated database without explicit authorization and a verified backup. Prefer isolated or in-memory test databases.
- Do not send test enquiries/emails to real users or addresses.
- Avoid destructive file/DB operations, mass dependency upgrades, or overwriting user changes without approval.

## Validation and development commands

Run applicable checks **only when the required tools and dependencies are available**:

```bash
# From the Laravel repository root
php -v
composer install           # Only if dependency installation is authorized/appropriate
php artisan test           # After vendor dependencies and test environment are ready
npm install                # Only if dependencies are needed and installation is appropriate
npm run build              # Frontend bundler check; not a substitute for checking inline JS
```

For a modified PHP file: `php -l path/to/file.php`. Consider `vendor/bin/pint --test` for PHP formatting where installed. For JavaScript inside `home.blade.php`, validate the changed script in a suitable JS environment; verify behavior in a browser when possible. For interface changes, inspect desktop and mobile layouts, interactions, console errors, and frame loading. For enquiry changes, test valid/invalid input, CSRF, persistence, mail handling, throttling, and admin authorization as applicable.

If `vendor/`, `node_modules/`, Composer, or a real browser are unavailable, state explicitly which checks could not run. **Do not claim a complete runtime test from static inspection alone.**

## Scope and deliverables

- Keep the original user-provided ZIP as a historical baseline; work in the current version-controlled checkout or a clearly separate working copy.
- Follow any more specific user instructions for a given task. When major architectural changes are requested, propose a minimal migration path and explain tradeoffs before carrying it out.
- Do not introduce new external services, frameworks, packages, or design systems without need and approval.
- Update `docs/DETAILING_DEVILS_REPO_MAP.md` if a task materially changes routes, file ownership, or data flow.
- End each task with: **What changed**, **Files touched**, **Checks run/results**, and **Risks/next steps**.
