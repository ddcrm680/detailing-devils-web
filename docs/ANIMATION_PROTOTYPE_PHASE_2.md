# Adaptive animation loader — Phase 2 refinement

Baseline: `c753564`. Uncommitted local prototype only. No commit, push, deployment, CRM request, form submission or database operation was performed.

The supplied `dd-animation-audit-*` Chrome artifacts and `dd-frame-prototype-*/BENCHMARK_REPORT.md`, scripts and raw results were read first and preserved. This report records the shared-loader refinement, rather than repeating the original audit.

## Scope and confirmed changes

- `frame-loader.js`: exact cache hits reuse their selection without scanning/allocating; available-budget checks skip eviction sorting; eviction selects obsolete entries before wanted frames. This removes a confirmed self-eviction/refill loop. Forward decoded working sets account for the opening frame, displayed frame and pending native preparation within the same budget.
- Current targets lead the queue. Discontinuities/reversal cancel obsolete downloads, while continuous slow scrubbing lets useful in-flight downloads finish. Compressed visited frames remain reusable on reverse traversal.
- Normal fetch limits are Home desktop 4 / compact 2, and Tour desktop 2 / compact 4. Slow connections cap both at 2. The limits respond to resize without aborting every existing request.
- Homepage JPEGs use native images, matching the original renderer and avoiding repeated bitmap creation/close costs. Desktop native images publish on load without speculative `decode()` bookkeeping.
- Tour AVIFs use bounded bitmaps except for the original opening frame and portrait source scales below half-size. The renderer-selection calculation uses the existing facade scale; drawing equations, frame positions, cropping, zoom, fades and captions are unchanged.
- Resource versions trigger redraw after renderer replacement. Object URLs remain valid until their native image is released. Menu/visibility/page-cache lifecycle behavior is preserved.

Only three runtime JavaScript files are used. All 240 JPEGs (18,692,350 bytes) and 330 AVIFs (35,250,971 bytes; 660 film frames) remain unchanged. No Blade, CSS, PHP, backend, dependencies or deployment settings changed.

## Matching comparison method

Chrome 156.0.8078.47, local GET/HEAD-only static harness, identical saved page HTML/assets/cache headers and a shared dependency proxy. Production JavaScript was verified against HEAD; v1 was frozen before refinement. No PHP application or CRM backend served browser tests.

Desktop 1440×900 / DPR 1 and mobile 390×844 / DPR 2. Fresh browser/cache for each run. Normal tests idle 5 seconds, then use 40 forward and 40 reverse wheel steps, 100 ms between steps and 800 ms settling. Slow desktop tests use 1.6 Mbps, 150 ms latency, CPU 4×, start scrolling at 20 seconds and settle 3 seconds at each end. Leaving the sequence is checked separately. Task/script CPU is CDP cumulative duration through reversal, including startup; frame error compares requested and last foreground source frame during visible forward/reverse phases.

Two original/v1 desktop control repeats and two final desktop repeats; one mobile control per original/v1 and two final mobile repeats; one slow run per arm. Ranges are run ranges, not confidence intervals. The original reports' absolute CPU numbers use another workload/timing and must not be mixed with this table.

A diagnostic found Chrome's estimated `navigator.connection` RTT/downlink varied even with the same transport emulation, switching the candidate between two and four downloads. Final candidate runs fix these JavaScript hints to 10 Mbps / 0 ms normally, and 1.6 Mbps / 150 ms when slow. Controls use the identical CDP transport/CPU/viewport; their loader uses save-data/effective-type, not RTT/downlink, and retained the same four-request mode. Final explicit per-page caps remove reliance on incidental estimates. Earlier candidate runs with mixed settings are diagnostics, not table inputs.

Normal desktop measurements are `stable-v2-desktop-*`, mobile measurements `latest-mobile-*`, slow measurements `stable-v2-slow-*`; controls are `final-*-baseline-*` / `final-*-v1-*`. The final responsive callback preserves the measured desktop and slow limits; its compact limits were benchmarked again twice.

## Desktop — normal connection

| Page / metric | Production baseline | Prototype v1 | Final v2 |
| --- | --- | --- | --- |
| Home idle sequence MiB | 17.87 | 1.18 | 1.18 |
| Home forward / reverse p95 frame error | 0 / 0 | 0 / 0 | 0 / 0 |
| Home task / script CPU seconds | 4.45–5.36 / 0.50–0.67 | 5.57–5.82 / 1.42–1.44 | 4.98–5.05 / 0.71–0.73 |
| Home forward callback Hz | 59.3 | 59.0–59.1 | 59.0–59.1 |
| Home retained pixel estimate MiB | 1906.35 | 158.20 | 158.20 |
| Tour idle sequence MiB | 33.68 | 0.70 | 1.10 |
| Tour forward / reverse p95 frame error | 0 / 0 | 3–8 / 5–6 | 0 / 0 |
| Tour task / script CPU seconds | 14.26–14.92 / 0.29–0.31 | 5.58–5.94 / 0.73–0.74 | 8.16–8.17 / 1.41–1.44 |
| Tour forward callback Hz | 39.9–42.4 | 58.8–59.1 | 59.1 |
| Tour retained pixel estimate MiB | 3625.49 | 153.81 | 153.81 |

## Mobile — normal connection

| Page / metric | Production baseline | Prototype v1 | Final v2 |
| --- | --- | --- | --- |
| Home idle sequence MiB | 17.87 | 1.18 | 1.18 |
| Home forward / reverse p95 frame error | 0 / 0 | 0 / 0 | 0 / 0 |
| Home task / script CPU seconds | 4.87 / 0.62 | 5.03 / 1.68 | 4.68–4.80 / 0.65–0.69 |
| Home forward callback Hz | 59.6 | 59.4 | 59.3–59.4 |
| Home retained pixel estimate MiB | 363.87 | 94.92 | 94.92 |
| Tour idle sequence MiB | 33.68 | 0.70 | 1.10 |
| Tour forward / reverse p95 frame error | 451 / 395 | 18 / 4 | 0–2 / 0 |
| Tour task / script CPU seconds | 7.55 / 0.46 | 5.42 / 0.77 | 7.65–7.78 / 2.33–2.50 |
| Tour forward callback Hz | 51.7 | 59.4 | 58.6–59.1 |
| Tour retained pixel estimate MiB | 274.66 | 87.89 | 87.89 |

## Throttled desktop — one run per arm

| Page / metric | Production baseline | Prototype v1 | Final v2 |
| --- | --- | --- | --- |
| Home forward p95 error | 215 | 233 | 233 |
| Home reverse p95 error | 203 | 47 | 22 |
| Home sequence MiB through reversal | 4.96 | 4.01 | 3.61 |
| Home first canvas draw seconds | 10.79 | 13.80 | 12.16 |
| Tour forward p95 error | 565 | 645 | 633 |
| Tour reverse p95 error | 190 | 100 | 119 |
| Tour sequence MiB through reversal | 4.95 | 3.60 | 3.60 |
| Tour first canvas draw seconds | 12.81 | 12.49 | 11.58 |

Frame errors are absolute source-frame differences (Tour uses film frames, not AVIF file numbers). Callback Hz is not measured presentation FPS. Fast forward scrolling still outruns required downloads; a faster callback cannot make unavailable frames appear. Home reverse recovery improves, but Tour's slow reverse error remains worse than v1 in this comparison. No claim of instantaneous slow-network scrubbing is made. Throttled CPU also regressed in these single runs: Home task time 17.38 to 20.86 seconds and Tour 10.28 to 12.87 seconds versus v1. These are unresolved results, not evidence of a universal processing improvement.

## Network, memory and processing interpretation

Idle sequence transfer is reduced about 93.4% for Home and 96.7% for Tour versus eager production. Tour's initial window increases from 7 to 13 files, approximately 0.70 to 1.10 MiB: a deliberate idle-bandwidth tradeoff for readiness. Full normal traversals still transfer approximately 17.87 / 33.68 MiB because no assets were changed. Other page/font/library bytes are not included in these sequence totals.

Decoded resource budgets remain 96 MiB compact / 160 MiB desktop, plus a 40 MiB compressed Blob cache. Pixel estimates sum tracked image dimensions × 4 with resource lifetime instrumentation; they are not physical browser RAM/VRAM measurements. Native decode surfaces, canvas/GPU buffers, browser caches and temporary intermediates are additional and not fully measurable here. Baseline native image references can outlive their browser-decoded surfaces, so its estimate is potential retained pixels, not measured physical residency. Unit tests verify budget enforcement through forward/reverse, resize and suspension.

Homepage profiling implicated repeated bitmap creation/disposal as well as cache bookkeeping. Native JPEG rendering plus allocation-free exact hits reduces script CPU and brings total task CPU close to the matching production range. Tour spends more CPU than v1 to retain accurate normal-connection frame presentation, while remaining below the eager production task cost on desktop. This tradeoff requires owner acceptance.

## Visual, responsive and functional verification

- Controlled canvas readback: v1 21/24 exact; final v2 24/24 exact. Six positions per page at 390 and 1440 widths, same raster context. The three v1 differences were reproduced (Home opening and next frame, Tour opening); native filtering removes them without changing source pixels or drawing equations.
- Fresh normal Chrome rendering checked at 320, 390, 768, 1024 and 1440 widths, including loaded desktop → mobile → desktop resizing. No horizontal overflow, page exceptions, console errors, HTTP errors or POST requests. Home/Tour screenshots at 390 and 1440 were visually reviewed.
- Menu opened before the loader module completed: zero frame requests/background movement until Escape. Downloads and scrolling then resumed.
- Rapid alternating jumps returned Home to `001 / 240` and Tour to progress 0. Tour replay returned to y=0 with opening hero opacity 1.
- 72 JavaScript tests passed (26 focused loader tests plus 46 existing tests); runtime/test JavaScript syntax checks passed.
- 40 isolated PHP tests / 544 assertions passed, with an unconfigured database, array cache/session/mail, temporary compiled views and disabled submission flags. No database access or submissions were required.
- Git diff review and `git diff --check` passed. No staged changes.

## Remaining limitations and acceptance decision

Ready for owner acceptance testing, not automatic production adoption. Normal final desktop frame accuracy is baseline-level in both repeated runs. Before selecting explicit caps, candidate Tour trials varied up to p95 34 forward / 18 reverse; the benchmark now records fixed hints and explicit limits to prevent that incidental configuration change from being hidden.

Acceptance should explicitly cover Tour's extra CPU versus v1, the extra 0.40 MiB idle Tour buffer, slow-network forward lag and mixed Tour reverse results. Small low-DPR portrait canvases require more native AVIF rendering to preserve original filtering; their performance was not benchmarked separately. Responsive geometry passed at 320, but performance timing is limited to the stated 390/DPR2 and 1440/DPR1 scenarios. Physical iPhone/Android, Safari, production HTTP/2/cache behavior, peak process RAM and exhaustive pixel parity for every frame/viewport remain unverified.

## Files and artifacts

Runtime: `public/assets/js/modules/frame-loader.js`, `home.js`, `studio-tour.js`.
Focused tests: `tests/js/frame-loader.test.js`.
Documentation: `docs/DETAILING_DEVILS_REPO_MAP.md`, this report.

Original audit/reference directories remain untouched and untracked. Temporary harness, frozen v1/original sources, raw measurements, pixel hashes and screenshots remain under `%TEMP%/dd-frame-phase2-1aab3fb3a9ad4b579e921cc51920a46b`. Nothing was staged, committed, pushed or deployed.

## Per-run summary

```json
[
  {
    "group": "desktop",
    "width": 1440,
    "repeat": 1,
    "name": "home",
    "arm": "baseline",
    "slow": false,
    "taskSeconds": 4.453267,
    "scriptSeconds": 0.49586,
    "initialSequenceBytes": 18737796,
    "reverseSequenceBytes": 18737796,
    "forwardErrorP95": 0,
    "reverseErrorP95": 0,
    "pixelEstimatePeakBytes": 1998950400
  },
  {
    "group": "desktop",
    "width": 1440,
    "repeat": 2,
    "name": "home",
    "arm": "baseline",
    "slow": false,
    "taskSeconds": 5.364826,
    "scriptSeconds": 0.666953,
    "initialSequenceBytes": 18737796,
    "reverseSequenceBytes": 18737796,
    "forwardErrorP95": 0,
    "reverseErrorP95": 0,
    "pixelEstimatePeakBytes": 1998950400
  },
  {
    "group": "desktop",
    "width": 1440,
    "repeat": 1,
    "name": "home",
    "arm": "v1",
    "slow": false,
    "taskSeconds": 5.573717,
    "scriptSeconds": 1.423111,
    "initialSequenceBytes": 1235132,
    "reverseSequenceBytes": 18737796,
    "forwardErrorP95": 0,
    "reverseErrorP95": 0,
    "pixelEstimatePeakBytes": 165888000
  },
  {
    "group": "desktop",
    "width": 1440,
    "repeat": 2,
    "name": "home",
    "arm": "v1",
    "slow": false,
    "taskSeconds": 5.816021,
    "scriptSeconds": 1.439536,
    "initialSequenceBytes": 1235132,
    "reverseSequenceBytes": 18737796,
    "forwardErrorP95": 0,
    "reverseErrorP95": 0,
    "pixelEstimatePeakBytes": 165888000
  },
  {
    "group": "desktop",
    "width": 1440,
    "repeat": 1,
    "name": "home",
    "arm": "refined",
    "slow": false,
    "taskSeconds": 4.982212,
    "scriptSeconds": 0.713433,
    "initialSequenceBytes": 1235132,
    "reverseSequenceBytes": 18737796,
    "forwardErrorP95": 0,
    "reverseErrorP95": 0,
    "pixelEstimatePeakBytes": 165888000
  },
  {
    "group": "desktop",
    "width": 1440,
    "repeat": 2,
    "name": "home",
    "arm": "refined",
    "slow": false,
    "taskSeconds": 5.05368,
    "scriptSeconds": 0.732119,
    "initialSequenceBytes": 1235132,
    "reverseSequenceBytes": 18737796,
    "forwardErrorP95": 0,
    "reverseErrorP95": 0,
    "pixelEstimatePeakBytes": 165888000
  },
  {
    "group": "desktop",
    "width": 1440,
    "repeat": 1,
    "name": "tour",
    "arm": "baseline",
    "slow": false,
    "taskSeconds": 14.26391,
    "scriptSeconds": 0.28871,
    "initialSequenceBytes": 35313527,
    "reverseSequenceBytes": 35313527,
    "forwardErrorP95": 0,
    "reverseErrorP95": 0,
    "pixelEstimatePeakBytes": 3801600000
  },
  {
    "group": "desktop",
    "width": 1440,
    "repeat": 2,
    "name": "tour",
    "arm": "baseline",
    "slow": false,
    "taskSeconds": 14.924233,
    "scriptSeconds": 0.307302,
    "initialSequenceBytes": 35313527,
    "reverseSequenceBytes": 35313527,
    "forwardErrorP95": 0,
    "reverseErrorP95": 0,
    "pixelEstimatePeakBytes": 3801600000
  },
  {
    "group": "desktop",
    "width": 1440,
    "repeat": 1,
    "name": "tour",
    "arm": "v1",
    "slow": false,
    "taskSeconds": 5.941521,
    "scriptSeconds": 0.741,
    "initialSequenceBytes": 729161,
    "reverseSequenceBytes": 35313527,
    "forwardErrorP95": 3,
    "reverseErrorP95": 5,
    "pixelEstimatePeakBytes": 161280000
  },
  {
    "group": "desktop",
    "width": 1440,
    "repeat": 2,
    "name": "tour",
    "arm": "v1",
    "slow": false,
    "taskSeconds": 5.581385,
    "scriptSeconds": 0.73365,
    "initialSequenceBytes": 729161,
    "reverseSequenceBytes": 35313527,
    "forwardErrorP95": 8,
    "reverseErrorP95": 6,
    "pixelEstimatePeakBytes": 161280000
  },
  {
    "group": "desktop",
    "width": 1440,
    "repeat": 1,
    "name": "tour",
    "arm": "refined",
    "slow": false,
    "taskSeconds": 8.162062,
    "scriptSeconds": 1.409985,
    "initialSequenceBytes": 1149823,
    "reverseSequenceBytes": 35313527,
    "forwardErrorP95": 0,
    "reverseErrorP95": 0,
    "pixelEstimatePeakBytes": 161280000
  },
  {
    "group": "desktop",
    "width": 1440,
    "repeat": 2,
    "name": "tour",
    "arm": "refined",
    "slow": false,
    "taskSeconds": 8.173557,
    "scriptSeconds": 1.437829,
    "initialSequenceBytes": 1149823,
    "reverseSequenceBytes": 35313527,
    "forwardErrorP95": 0,
    "reverseErrorP95": 0,
    "pixelEstimatePeakBytes": 161280000
  },
  {
    "group": "mobile",
    "width": 390,
    "repeat": 1,
    "name": "home",
    "arm": "baseline",
    "slow": false,
    "taskSeconds": 4.873385,
    "scriptSeconds": 0.616054,
    "initialSequenceBytes": 18737796,
    "reverseSequenceBytes": 18737796,
    "forwardErrorP95": 0,
    "reverseErrorP95": 0,
    "pixelEstimatePeakBytes": 381542400
  },
  {
    "group": "mobile",
    "width": 390,
    "repeat": 1,
    "name": "home",
    "arm": "v1",
    "slow": false,
    "taskSeconds": 5.034189,
    "scriptSeconds": 1.684944,
    "initialSequenceBytes": 1235132,
    "reverseSequenceBytes": 18737796,
    "forwardErrorP95": 0,
    "reverseErrorP95": 0,
    "pixelEstimatePeakBytes": 99532800
  },
  {
    "group": "mobile",
    "width": 390,
    "repeat": 1,
    "name": "home",
    "arm": "refined",
    "slow": false,
    "taskSeconds": 4.679544,
    "scriptSeconds": 0.654432,
    "initialSequenceBytes": 1235132,
    "reverseSequenceBytes": 18737796,
    "forwardErrorP95": 0,
    "reverseErrorP95": 0,
    "pixelEstimatePeakBytes": 99532800
  },
  {
    "group": "mobile",
    "width": 390,
    "repeat": 2,
    "name": "home",
    "arm": "refined",
    "slow": false,
    "taskSeconds": 4.801856,
    "scriptSeconds": 0.692065,
    "initialSequenceBytes": 1235132,
    "reverseSequenceBytes": 18737796,
    "forwardErrorP95": 0,
    "reverseErrorP95": 0,
    "pixelEstimatePeakBytes": 99532800
  },
  {
    "group": "mobile",
    "width": 390,
    "repeat": 1,
    "name": "tour",
    "arm": "baseline",
    "slow": false,
    "taskSeconds": 7.550428,
    "scriptSeconds": 0.461881,
    "initialSequenceBytes": 35313527,
    "reverseSequenceBytes": 35313527,
    "forwardErrorP95": 451,
    "reverseErrorP95": 395,
    "pixelEstimatePeakBytes": 288000000
  },
  {
    "group": "mobile",
    "width": 390,
    "repeat": 1,
    "name": "tour",
    "arm": "v1",
    "slow": false,
    "taskSeconds": 5.421181,
    "scriptSeconds": 0.766306,
    "initialSequenceBytes": 729161,
    "reverseSequenceBytes": 35313527,
    "forwardErrorP95": 18,
    "reverseErrorP95": 4,
    "pixelEstimatePeakBytes": 92160000
  },
  {
    "group": "mobile",
    "width": 390,
    "repeat": 1,
    "name": "tour",
    "arm": "refined",
    "slow": false,
    "taskSeconds": 7.649797,
    "scriptSeconds": 2.503995,
    "initialSequenceBytes": 1149823,
    "reverseSequenceBytes": 35313527,
    "forwardErrorP95": 0,
    "reverseErrorP95": 0,
    "pixelEstimatePeakBytes": 92160000
  },
  {
    "group": "mobile",
    "width": 390,
    "repeat": 2,
    "name": "tour",
    "arm": "refined",
    "slow": false,
    "taskSeconds": 7.77883,
    "scriptSeconds": 2.331291,
    "initialSequenceBytes": 1149823,
    "reverseSequenceBytes": 35313527,
    "forwardErrorP95": 2,
    "reverseErrorP95": 0,
    "pixelEstimatePeakBytes": 92160000
  },
  {
    "group": "slow",
    "width": 1440,
    "repeat": 1,
    "name": "home",
    "arm": "baseline",
    "slow": true,
    "taskSeconds": 18.410369,
    "scriptSeconds": 3.548879,
    "initialSequenceBytes": 69906,
    "reverseSequenceBytes": 5202557,
    "forwardErrorP95": 215,
    "reverseErrorP95": 203,
    "pixelEstimatePeakBytes": 414720000
  },
  {
    "group": "slow",
    "width": 1440,
    "repeat": 1,
    "name": "home",
    "arm": "v1",
    "slow": true,
    "taskSeconds": 17.37987,
    "scriptSeconds": 3.511826,
    "initialSequenceBytes": 0,
    "reverseSequenceBytes": 4199704,
    "forwardErrorP95": 233,
    "reverseErrorP95": 47,
    "pixelEstimatePeakBytes": 165888000
  },
  {
    "group": "slow",
    "width": 1440,
    "repeat": 1,
    "name": "home",
    "arm": "refined",
    "slow": true,
    "taskSeconds": 20.862527,
    "scriptSeconds": 4.219186,
    "initialSequenceBytes": 0,
    "reverseSequenceBytes": 3781271,
    "forwardErrorP95": 233,
    "reverseErrorP95": 22,
    "pixelEstimatePeakBytes": 165888000
  },
  {
    "group": "slow",
    "width": 1440,
    "repeat": 1,
    "name": "tour",
    "arm": "baseline",
    "slow": true,
    "taskSeconds": 13.018766,
    "scriptSeconds": 2.701367,
    "initialSequenceBytes": 41718,
    "reverseSequenceBytes": 5192550,
    "forwardErrorP95": 565,
    "reverseErrorP95": 190,
    "pixelEstimatePeakBytes": 622080000
  },
  {
    "group": "slow",
    "width": 1440,
    "repeat": 1,
    "name": "tour",
    "arm": "v1",
    "slow": true,
    "taskSeconds": 10.276385,
    "scriptSeconds": 2.746573,
    "initialSequenceBytes": 0,
    "reverseSequenceBytes": 3775240,
    "forwardErrorP95": 645,
    "reverseErrorP95": 100,
    "pixelEstimatePeakBytes": 161280000
  },
  {
    "group": "slow",
    "width": 1440,
    "repeat": 1,
    "name": "tour",
    "arm": "refined",
    "slow": true,
    "taskSeconds": 12.87251,
    "scriptSeconds": 3.556396,
    "initialSequenceBytes": 0,
    "reverseSequenceBytes": 3778250,
    "forwardErrorP95": 633,
    "reverseErrorP95": 119,
    "pixelEstimatePeakBytes": 161280000
  }
]
```
