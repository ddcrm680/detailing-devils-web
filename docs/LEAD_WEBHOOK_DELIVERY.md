# Lead Webhook Delivery

Disabled by default. Database setup does not activate delivery. Existing forms, OTP, emails and lead-saving code are unchanged.

## Setup

First test and agree the HTTPS receiver, authentication, payload and acceptance contract with CallVault. Confirm that HTTP 200/201 mean the lead was accepted durably, not merely that a request reached the server. Agree idempotency and uncertain-response recovery: duplicate-free delivery requires receiver support for `lead_reference`. Confirm privacy handling, that the franchise record types identify the intended website leads, and that CallVault does not already import them.

After separate deployment/database approval, verify the target database and backup, then apply only this additive migration if absent:

```sh
php artisan migrate --path=database/migrations/2026_11_17_000000_create_lead_deliveries_table.php --force
```

### Required configuration

Only these five environment settings are used. Keep secrets on the host, never in Git or frontend code:

The committed `.env.example` documents these disabled defaults. Keep the actual `.env` private; it must not be committed or included in an upload ZIP. Uploading this release does not authorize changing Apache's environment or activating delivery.

```dotenv
LEAD_DELIVERY_ENABLED=false
LEAD_DELIVERY_WEBHOOK_URL=
LEAD_DELIVERY_TOKEN=
LEAD_DELIVERY_CONTRACT_CONFIRMED=false
LEAD_DELIVERY_FRANCHISE_SOURCES_CONFIRMED=false
```

The token is optional for provider-approved URL-only authentication. A nonblank token sends `Authorization: Bearer <token>`; a blank token sends no authentication header. A secret receiving URL must be handled like a token and never printed in logs or tickets. HTTPS remains mandatory, redirects are not followed, and credentials embedded as URL user/password are rejected.

Set the contract confirmation flag only after testing the provider's acceptance, authentication, idempotency and retry/reconciliation behavior. Set the franchise-source confirmation only after verifying source ownership. Keep delivery disabled until activation is approved. No JSON success field or response-reference echo is required.

### Fixed defaults

Standard settings live directly in `config/lead_delivery.php`; they do not require environment variables. If a provider needs a different header, change the existing config deliberately and test it before release.

| Setting | Default / purpose |
| --- | --- |
| Authentication / reference headers | `Authorization: Bearer` when a token is present; `Idempotency-Key` always carries the original lead UUID |
| Accepted statuses | 200, 201, under the explicitly confirmed provider contract |
| Connect / request timeout | 3 / 10 seconds |
| Retry limit / delays | 8 attempts; `60,300,900,3600,21600` seconds plus jitter |
| Processing budget / batch size | 45 seconds; 25 rows per source / delivery attempts by default, with the existing `--limit` option |

Older `LEAD_DELIVERY_ACK_*`, header, timeout and retry environment overrides are no longer read. Account for Laravel configuration caching when deploying the new config; no host configuration or caches are changed by this local refinement.

## Activation

While delivery remains disabled, initialize once at the chosen cutover time:

```sh
php artisan leads:deliver --activate
```

Confirmation defaults to No. `--activate --force` is the explicit non-interactive alternative. Four checkpoint rows permanently exclude all existing IDs; activation sends nothing. Repeating it preserves the original cutoffs. Missing/partial checkpoints stop delivery and require recovery, never automatic recreation. Submissions before the initial cutover are intentionally excluded.

## Cron

The existing global Laravel `schedule:run` cron is already verified on Apache and runs every minute. `routes/console.php` registers `leads:deliver` every minute with `withoutOverlapping()`. Do not create a second cron or a separate direct `leads:deliver` cron. The temporary heartbeat test is absent.

Inspect registration without executing scheduled tasks:

```sh
php artisan schedule:list
```

Registration does not activate delivery. Missing URL or disabled delivery exits before lead database access, discovery, checkpoint creation or sending. The scheduler's overlap mutex uses Laravel's existing cache store, which must be operational; it is separate from lead processing. Do not create cache tables or change host configuration implicitly. The command retains its own MySQL lock and per-attempt processing leases.

Only after separate approval, configure the tested receiver, initialize the permanent checkpoints and explicitly enable `LEAD_DELIVERY_ENABLED=true` with both confirmation flags true. Verify the host's CLI PHP, application/database settings and outbound TLS. No queue worker is needed. Default batches discover up to 25 rows per source and process at most 25 deliveries within a 45-second budget. Keep logs outside the web root. No server cron is added or changed by this release.

## Manual commands

| Mode | Command | Effect |
| --- | --- | --- |
| Run | `php artisan leads:deliver` | Discover new eligible rows and deliver/retry when enabled and configured |
| Activate | `php artisan leads:deliver --activate` | One-time permanent cutoffs; no HTTP requests |
| Status | `php artisan leads:deliver --status` | Read-only totals and up to 10 recent deliveries needing attention |

Activation/status work without a webhook URL or delivery credentials. Status shows safe references, HTTP codes, reason codes and retry times in UTC; it never reads payloads. A successful run exit means processing completed, not that every lead was delivered.

## Troubleshooting

| Result | Action |
| --- | --- |
| `Webhook not configured; delivery skipped.` | Normal run with no URL: successful skip, no database access or attempts. Configure the receiver later. |
| `Lead delivery is disabled.` | Configured URL but delivery is disabled; nothing is processed. |
| Invalid URL/authentication/processing configuration | Correct the named setting; these remain errors, not skips. A missing token alone is valid for approved URL-only authentication. |
| `missing_submission_timestamp`, `invalid_submission_timestamp`, `invalid_lead_fields`, `invalid_lead_encoding`, `predates_activation` | Source row retained for review; inspect only the referenced record through authorized access. No guessed replacement values. |
| Timeout, 408/425/429/5xx | Automatic bounded retry with the same UUID/payload; respects `Retry-After`. |
| 401/403 | Retained for review; current batch stops. Pause delivery/cron while correcting credentials. |
| 400/422 | Retained for review as `validation_rejected`; correct and reconcile before an approved recovery. |
| 409 | Retained for review as `conflict`; never assume a duplicate response confirms this lead was accepted. |
| 200/201, regardless of body | Marked Delivered immediately; no further retries. The receiver is responsible for acceptance and storage. |
| 202 or other unexpected statuses | Needs Attention (internal `review`) as `unexpected_http_status`; no completed-delivery claim or automatic replay. |
| Exhausted retries | Retained for review; reconcile before an approved recovery. Never force duplicates. |

`--status` includes review rows, failed pending retries and expired processing leases. Unknown error text is redacted. Logs contain only safe reference/reason metadata. Review entries are not automatically reset or replayed.

To pause/roll back, disable delivery and its cron; preserve the table, checkpoints, UUIDs and encryption keys. Never truncate delivery history or reset source IDs. Agree payload retention before activation; deduplication metadata must survive. The migration refuses destructive rollback.

### Response handling

Delivery results use HTTP status only. Every HTTP 200/201 response marks the delivery complete, including empty, HTML, malformed JSON and explicit error bodies. No JSON acknowledgement, reference echo or body inspection is performed. Confirm with the receiving provider that those statuses represent successful acceptance; its API is responsible for storing the lead and returning an appropriate failure status otherwise. Response bodies and secret URLs are not logged.

HTTP 202 and unexpected responses require attention; they are not automatically marked delivered or replayed. HTTP 400/422, 401/403 and 409 require validation, authentication or conflict review respectively. Temporary statuses and timeouts retain the existing backoff behavior. A timeout may occur after acceptance; every retry keeps the same UUID and snapshot, but only receiver-side idempotency can prevent a second CRM lead. Legacy body-rejection reason codes remain readable in status output for existing history.

## Reference: source identities

| Form | Stable source ID and exact filter | Timestamp |
| --- | --- | --- |
| Enquiry | `tbl_SaveContactForm7_8.id`; `source=website-home`, `query_type=service-enquiry` | `created_on` |
| Prime | `tbl_common_leads.id`; `record_type=prime-franchise` | `add_franchise_date` |
| FOFO | `tbl_common_leads.id`; `record_type=FOFO` | `add_franchise_date` |
| X-Clusive | `tbl_common_leads.id`; `record_type=xclusive` | `add_franchise_date` |

The homepage Franchise Enquiry CTA reaches its existing service-enquiry form; this classification is preserved. Franchise `record_type` alone does not prove website ownership; confirm other writers and X-Clusive's provisional type before activation.

Existing tables use Query Builder, not enquiry/franchise models. The Enquiry table remains MyISAM; franchise remains InnoDB. FOFO/X-Clusive use the timestamp default, while Prime supplies application-local time. MySQL timestamps are converted to UTC using `UNIX_TIMESTAMP()`; date-only `created_date` is never substituted. The audited session offset matches `Asia/Kolkata` (+05:30); verify web/CLI settings on the eventual host.

## Exact allowlisted field mapping

Common payload keys: `schema_version`, `lead_reference`, `website_source`, `form`, `submitted_at`, `customer`, `enquiry`. The UUID is assigned once when discovered and reused in the JSON and configured reference header on every attempt. `website_source` is `detailing-devils-website`. Form names/types are fixed from the actual four handlers above.

| Payload field | Enquiry column | Franchise column |
| --- | --- | --- |
| `customer.name` | trimmed `first_name` + `last_name` | `name` |
| `customer.mobile` | `your_phone` | `phone` |
| `customer.email` | `your_email`, normally null because the current form has no email input | `email` |
| `customer.city` | `city` | `city` |
| `customer.state`, `customer.country` | not included | `state`, `country` |
| `enquiry.service_interest` | `interested_in` | null |
| `enquiry.franchise_model` | null | derived from the exact record type: Prime, FOFO or X-Clusive |
| `enquiry.details.vehicle` | `car_model` | not included |

Phone formatting is preserved as saved. Franchise writers currently strip the leading `+` from E.164; no guessed country prefix is added. Agree this representation with the receiver. Blank optional values are null or omitted from `details`. Customer name/mobile and any supplied email are validated before sending; invalid source rows become review entries without a payload snapshot.

Prime `enquiry.details` mapping:

| Stored column | Payload detail |
| --- | --- |
| `profession` | `occupation` |
| `experience` | `business_experience` |
| `newbussiness` | `business_name` |
| `type_of_bussiness` | `industry` |
| `existingfacility` | `has_property` |
| `spacearound` | `carpet_area` |
| `territory_city`, `territory` | `preferred_city`, `preferred_area` |
| `wantstart` | `start_timeline` |
| `bussinessowner` | `decision_maker` (not business ownership on Prime) |
| `contacted` | `contact_methods` |
| `bussiness_description` | `partnership_reason` |

FOFO/X-Clusive `enquiry.details` mapping:

| Stored column | Payload detail |
| --- | --- |
| `profession`, `hearabout` | `profession`, `heard_about_us` |
| `whichcityfranchise`, `wantstart` | `preferred_city`, `start_timeline` |
| `newbussiness`, `bussinessowner` | `business_plan`, `owns_business` |
| `type_of_bussiness`, `product_service`, `bussiness_description` | `business_type`, `products_or_services`, `business_description` |
| `experienceautomotive`, `experience` | `has_automotive_experience`, `automotive_experience` |
| `existingautomotive`, `existingfacility` | `has_automotive_facility`, `automotive_facility` |
| `spacearound`, `contacted` | `has_property`, `previous_contact` |

DOB, age, OTPs/proofs, property images/paths, precise property addresses, financial/budget/legal answers, internal CRM assignments/comments/statuses and other non-allowlisted columns are neither selected nor transmitted. Existing survey values are not reinterpreted as provider enums. No studio assignment, UTM/campaign attribution or customer information is fabricated.

## Four synthetic payload examples

These illustrate schema version 1.0, not real leads or a provider-approved contract. Optional details are included only when stored. UUIDs below are illustrative; actual references are generated once.

Enquiry:

```json
{
  "schema_version": "1.0",
  "lead_reference": "11111111-1111-4111-8111-111111111111",
  "website_source": "detailing-devils-website",
  "form": {"name": "Enquiry", "type": "service-enquiry"},
  "submitted_at": "2026-11-17T06:30:00+00:00",
  "customer": {"name": "Synthetic Customer", "mobile": "+919876543210", "email": null, "city": "Jaipur"},
  "enquiry": {"kind": "service", "service_interest": "Ceramic Coating", "franchise_model": null, "details": {"vehicle": "Test Vehicle"}}
}
```

Prime:

```json
{
  "schema_version": "1.0",
  "lead_reference": "22222222-2222-4222-8222-222222222222",
  "website_source": "detailing-devils-website",
  "form": {"name": "Prime Franchise Application", "type": "prime-franchise"},
  "submitted_at": "2026-11-17T06:30:00+00:00",
  "customer": {"name": "Synthetic Applicant", "mobile": "919876543210", "email": "applicant@example.test", "city": "Jaipur", "state": "Rajasthan", "country": "India"},
  "enquiry": {
    "kind": "franchise", "service_interest": null, "franchise_model": "Prime",
    "details": {"occupation": "entrepreneur", "business_experience": "yes", "business_name": "Example Company", "industry": "Automotive", "has_property": "yes", "carpet_area": "2500", "preferred_city": "Ajmer", "decision_maker": "self", "partnership_reason": "Build a detailing business"}
  }
}
```

FOFO:

```json
{
  "schema_version": "1.0",
  "lead_reference": "33333333-3333-4333-8333-333333333333",
  "website_source": "detailing-devils-website",
  "form": {"name": "FOFO Franchise Application", "type": "FOFO"},
  "submitted_at": "2026-11-17T06:30:00+00:00",
  "customer": {"name": "Synthetic Applicant", "mobile": "919876543210", "email": "applicant@example.test", "city": "Jaipur", "state": "Rajasthan", "country": "India"},
  "enquiry": {
    "kind": "franchise", "service_interest": null, "franchise_model": "FOFO",
    "details": {"preferred_city": "Ajmer", "business_plan": "Open a studio", "owns_business": "YES", "business_type": "Automotive", "has_automotive_experience": "YES", "automotive_experience": "Five years", "has_property": "NO"}
  }
}
```

X-Clusive:

```json
{
  "schema_version": "1.0",
  "lead_reference": "44444444-4444-4444-8444-444444444444",
  "website_source": "detailing-devils-website",
  "form": {"name": "X-Clusive Franchise Application", "type": "xclusive"},
  "submitted_at": "2026-11-17T06:30:00+00:00",
  "customer": {"name": "Synthetic Applicant", "mobile": "919876543210", "email": "applicant@example.test", "city": "Jaipur", "state": "Rajasthan", "country": "India"},
  "enquiry": {
    "kind": "franchise", "service_interest": null, "franchise_model": "X-Clusive",
    "details": {"preferred_city": "Udaipur", "business_plan": "Premium detailing studio", "owns_business": "NO", "has_automotive_experience": "NO", "has_automotive_facility": "NO", "has_property": "NO"}
  }
}
```

## Reliability notes

- One new InnoDB table holds checkpoint rows (`source_record_id=0`) and encrypted delivery snapshots. Unique source references and physical `record_key` values prevent duplicate discovery, including franchise reclassification.
- Discovery anti-joins IDs above the fixed cutoffs; cutoffs never advance, so late-committing lower IDs remain discoverable. The post-activation scan grows over time; real MySQL query-plan/performance testing remains a deployment check.
- MySQL advisory locking, expiring per-attempt leases and claim tokens prevent normal overlap and stale updates. No database transaction is held open across HTTP. Repeated attempts require receiver-side idempotency; the sender alone cannot guarantee exactly-once creation after an uncertain response.
- Separate accepted homepage rows remain separate leads. Polling cannot recover source rows deleted before discovery or reconstruct their initial values after other applications edit them. Retries use the original encrypted snapshot; keep its decryption keys.
- Tests use isolated in-memory SQLite and mocked HTTP. The additive table has been created on the shared MySQL 5.7.29 database, but native MySQL concurrency/performance, cPanel cron and actual provider acceptance remain unverified. No connected database writes or real API calls are authorized by this guide.
