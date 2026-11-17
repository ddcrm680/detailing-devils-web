# Lead Webhook Delivery

Local implementation; disabled by default. No connected migration, activation, cron or real webhook call has been performed. Existing forms, OTP, emails and lead-saving code are unchanged.

## Setup

First agree the HTTPS receiver, authentication, payload, durable-success acknowledgement and idempotency contract with CallVault. The receiver must deduplicate `lead_reference` for the full retry/recovery period. Confirm privacy handling, that the franchise record types identify the intended website leads, and that CallVault does not already import them.

After separate deployment/database approval, verify the target database and backup, then apply only this additive migration if absent:

```sh
php artisan migrate --path=database/migrations/2026_11_17_000000_create_lead_deliveries_table.php --force
```

### Required configuration

Set secrets on the host, never in Git or frontend code. These illustrative acknowledgement values must be replaced with the provider's documented contract:

```dotenv
LEAD_DELIVERY_ENABLED=false
LEAD_DELIVERY_WEBHOOK_URL=https://receiver.example.test/webhook
LEAD_DELIVERY_TOKEN=configure-securely-on-host
LEAD_DELIVERY_CONTRACT_CONFIRMED=false
LEAD_DELIVERY_FRANCHISE_SOURCES_CONFIRMED=false
LEAD_DELIVERY_ACK_PATH=stored
LEAD_DELIVERY_ACK_VALUE=true
```

The example expects 200/201 AND JSON `{"stored":true}`. HTTP 200 alone is never enough. Set the two confirmation flags to true only after their respective agreements are verified. Keep delivery disabled until activation is reviewed.

### Optional settings

Defaults normally need no changes. All settings are in `config/lead_delivery.php`.

| Environment setting | Default / purpose |
| --- | --- |
| `LEAD_DELIVERY_AUTH_HEADER`, `LEAD_DELIVERY_AUTH_SCHEME` | `Authorization`, `Bearer`; use an empty scheme for a custom token header |
| `LEAD_DELIVERY_REFERENCE_HEADER` | `Idempotency-Key`; always carries the same lead UUID |
| `LEAD_DELIVERY_ACK_STATUSES`, `LEAD_DELIVERY_ACK_TYPE` | `200,201`, `boolean`; other value types: `string`, `integer` |
| `LEAD_DELIVERY_ACK_REFERENCE_PATH` | Optional JSON path to verify the receiver echoes the UUID |
| `LEAD_DELIVERY_CONNECT_TIMEOUT`, `LEAD_DELIVERY_TIMEOUT` | 3 / 10 seconds |
| `LEAD_DELIVERY_MAX_ATTEMPTS`, `LEAD_DELIVERY_RETRY_DELAYS` | 8 attempts; `60,300,900,3600,21600` seconds plus jitter |

Acknowledgement paths support dot notation. Values are compared strictly. Account for existing Laravel configuration caching when changing host settings; no cache changes have been made here.

## Activation

While delivery remains disabled, initialize once at the chosen cutover time:

```sh
php artisan leads:deliver --activate
```

Confirmation defaults to No. `--activate --force` is the explicit non-interactive alternative. Four checkpoint rows permanently exclude all existing IDs; activation sends nothing. Repeating it preserves the original cutoffs. Missing/partial checkpoints stop delivery and require recovery, never automatic recreation. Submissions before the initial cutover are intentionally excluded.

## Cron

After approval, explicitly enable `LEAD_DELIVERY_ENABLED=true` with both confirmation flags true. Verify the host's CLI PHP, application/database settings and outbound TLS. Register only the intended host's minute-based cPanel cron, replacing these paths:

```cron
* * * * * /verified/php /APP_ROOT/artisan leads:deliver --limit=25 >> /PRIVATE_LOG_PATH/lead-delivery.log 2>&1
```

No queue worker is needed. Keep logs outside the web root. Default batches discover up to 25 rows per source and process at most 25 deliveries within a 45-second budget. Locking prevents overlapping runs. No cron is activated by this code.

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
| Invalid URL/authentication/acknowledgement configuration | Correct the named setting; these remain errors, not skips. |
| `missing_submission_timestamp`, `invalid_submission_timestamp`, `invalid_lead_fields`, `invalid_lead_encoding`, `predates_activation` | Source row retained for review; inspect only the referenced record through authorized access. No guessed replacement values. |
| Timeout, 408/425/429/5xx | Automatic bounded retry with the same UUID/payload; respects `Retry-After`. |
| 401/403 | Retained for review; current batch stops. Pause delivery/cron while correcting credentials. |
| 409/422, unconfirmed acknowledgement, exhausted retries | Retained for review; reconcile/correct the cause before an approved recovery. Never force duplicates. |

`--status` includes review rows, failed pending retries and expired processing leases. Unknown error text is redacted. Logs contain only safe reference/reason metadata. Review entries are not automatically reset or replayed.

To pause/roll back, disable delivery and its cron; preserve the table, checkpoints, UUIDs and encryption keys. Never truncate delivery history or reset source IDs. Agree payload retention before activation; deduplication metadata must survive. The migration refuses destructive rollback.

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
- MySQL advisory locking, expiring per-attempt leases and claim tokens prevent normal overlap and stale updates. No database transaction is held open across HTTP. Acknowledged repeats require receiver-side idempotency; the sender alone cannot guarantee exactly-once creation after an uncertain response.
- Separate accepted homepage rows remain separate leads. Polling cannot recover source rows deleted before discovery or reconstruct their initial values after other applications edit them. Retries use the original encrypted snapshot; keep its decryption keys.
- Tests use isolated in-memory SQLite and mocked HTTP. Real MySQL concurrency, cPanel cron and the actual provider acknowledgement remain unverified. No connected database writes or real API calls are authorized by this guide.
