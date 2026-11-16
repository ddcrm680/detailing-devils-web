# Studio URL ownership and redirects

Implementation and development verification are complete; Apache rollout requires separate owner approval. The owner-authorized connected development migration and backfill were completed on 16 November 2026; see the verification records below. Subsequent URL tests changed only explicitly authorized temporary Studios, which were deleted after testing. No original Studio URL or Apache/production file was changed during development verification.

## Architecture and permissions

`content_urls` reserves complete relative paths with a database unique index. Each immutable reservation stores `content_type`, immutable `content_id`, `path`, `created_by` and `created_at`. Current paths and historical paths occupy the same namespace. Current destinations come from the owning content record, not a mutable redirect target. Studio IDs remain the SEO identities (`studio.{id}`).

`ContentUrls::reserve()` is reusable; only Studios are integrated. A future Blog/Services module must explicitly supply its stable type/ID, normalized named-route path, transactional save integration, active-content resolver and tests. Static-page integration likewise needs an explicitly assigned immutable numeric owner ID and a named-route path; existing static routes are not claimed by this release. This release adds no catch-all redirect route or other CMS module.

`studios.url_locked_at` records first activation and remains set after deactivation. It is not mass assignable. New drafts keep optional manual slug editing. Published/previously active Studios use a separate Change URL form. **Both Admin and Super Admin retain `manage-studios`; no new gate or role restriction exists.** SEO Admin remains excluded by the existing gate.

`Studio::save()` wraps the model write and reservation in a transaction. The model still generates absent slugs using `Str::slug()` and suffixes; generation considers both current slugs and historical reservations. Ordinary writes cannot change protected slugs. `StudioUrls::change()` validates the expected current slug and reserves old/new paths before updating only the slug and modification time. A stale or duplicate submission fails without partial changes. The existing Studio slug unique index remains in place.

All Studio URL writes take the existing SEO publication-row lock first, then the Studio row, then path reservations. This matches the publisher's lock order and serializes URL allocation with SEO drafts/publications. Database uniqueness is the final collision guard. Gallery operations and their file handling are unchanged.

## Decisions and SEO safeguards

- A → B → C: A and B return one HTTP 301 directly to C, using the trusted SEO origin and named Studio route. Responses use `no-store` to avoid caching intermediate destinations or stale redirects to deactivated content. Existing browser/search-engine caches from outside this implementation cannot be recalled.
- Reusing any historical path is rejected, including the same Studio reverting to its own earlier slug. Reservations are retained permanently.
- Draft/inactive, missing-owner, unknown and malformed Studio paths return 404. Reactivation restores redirects to the current active URL. No Admin Studio deletion endpoint is added. If a Studio is removed elsewhere, its reservations survive and resolve to 404.
- Studio name, phones, gallery associations and existing metadata/revision rows are not changed by a URL-only operation.
- Existing published self-canonicals already follow the provider's current slug. Custom titles/descriptions and stable SEO history are preserved. Sitemap inventory continues to include only current, active, eligible URLs.
- A pending SEO draft for the Studio, an explicit published canonical on the Studio, or another page's current/draft canonical targeting the old Studio URL blocks the change. The response asks the Admin to review the relevant existing SEO decision. Nothing is silently rewritten or discarded. Only the latest revision per page is treated as a pending draft, matching existing SEO Admin behavior.
- The unchanged SEO publisher revalidates stale canonical choices after a URL change. Historical revisions are retained; an old self-canonical cannot silently be republished as a valid current canonical.
- Ordinary Studio edits and URL edits are separate native forms. Existing snapshots protect unsaved Studio edits; pending URL input also triggers navigation protection. SweetAlert shows the current and proposed URL. Confirmation cancellation and validation failure retain the proposed value; Cancel explicitly clears it. Existing shared submit feedback prevents duplicate native submissions.

## Files

New runtime files:

- `database/migrations/2026_11_16_000000_create_content_urls.php`
- `app/Models/ContentUrl.php`
- `app/Services/ContentUrls.php`
- `app/Services/StudioUrls.php`
- `app/Http/Requests/Admin/ChangeStudioUrlRequest.php`
- `app/Console/Commands/ReserveStudioUrlsCommand.php`

Modified runtime files:

- `app/Models/Studio.php`
- `app/Http/Requests/Admin/SaveStudioRequest.php`
- `app/Http/Controllers/Admin/StudioController.php`
- `app/Http/Controllers/StudioController.php`
- `routes/admin.php` — adds POST `admin.studios.url.update` inside the unchanged management authorization group.
- `resources/views/admin/studios/edit.blade.php`
- `public/assets/css/admin-studios.css`
- `public/assets/js/admin/studios.js`

Tests: new `tests/Feature/StudioUrlTest.php`; updated `StudioCmsTest.php`, `StudioPagesTest.php`, `StudioLocatorTest.php`, `SeoMigrationSafetyTest.php` and `tests/js/studio-cms.test.js`. Documentation: this file and `DETAILING_DEVILS_REPO_MAP.md`. Public Blade/CSS/JS, `routes/studios.php`, SEO UI, migrations for existing modules and gallery storage are unchanged.

## Future production rollout — NOT executed

1. Obtain separate release/database approval. Verify a recoverable database backup and record the migration ledger, all Studio IDs/slugs/statuses/timestamps and relevant SEO state. Rehearse on a disposable **MySQL staging copy**, including concurrent requests, uniqueness and rollback. SQLite tests do not demonstrate MySQL lock scheduling or hosting DDL behavior.
2. Freeze Studio/SEO writes during installation. Check all existing slugs for lowercase URL-safe formatting and uniqueness, the new table/column for absence, InnoDB/unique-index support and the SEO publication state's integrity. Stop on any mismatch; never rename existing slugs automatically. Currently approved Studios are all active. If the deployment inventory instead contains inactive/draft Studios with prior publication, confirm their publication history and protection explicitly before enabling edits; the backfill cannot infer unrecorded past publication.
3. Upload the additive migration and reviewed runtime files during the maintenance window. Run **only**:

   ```sh
   php artisan migrate --path=database/migrations/2026_11_16_000000_create_content_urls.php --force
   ```

   This creates one registry and one nullable marker; it does not backfill or rewrite Studio data. MySQL DDL may commit independently. If installation is partial, inspect it and recover deliberately; rerunning refuses an existing target rather than adopting/overwriting it.
4. Explicitly provision current ownership:

   ```sh
   php artisan studios:reserve-urls --check
   php artisan studios:reserve-urls
   php artisan studios:reserve-urls --check
   ```

   The first check is read-only and normally exits nonzero until coverage exists. The backfill locks the existing records and reserves every current URL across all statuses in one transaction; only current active Studios receive the protection marker. It does not change IDs, slugs, statuses, phones, gallery links, created_at or updated_at. A conflicting owner rolls back the whole batch. Repeated execution is idempotent; no historical paths are invented. Do not invoke blanket migrations, retired imports or `/deploy`.
5. Compare the before/after Studio inventory excluding the newly populated protection marker; expect zero other differences. Confirm every existing path has the correct owner and every active Studio is protected. Confirm all original public URLs still load and the sitemap URL set is unchanged before any intentional URL edit.
6. Refresh compiled views if needed. Respect the existing intentionally uncached route workflow: do not enable route caching. If a compiled route cache unexpectedly exists, have the authorized operator remove/clear it through the actual APP_ROOT CLI workflow and verify `route:list --path=admin/studios` includes the new POST route. No HTTP maintenance endpoints. Existing asset versioning uses file timestamps; verify fresh JS/CSS responses.
7. Re-enable writes only after schema/backfill checks and authorized-role/read-only public smoke tests pass. Never use an approved Studio as a disposable URL-change test. No Composer install, frontend build, legacy-table changes or media replacement is required.

## Rollback

Do not run an automatic migration rollback: `down()` deliberately refuses to discard URL ownership/history. Preserve the registry and protection marker, even if application files are rolled back. Prefer restoring only UI assets while retaining backend reservation/redirect safeguards, or forward-fixing the release.

If a full application rollback is essential, freeze Studio URL writes first; older code can change slugs without maintaining the registry and cannot serve the new redirects. Keep the new redirect reader/URL write protection deployed until a reviewed replacement is available. After any public URL change, do not restore old Studio slugs or restore a database backup blindly: that can invalidate newly indexed URLs and ownership. Reconcile current slugs/reservations and SEO state from verified backups in staging before any approved data recovery. Schema removal or historical reuse requires a separate reviewed decision.

## Verification evidence

- Final focused run: **154 PHP tests / 1,787 assertions passed** across Studio URL/CMS/public pages/locator/migration and SEO Admin/migration safety tests. **31 JavaScript tests passed** across Studio CMS, shared Admin and public locator suites. Scoped Pint, PHP syntax, JavaScript syntax and `git diff --check` passed.
- Focused isolated SQLite coverage checks URL allocation, automatic/custom slugs, first activation, both existing roles, server-side confirmation, stale requests, immutable ownership, A → B → C, unavailable owners, active-only access, canonical/sitemap updates, SEO drafts/aliases, stale SEO forms, gallery/history preservation and transactional/idempotent backfill.
- Chromium uses disposable local fixture records at 1440px, 768px and 390px, actual Blade/assets, native keyboard disclosure, confirmation/cancel focus, automatic/manual previews, independent forms, successful URL submission and exact HTTP 301 Location. Screenshots are local-only under ignored `output/studio-url-review/`.
- Final browser checks found no horizontal overflow, page errors or failed asset requests; action buttons remained 44px high. A rejected reserved URL retained input for retry, and Cancel cleared it and reset the dirty indicator. Firefox/WebKit were not exercised.
- Initial implementation tests used isolated records. The separately authorized connected development setup below added only the registry/protection schema and current reservations; existing Studio content and SEO state were preserved. Native MySQL concurrency was subsequently verified using owner-authorized temporary fixtures as recorded below. Production rollout still requires separate approval.

## Connected development setup — 16 November 2026

The owner explicitly authorized the existing configured development connection; its private host/database identity was verified before any schema changes. Local logs confirmed missing `content_urls`; both the registry table and `studios.url_locked_at` were absent. Preflight found 69 active Studios, 142 phone numbers, valid unique slugs and no ownership conflicts. MySQL 5.7.29 uses InnoDB Dynamic row format with large-prefix support for the new unique index.

A complete private backup was taken before schema changes. All 64 table definitions were covered; dump completion, SHA-256 and gzip decompression round-trip were verified. The backup includes routines/events/triggers; no restore rehearsal was performed. Files and comparison manifests remain Git-ignored under Git-ignored `storage/app/private/`, outside the public directory.

Only `2026_11_16_000000_create_content_urls` was migrated, followed by one `studios:reserve-urls` backfill and read-only coverage checks. There are now 65 tables, 69 current reservations and 69 protected active Studios, with zero conflicts, duplicate paths or invented historical URLs. No inactive/draft records existed; the backfill includes all statuses when present. Cache/jobs migrations remain pending and untouched.

All 69 original Studio rows match their pre-change hashes after excluding only the new protection marker. IDs, names, slugs, statuses, phones, ordering, created_at and updated_at are unchanged. Gallery rows, SEO metadata and revisions match exactly; gallery relationships have no orphans. All 64 original schema definitions are preserved except for the approved new Studio column. Exactly one migration-ledger entry was added. An initial verification comparison flagged table ordering only; sorting both inventories confirmed no missing/unexpected tables, without rerunning migration/backfill.

Read-only HTTP-kernel verification using an existing authorized management role and in-memory sessions returned 200 for Add and all 69 Edit pages, with zero database writes. Chromium checked freshly rendered connected-data Add/Edit pages at 1440px and 390px, including URL disclosure/automatic preview: no overflow, browser errors or failed assets. No forms were submitted. No new URL-registry errors appeared in the local application log after migration. QA evidence remains ignored under `output/studio-url-connected-setup/`.

## Final native MySQL and browser verification — 16 November 2026

The owner changed the testing restriction explicitly: no software installation; create disposable Studio data in the existing authorized development database, verify, then delete it. Accordingly, these native MySQL tests used **temporary records in the owner-authorized connected development database**, not a separate database. No software was installed. The unused portable-server archive was removed without extraction. No Apache requests or deployment actions were performed.

Two temporary Studios (IDs 70 and 71) and their URL reservations were created with the unique marker `qa-url-20261116-c6774c97`. All original Studio, registry, gallery, SEO metadata and SEO revision rows were hashed before testing. No users or connected SEO records were inserted/updated. Authorization tests used existing-user role fixtures in memory, without changing stored roles or adding gates.

Native MySQL **5.7.29 / REPEATABLE READ** results:

| Check | Result |
| --- | --- |
| Same Studio, two concurrent URL requests | PASS: separate PHP processes; second request blocked on the held publication lock (~2.015 seconds), then rejected its stale expected URL |
| Two Studios claiming one URL | PASS: second transaction blocked (~2.084 seconds), then rejected the reserved URL; its Studio remained unchanged |
| Registry database uniqueness | PASS: duplicate insert rejected with SQLSTATE 23000 / MySQL 1062 |
| Current/historical reservations | PASS: conflicting paths rejected; no partial losing reservation or duplicate ownership |
| A → B → C and subsequent changes | PASS: every recorded old path returned HTTP 301 directly to the latest trusted named-route URL; current local public route returned 200 |
| Unknown, inactive and draft paths | PASS: unknown 404; all current/historical paths 404 while the temporary Studio was inactive/draft |
| Permissions and confirmation | PASS: Admin/Super Admin success; SEO Admin/unassigned 403; unauthenticated JSON request 401; missing confirmation, malformed URL, stale URL and forged ordinary-edit slug rejected |
| Current canonical and dynamic sitemap | PASS: self-canonical followed the new slug; stable identity stayed `studio.70`; only current eligible paths were included |
| Original connected SEO data | PASS: metadata and revision hashes unchanged throughout URL tests |

The 301 Location headers retained the configured production canonical origin. Their final paths were rendered through the local application and returned 200; tests did not follow them to Apache. Native MySQL SEO publication/draft/restore writes were deliberately not performed against shared SEO state. Those write scenarios, including canonical/draft conflicts and published metadata/history preservation, passed **25 focused isolated SQLite PHP tests / 257 assertions**. Eleven Studio JavaScript tests passed. A separate isolated MySQL database remains necessary if full native-MySQL SEO publication/restore write coverage is required.

Release preflight reran the 154 focused PHP tests / 1,787 assertions successfully on local PHP 8.3 and PHP 8.4, together with 31 JavaScript tests, scoped Pint, syntax and diff checks. GitHub Actions verifies PHP 8.3/8.4/8.5 on the pushed release commit. No connected database setup or temporary-record tests are repeated for release preparation. The manual Apache guide must inspect the actual hosting connection and ledger/schema/ownership coverage before deciding whether any migration or backfill is needed; development setup is not evidence that Apache uses the same database.

Chromium exercised the actual local Edit route, native CSRF-protected POST and server redirects at 1440px, 768px and 390px. All three widths passed confirmation/cancel, spinner/disabled loading states and overflow checks. A double-click produced one confirmation and one submitted request; reserved-URL validation retained input, then a corrected retry succeeded. No JavaScript errors or failed CSS/JS/image/font requests were observed. Screenshots and repeatable QA harness/results are ignored under `output/studio-url-mysql-review/` (`edit-*.png`, `confirm-*.png`, `validation-390.png`). Request-interception timing problems in the QA harness were corrected without changing application code; no implementation defect was found.

Cleanup completed in one scoped transaction: **2 temporary Studios and 14 test URL reservations deleted**, zero fixture rows/media remaining. Permanent application reservations for real content were not modified. Exact original-row hashes matched for `studios`, `content_urls`, `studio_images`, `seo_metadata` and `seo_revisions`. Final state: **69 active Studios, 142 phone numbers and 69 current reservations**. The local sitemap returned 200 with **82 unique URLs and zero test URLs**; the removed temporary current/historical paths returned 404. The temporary local QA server was stopped. No schema changes, migrations, connected SEO publications, commits, pushes or deployment occurred during this verification pass.
