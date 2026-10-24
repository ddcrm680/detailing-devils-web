# Adaptive frame loader — local prototype results

Baseline: c753564. Production/staging was not changed. The supplied docs/dd-animation-audit-* Chrome artifacts were read first.

## Method

The unchanged Blade views were rendered once with an unconfigured database, array sessions/cache/mail and disabled submission flags. A temporary, GET-only local server served the original scripts on one origin and the working prototype on another, using the same original assets. Main external styles/scripts/fonts were cached through a shared local proxy. Both origins used the same cache headers. This HTTP/1.1 harness isolates the loader; its absolute timings do not predict Wasmer HTTP/2 performance.

Chrome/Playwright: 1440x900 at DPR 1 and 390x844 at DPR 2, cold browser cache. Ten seconds at rest, forward/reverse wheel scrubbing, then leaving the sequence. Slow runs added 150 ms latency, 1.6 Mbps download and 4x CPU slowdown; scrolling started at 20 seconds and included three seconds to settle at each end. Slow scenarios were repeated twice. Normal scenarios were single paired runs. All tests were local; no CRM requests, submissions or database operations were made.

## Final normal-connection results

| Metric | Homepage desktop | Homepage mobile | Tour desktop | Tour mobile |
| --- | --- | --- | --- | --- |
| Initial sequence transfer, baseline -> prototype | 17.87 -> 1.18 MiB | 17.87 -> 1.18 MiB | 33.68 -> 0.70 MiB | 33.68 -> 0.70 MiB |
| Idle unique frames requested | 240 -> 7 | 240 -> 7 (old separate starter request made 241 request events) | 330 -> 7 | 330 -> 7 |
| Forward RAF frequency | 59.4 -> 60.0 Hz | 60.0 -> 60.0 Hz | 48.4 -> 60.0 Hz | 52.2 -> 60.0 Hz |
| Forward requested-vs-drawn frame error, p95 | 0 -> 0 | 0 -> 0 | 0 -> 9 film frames | 449 -> 2 film frames |
| Reverse frame error, p95 | 0 -> 0 | 0 -> 0 | 0 -> 4 film frames | 375 -> 1 film frame |
| Prototype retained bitmap pixel footprint peak | 158.20 MiB | 94.92 MiB | 153.81 MiB | 87.89 MiB |

Bitmap footprints are RGBA estimates from instrumented resource dimensions/lifetimes, not measured browser RAM or VRAM. The decoded cache limits are 160 MiB desktop and 96 MiB compact. Compressed visited data is bounded at 40 MiB. Browser-native buffers and decode intermediates are additional.

Full normal traversals still transfer approximately 17.87 MiB / 33.68 MiB because the assets are unchanged. The prototype avoids paying that cost before traversal. Its first revision refetched evicted compressed data on reverse traversal; the final cache removes that regression for these sequences. Its first cancellation strategy starved downloads during continuous slow-network scrolling; cancellation is now limited to discontinuities, reversal and suspension.

## Repeated slow-network results

| Metric | Homepage baseline -> prototype | Tour baseline -> prototype |
| --- | --- | --- |
| First canvas draw | 14.79-14.84 -> 14.25-14.26 s | 12.04-12.09 -> 12.07 s |
| Forward p95 frame error | 228 -> 233 | 612 -> 646 film frames |
| Reverse p95 frame error | 219 -> 41-43 | 594 -> 126-232 film frames |
| Total sequence transfer during test | 5.21 -> 3.93-3.94 MiB | 5.63-5.70 -> 3.57-3.73 MiB |

Fast forward scrubbing remains inaccurate when full-resolution images cannot arrive quickly enough. Both old and new implementations can show a far-behind frame; the prototype is slightly worse in this forward test. Improved callback frequency is not evidence of exact frame presentation. Startup gains are modest and workload-dependent.

Instrumented desktop homepage traversal also increased main-thread task time from about 2.97 to 4.23 seconds (and script time from 0.29 to 1.06 seconds). Tour desktop task time fell from 12.94 to 5.18 seconds while script bookkeeping increased. These are instrumented single runs, not field CPU percentiles.

## Visual / functional checks

Original drawing equations, interpolation, canvas sizes, frames, gradients, captions, overlays, zoom, replay and Lenis options are preserved. The loading strip now describes the initial working buffer, not a complete eager download.

Controlled readback compared six positions per sequence at each viewport: 21/24 comparisons were byte-identical. Three mobile opening samples differed. Raw native-size JPEG/AVIF pixel comparisons between Image and ImageBitmap had zero differences. Scaled opening comparisons found mean absolute RGB differences of 0.023-0.027 / 255 for Home and 0.082 / 255 for Tour, with maximum single-channel differences of 8-10 and 23 respectively. Resampling is the suspected cause; it is not a source-asset modification. Exact opening-frame output is not verified as preserved and needs review before adoption.

Responsive checks passed at widths 320, 390, 768, 1024 and 1440, including an already-loaded desktop page resized down and back up. No horizontal overflow. Menu opened while the shared module was still pending: no frame requests or background scrolling until Escape; loading then resumed. Tour replay returned to y=0 with the opening hero visible. No page exceptions, HTTP errors or POST requests in these checks. Browser visibility/page cache restoration, fallback image decoding and cleanup are covered by isolated unit tests; real Safari/iPhone/Android remain unverified.

## Verification / recommendation

60 JavaScript tests passed (14 focused loader tests plus 46 existing tests). 40 isolated PHP tests / 544 assertions passed. Three runtime JS syntax checks and git diff --check passed. No Blade, CSS, assets, backend, routes, dependencies or deployment settings were changed. Runtime changes are frame-loader.js, home.js and studio-tour.js, plus one test file and a repository-map note.

Keep this as an uncommitted prototype. Do not adopt unchanged until desktop Tour frame accuracy, the small mobile opening rendering differences and homepage bookkeeping overhead have been resolved or explicitly accepted. No commit, push or deployment was performed.

Raw per-run measurements: summary.json, v2-*.txt; visual-parity-repaint.txt; opening-pixel-difference.txt; responsive-qa.txt. The harness and baseline script copies are alongside this report in the temporary directory.
