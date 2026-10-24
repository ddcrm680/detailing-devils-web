# Animation prototype V2.1 — one bounded nearest-available fallback

## Release approval and rollback baseline

The owner approved V2.1 for the animation-only release after reviewing the prototype. The measurements below remain the historical pre-release benchmark; the approval does not remove the slow-network lag or the measured Homepage timing uncertainty.

Before release, local `main` and fetched `origin/main` both resolved to `c7535646c3749912e26645e2bdfa95203871b4b7`. Wasmer's active version was confirmed as **v38**, ID `dav_KwGIWtPuEmG6`, created 2026-10-23T10:14:41.901025Z. Its public Homepage and Tour scripts matched that commit; the shared loader did not exist on staging. This records the original-animation rollback target. No rollback or version activation was performed. If rollback becomes necessary, obtain owner authorization to restore this known version or revert the animation release; do not reset or force-push `main`.

The release intentionally excludes large raw screenshots, traces and temporary harness files. The selected V1 report, Phase 2 report, V2.1 per-run summaries and frozen V2 sources preserve the benchmark history. Raw artifact links below refer to retained local reference folders and may not be present in a fresh clone.

Final local pre-push verification on 2026-10-24: 80 JavaScript tests and 40 isolated PHP tests / 544 assertions passed, with no database or live-service dependency. Runtime/test JavaScript and relevant PHP syntax checks passed. Chrome checks at 1440 and 390 confirmed opening/final frames, forward/reverse traversal, all five Tour captions, zoom/fade, final sections and replay to y=0, without page/console/HTTP errors or submissions. Idle loading remained seven Home files / 1,233,802 encoded bytes and thirteen Tour files / 1,147,362 encoded bytes at both widths. Loaded resizing across 320, 390, 768, 1024 and 1440 had no horizontal overflow. All 240 JPEGs and 330 AVIFs, Blade/CSS, backend and deployment settings remained unchanged. These are local pre-push checks, not a claim that deployment has completed.

Status: local, uncommitted first experiment. Only `public/assets/js/modules/frame-loader.js` changed at runtime. Homepage and Tour integrations, renderers, equations, captions, assets, Blade, CSS, PHP, dependencies and deployment configuration are unchanged. No CRM/database access, real submissions, commit, push or deployment.

## Baseline evidence

Read and preserved the [original Chrome audit artifacts](dd-animation-audit-5d3003d6717f41a19d9f094ff44a6fd2/), [V1 report](dd-frame-prototype-c4276343281c4a89ad7b67d3536702d3/BENCHMARK_REPORT.md) and [V2 report](ANIMATION_PROTOTYPE_PHASE_2.md). No separate acceptance report was present in `docs/` when this task began; the latest conversation and retained acceptance diagnostics supplied the additional evidence.

The acceptance inspection found Tour target file 200, displayed file 12 and a closer downloaded file 109, with no decode in progress. The directional working window excluded file 109. These are zero-based AVIF file indices; presented-source errors below use film frames (two per AVIF). Acceptance also measured a fresh unprofiled desktop forward/reverse p95 of 12/2, so the prior V2 report's 0/0 was not a reliable universal result.

## Minimal change

- Preserve the directional working set and add at most one planned fallback candidate, using already downloaded Blobs. No fallback fetches are added.
- Admit only a strictly closer source than every currently cached source. Equal-distance arrivals do not replace the current fallback.
- Start an extra fallback decode only when the decode pool is idle. Reserve a worker slot and pixel capacity for requested work; background work cannot consume that reservation while the fallback is pending. Exact targets remain first and can preempt an undisplayed retained fallback if the pixel budget requires it.
- Reuse a useful late completion as the fallback rather than closing and decoding the same data again. Reject out-of-order completions that would worsen the available source.
- Count the candidate within the existing working-set/byte limits; preserve the existing displayed-frame pin. No new retirement queue or disposal delay.

Regression tests cover excluded downloaded frames, exact dispatch while the pool contains pending fallback/speculation, insufficient pixels, exact preemption after budget shrink, late result reuse, out-of-order results, reversal/ties/repeated pumps and suspension. The reported-case test deliberately fails on frozen V2 (returns 6 instead of downloaded 109) and passes on V2.1. The fixture starts with seven frames; the real Tour acceptance case started with thirteen.

## Repeated benchmark method

18 fresh Chrome visits: three runs per arm for desktop Tour (1440×900, DPR1), throttled mobile Tour (390×844, DPR2, touch, 1.6 Mbps, 150ms latency, 4× CPU), and normal desktop Home. Paired order AB, BA, AB. Identical current rendered HTML/assets, cache headers, cold browser cache and shared warmed dependency proxy. Frozen V2 sources and working-tree V2.1 were served separately. No Laravel server/database endpoint served the browser tests.

Normal transport is unthrottled with 1× CPU. Network Information hints are fixed identically at 10 Mbps/0ms normally and 1.6 Mbps/150ms when slow. Normal input starts at 5 seconds, slow input at 20 seconds. Forty wheel steps forward and reverse, 100ms cadence, followed by fixed 2s normal / 5s slow settling per direction; then departure and an offscreen check. Frame-error phases include their fixed settling interval. Absolute results are not directly comparable to earlier reports using shorter settling periods.

All timed runs are **unprofiled**: no CPU profiler or Chrome tracing. Identical lightweight network, canvas source, image-lifetime and loader counters were applied to both arms. Loader modules are intercepted only to attach these identical counters; frame requests use the normal emulated transport. The counters add overhead, and module cold-wire timing is not a production measurement. Sequence HTTP transfer is reported separately from other page bytes.

The frame metric is the absolute difference between requested film frame and the source/row successfully submitted to canvas. This is a presented-source proxy, not compositor/physical-screen presentation or FPS. CDP task duration includes startup through reverse settling and native-call waits; it is not OS CPU utilization. Bitmap creation elapsed includes queuing and promise delivery, not isolated codec CPU. Creation counts/timings cover the entire visit including departure. Home uses native images; zero bitmap timings in its raw summary mean unmeasured native decoding, not zero decoding cost.

Latency measures a file-target change to its first exact-source canvas draw. It is conditional on that target actually being fulfilled; superseded/unserved targets are excluded and fulfillment percentages are reported. Recovery is the first post-input-loop exact-source sample sustained for 150ms, measured from the loop-end marker (after its final 100ms cadence wait). It includes existing Lenis/interpolation behavior. It is not last-wheel-to-photon latency. Values below one RAF interval mean already caught up at the checkpoint; they should not be interpreted as subframe input-latency wins. All runs recovered within the fixed settling windows.

Values are **median [minimum–maximum] of three runs**, including medians of per-run p95s. These are small local samples, not confidence intervals or field percentiles.

## Desktop Tour — normal connection

| Metric | Frozen V2 | V2.1 |
| --- | --- | --- |
| Forward source-error p95, film frames | 2 [1–3] | 2 [1–2] |
| Reverse source-error p95, film frames | 0 [0–1] | 0 [0–1] |
| Sequence transfer through reversal, MiB | 33.678 [33.678–33.678] | 33.678 [33.678–33.678] |
| Main-thread task duration, seconds | 8.599 [8.326–9.064] | 8.525 [8.477–8.861] |
| Script duration, seconds | 1.443 [1.305–1.551] | 1.475 [0.993–1.511] |
| Bitmap creations, entire visit | 688 [684–690] | 678 [673–680] |
| Mean bitmap creation elapsed, ms | 56.54 [56.49–62.90] | 56.53 [56.41–66.70] |
| Bitmap creation p95 elapsed, ms | 85.70 [85.40–97.90] | 86.70 [84.60–92.40] |
| Conditional exact-file draw latency p95, forward ms | 2.50 [1.10–2.50] | 2.60 [2.60–3.30] |
| Conditional exact-file draw latency p95, reverse ms | 3.90 [3.40–4.00] | 3.40 [3.20–3.50] |
| Exact-file fulfilled/requested, forward % | 83.54 [67.07–85.93] | 83.54 [79.88–90.21] |
| Exact-file fulfilled/requested, reverse % | 100.00 [94.22–100.00] | 100.00 [90.27–100.00] |
| Stable exact-source recovery after loop, forward ms | 5.0 [1.2–15.1] | 1.2 [0.9–1.9] |
| Stable exact-source recovery after loop, reverse ms | 5.7 [5.2–8.2] | 8.0 [4.6–9.1] |
| Peak cache + in-flight pixel reservation, MiB | 153.81 [153.81–153.81] | 153.81 [153.81–153.81] |
| Peak retained compressed payload, MiB | 33.618 [33.618–33.618] | 33.618 [33.618–33.618] |

Desktop forward p95 median remains 2; the observed range narrows from 1–3 to 1–2. Reverse median remains 0, with a 0–1 range in both arms. This is not proof of baseline-level zero error or a broad consistency improvement. Main-thread changes are small relative to variation.

## Mobile Tour — throttled

| Metric | Frozen V2 | V2.1 |
| --- | --- | --- |
| Forward source-error p95, film frames | 634 [634–634] | 283 [228–291] |
| Reverse source-error p95, film frames | 113 [112–119] | 66 [55–67] |
| Sequence transfer through reversal, MiB | 3.600 [3.545–3.657] | 3.622 [3.484–3.670] |
| Main-thread task duration, seconds | 9.534 [9.309–10.245] | 9.474 [9.388–10.306] |
| Script duration, seconds | 2.578 [2.554–2.838] | 2.586 [2.550–2.889] |
| Bitmap creations, entire visit | 55 [50–55] | 64 [62–65] |
| Mean bitmap creation elapsed, ms | 42.09 [39.77–46.06] | 40.09 [39.48–40.22] |
| Bitmap creation p95 elapsed, ms | 72.60 [60.30–90.60] | 69.70 [65.30–78.10] |
| Conditional exact-file draw latency p95, forward ms | 1000.00 [915.60–1233.40] | 766.30 [733.00–1116.60] |
| Conditional exact-file draw latency p95, reverse ms | 5.10 [4.40–8.00] | 6.50 [6.30–7.60] |
| Exact-file fulfilled/requested, forward % | 3.95 [3.95–3.95] | 3.96 [3.95–3.96] |
| Exact-file fulfilled/requested, reverse % | 8.21 [7.60–8.51] | 8.21 [8.21–8.51] |
| Stable exact-source recovery after loop, forward ms | 1609.3 [1524.6–1838.0] | 1383.9 [1344.1–1734.5] |
| Stable exact-source recovery after loop, reverse ms | 38.0 [28.3–38.6] | 38.7 [24.4–39.3] |
| Peak cache + in-flight pixel reservation, MiB | 87.89 [87.89–87.89] | 87.89 [87.89–87.89] |
| Peak retained compressed payload, MiB | 3.593 [3.538–3.650] | 3.615 [3.477–3.663] |

Forward p95 median improves 634→283 (about 55%); reverse improves 113→66 (about 42%). Every paired run improves in both directions. V2.1 forward variation is 228–291, whereas V2 stayed at 634: the gains are consistent, but absolute variance is not lower. Large residual errors remain because needed source files cannot arrive fast enough.

Only 13 of 329 moving forward file targets were fulfilled exactly in each slow run. The improvement is more effective nearest-frame display, not new bandwidth or instantaneous exact scrubbing. Conditional latency must be read alongside this low coverage. Forward recovery median improves about 1.61→1.38s; ranges overlap. Reverse recovery remains about 38ms. No universal CPU improvement is established.

## Homepage preservation check — three normal desktop pairs

| Metric | Frozen V2 | V2.1 |
| --- | --- | --- |
| Forward / reverse error p95 | 0 / 0 (all runs) | 0 / 0 (all runs) |
| Task duration, seconds | 5.522 [5.393–5.544] | 5.693 [5.483–5.822] |
| Script duration, seconds | 0.919 [0.842–0.922] | 0.914 [0.880–0.932] |
| Sequence transfer, MiB | 17.870 [17.870–17.870] | 17.870 [17.870–17.870] |
| Peak reserved pixels, MiB | 158.20 [158.20–158.20] | 158.20 [158.20–158.20] |

Accuracy is unchanged. Task median increases about 3.1% (5.52→5.69s), with overlapping ranges and slightly lower script median. This is a remaining timing concern; three shared-machine runs do not establish its cause or significance. Homepage throttled performance was not exhaustively benchmarked in this first Tour-focused experiment.

## Network and memory

Idle requests/transfers remain seven Home files / about 1.18 MiB and thirteen Tour files / about 1.10 MiB in both versions, including the slow pre-input checkpoint. Full normal sequence transfer remains about 17.87 / 33.68 MiB. Slow Tour reversal transfer median is about 3.60→3.62 MiB; timing/cancellation varies, and no transfer reduction is claimed. No extra fallback fetch policy was introduced.

No recorded cache/reservation or compressed-byte limit violations in any of the 18 visits. Limits remain 160 MiB desktop / 96 MiB compact, plus 40 MiB compressed. Peak committed+in-flight pixel reservation is 153.81 MiB desktop Tour, 87.89 MiB slow mobile Tour and 158.20 MiB Home. Normal Tour compressed payload peak is 33.62 MiB. These are loader-managed RGBA estimates/reservations, not measured physical browser RAM/VRAM; other browser/GPU surfaces and intermediate allocations remain unmeasured.

## Visual/functional checks and tests

- 24/24 controlled canvas comparisons against frozen V2 match byte for byte: six positions per page at 390/DPR2 and 1440/DPR1, including opening, frame handover, zoom and final film positions. Readback uses the same raster context for both arms; it does not prove every pixel on every browser/device.
- Candidate Home/Tour at 1440 and 390: opening and final source frames 0→239 / 0→659; reverse returns 0. All five Tour captions, final zoom, fade, ending panel, models/footer and replay passed. Replay returns y=0. No console/page errors, failed HTTP assets or POST requests in this check.
- Loaded-page resizing at 320, 390, 768, 1024 and 1440: no horizontal overflow or page exceptions.
- 80 JavaScript tests passed (34 loader + 46 existing). Runtime/test syntax checks passed.
- 40 isolated PHP tests / 544 assertions passed, with an unconfigured database, temporary compiled views, array sessions/cache/mail and disabled submission flags. No database or live service was needed.
- Whitespace/diff and protected-file review passed; nothing staged.

## Second experiment — not implemented or recommended yet

The `release(image)` body is unchanged: bitmap closure remains immediate. No RAF/idle retirement queue, disposal timer or memory-budget increase was added. Existing acceptance profiles identify substantial bitmap-close stacks (approximately 1.86s V2 versus 1.15s V1 in one desktop sample), but do not compare immediate disposal against a delayed variant. Repeated profiles of the current pipeline cannot by themselves demonstrate a worthwhile benefit from delay. A separate repeated, bounded-memory A/B would be required before recommending it; this task does not implement that experiment.

## Decision and remaining limits

The fallback bug is fixed and produces consistently better accuracy under the tested bandwidth starvation. Desktop median is unchanged and nonzero, so a blanket baseline-zero/production-acceptance claim would be unjustified. Residual slow-network lag, the small Home task-time increase, actual process/GPU memory and physical Safari/iPhone/Android behavior remain open. No additional optimization or deployment is authorized by these results.

## Preserved comparison capability and files

Runtime change: `public/assets/js/modules/frame-loader.js` only. Focused tests: `tests/js/frame-loader.test.js`. Documentation: this report, repository-map note and three readable frozen V2 references. Existing reports and the pre-existing Home/Tour changes remain preserved.

Local original / V2 / V2.1 origins: http://127.0.0.1:55610 / http://127.0.0.1:55611 / http://127.0.0.1:55612. Use `/` and `/studio-tour`. These are GET-only animation previews with application endpoints disabled, available while the temporary helper runs. V2 snapshots below preserve source comparisons beyond temporary-file cleanup; original scripts remain in the supplied V1 artifact folder and committed baseline `c753564`.

Frozen V2 source SHA-256 (UTF-8 normalized LF):

- `docs/animation-benchmarks/frame-loader-v2.js.txt`: `7906252dfc8e4fef684444a294afcae2dfb0ced44767f4346ec8b6529e06dbe7`
- `docs/animation-benchmarks/home-v2.js.txt`: `46b18cccefc483d632162be4e2e83e118a27adc9fa59129100763695ac3429fe`
- `docs/animation-benchmarks/studio-tour-v2.js.txt`: `e0db1dd881d5940a112ed00fc9e3477cb041ca776d23f7f75af18f2c11b6122b`

Detailed scripts/raw JSON, original/V2 snapshots, pixel hashes and functional results remain under `%TEMP%/dd-frame-v21-e7811bf292544cebb9c08efd778d5199`. `bench-template.js` and `bench-runs.ps1` define the protocol; scenario/arm/repeat JSON files contain the raw observations. This report retains their compact per-run summary below. No commit, push, deployment or live CRM/database operation was performed.

## Per-run measurements (JSON Lines)

```jsonl
{"scenario":"desktop","width":1440,"repeat":1,"name":"tour","arm":"v2","slow":false,"forwardP95":3,"reverseP95":0,"forwardMax":6,"reverseMax":0,"sequenceBytes":35313527,"taskSeconds":8.325862,"scriptSeconds":1.443003,"bitmapCreations":684,"bitmapMeanMs":56.537134502886396,"bitmapP95Ms":85.40000000037253,"forwardLatencyP95Ms":1.099999999627471,"reverseLatencyP95Ms":3.400000000372529,"requested":{"initial":1,"forward":328,"reverse":329,"leave":26},"fulfilled":{"initial":1,"forward":220,"reverse":329,"leave":3},"recoveryMs":{"forward":1.200000001117587,"reverse":5.699999999254942},"reservedPeakBytes":161280000,"blobPeakBytes":35250971,"budgetViolations":0}
{"scenario":"desktop","width":1440,"repeat":2,"name":"tour","arm":"v2","slow":false,"forwardP95":1,"reverseP95":0,"forwardMax":3,"reverseMax":0,"sequenceBytes":35313527,"taskSeconds":8.599294,"scriptSeconds":1.550562,"bitmapCreations":688,"bitmapMeanMs":56.48982558136286,"bitmapP95Ms":85.69999999925494,"forwardLatencyP95Ms":2.5,"reverseLatencyP95Ms":3.900000000372529,"requested":{"initial":1,"forward":327,"reverse":329,"leave":26},"fulfilled":{"initial":1,"forward":281,"reverse":329,"leave":3},"recoveryMs":{"forward":5,"reverse":5.199999999254942},"reservedPeakBytes":161280000,"blobPeakBytes":35250971,"budgetViolations":0}
{"scenario":"desktop","width":1440,"repeat":3,"name":"tour","arm":"v2","slow":false,"forwardP95":2,"reverseP95":1,"forwardMax":4,"reverseMax":3,"sequenceBytes":35313527,"taskSeconds":9.064063,"scriptSeconds":1.305214,"bitmapCreations":690,"bitmapMeanMs":62.903043478241436,"bitmapP95Ms":97.90000000037253,"forwardLatencyP95Ms":2.5,"reverseLatencyP95Ms":4,"requested":{"initial":1,"forward":328,"reverse":329,"leave":26},"fulfilled":{"initial":1,"forward":274,"reverse":310,"leave":3},"recoveryMs":{"forward":15.100000001490116,"reverse":8.199999999254942},"reservedPeakBytes":161280000,"blobPeakBytes":35250971,"budgetViolations":0}
{"scenario":"desktop","width":1440,"repeat":1,"name":"tour","arm":"v21","slow":false,"forwardP95":2,"reverseP95":0,"forwardMax":4,"reverseMax":0,"sequenceBytes":35313527,"taskSeconds":8.525152,"scriptSeconds":1.510709,"bitmapCreations":680,"bitmapMeanMs":56.52514705886407,"bitmapP95Ms":86.70000000111759,"forwardLatencyP95Ms":3.299999998882413,"reverseLatencyP95Ms":3.400000000372529,"requested":{"initial":1,"forward":328,"reverse":329,"leave":26},"fulfilled":{"initial":1,"forward":274,"reverse":329,"leave":2},"recoveryMs":{"forward":0.900000000372529,"reverse":8},"reservedPeakBytes":161280000,"blobPeakBytes":35250971,"budgetViolations":0}
{"scenario":"desktop","width":1440,"repeat":2,"name":"tour","arm":"v21","slow":false,"forwardP95":1,"reverseP95":0,"forwardMax":4,"reverseMax":0,"sequenceBytes":35313527,"taskSeconds":8.476866,"scriptSeconds":1.474634,"bitmapCreations":678,"bitmapMeanMs":56.40914454275418,"bitmapP95Ms":84.59999999962747,"forwardLatencyP95Ms":2.600000001490116,"reverseLatencyP95Ms":3.200000001117587,"requested":{"initial":1,"forward":327,"reverse":329,"leave":26},"fulfilled":{"initial":1,"forward":295,"reverse":329,"leave":3},"recoveryMs":{"forward":1.900000000372529,"reverse":9.099999999627471},"reservedPeakBytes":161280000,"blobPeakBytes":35250971,"budgetViolations":0}
{"scenario":"desktop","width":1440,"repeat":3,"name":"tour","arm":"v21","slow":false,"forwardP95":2,"reverseP95":1,"forwardMax":5,"reverseMax":5,"sequenceBytes":35313527,"taskSeconds":8.861425,"scriptSeconds":0.992657,"bitmapCreations":673,"bitmapMeanMs":66.69791976221869,"bitmapP95Ms":92.40000000037253,"forwardLatencyP95Ms":2.600000001490116,"reverseLatencyP95Ms":3.5,"requested":{"initial":1,"forward":328,"reverse":329,"leave":26},"fulfilled":{"initial":1,"forward":262,"reverse":297,"leave":3},"recoveryMs":{"forward":1.200000001117587,"reverse":4.599999999627471},"reservedPeakBytes":161280000,"blobPeakBytes":35250971,"budgetViolations":0}
{"scenario":"slow-mobile","width":390,"repeat":1,"name":"tour","arm":"v2","slow":true,"forwardP95":634,"reverseP95":113,"forwardMax":634,"reverseMax":174,"sequenceBytes":3775051,"taskSeconds":9.534323,"scriptSeconds":2.577684,"bitmapCreations":55,"bitmapMeanMs":46.05999999997968,"bitmapP95Ms":90.59999999962747,"forwardLatencyP95Ms":1233.4000000003725,"reverseLatencyP95Ms":5.099999999627471,"requested":{"initial":1,"forward":329,"reverse":329,"leave":23},"fulfilled":{"initial":1,"forward":13,"reverse":27,"leave":1},"recoveryMs":{"forward":1838,"reverse":28.300000000745058},"reservedPeakBytes":92160000,"blobPeakBytes":3767289,"budgetViolations":0}
{"scenario":"slow-mobile","width":390,"repeat":2,"name":"tour","arm":"v2","slow":true,"forwardP95":634,"reverseP95":112,"forwardMax":634,"reverseMax":169,"sequenceBytes":3834776,"taskSeconds":10.24508,"scriptSeconds":2.837727,"bitmapCreations":55,"bitmapMeanMs":42.094545454633504,"bitmapP95Ms":72.60000000149012,"forwardLatencyP95Ms":1000,"reverseLatencyP95Ms":8,"requested":{"initial":1,"forward":329,"reverse":329,"leave":24},"fulfilled":{"initial":1,"forward":13,"reverse":28,"leave":1},"recoveryMs":{"forward":1609.2999999988824,"reverse":38},"reservedPeakBytes":92160000,"blobPeakBytes":3827011,"budgetViolations":0}
{"scenario":"slow-mobile","width":390,"repeat":3,"name":"tour","arm":"v2","slow":true,"forwardP95":634,"reverseP95":119,"forwardMax":634,"reverseMax":180,"sequenceBytes":3717419,"taskSeconds":9.309041,"scriptSeconds":2.553969,"bitmapCreations":50,"bitmapMeanMs":39.77000000014901,"bitmapP95Ms":60.29999999888241,"forwardLatencyP95Ms":915.5999999996275,"reverseLatencyP95Ms":4.400000000372529,"requested":{"initial":1,"forward":329,"reverse":329,"leave":23},"fulfilled":{"initial":1,"forward":13,"reverse":25,"leave":1},"recoveryMs":{"forward":1524.6000000014901,"reverse":38.59999999962747},"reservedPeakBytes":92160000,"blobPeakBytes":3710032,"budgetViolations":0}
{"scenario":"slow-mobile","width":390,"repeat":1,"name":"tour","arm":"v21","slow":true,"forwardP95":228,"reverseP95":55,"forwardMax":288,"reverseMax":81,"sequenceBytes":3653674,"taskSeconds":9.474319,"scriptSeconds":2.585677,"bitmapCreations":64,"bitmapMeanMs":39.484374999970896,"bitmapP95Ms":65.29999999888241,"forwardLatencyP95Ms":1116.5999999996275,"reverseLatencyP95Ms":6.5,"requested":{"initial":1,"forward":329,"reverse":329,"leave":24},"fulfilled":{"initial":1,"forward":13,"reverse":27,"leave":1},"recoveryMs":{"forward":1734.5,"reverse":38.69999999925494},"reservedPeakBytes":92160000,"blobPeakBytes":3646097,"budgetViolations":0}
{"scenario":"slow-mobile","width":390,"repeat":2,"name":"tour","arm":"v21","slow":true,"forwardP95":283,"reverseP95":67,"forwardMax":340,"reverseMax":89,"sequenceBytes":3848644,"taskSeconds":10.305526,"scriptSeconds":2.888823,"bitmapCreations":65,"bitmapMeanMs":40.089230769242235,"bitmapP95Ms":78.09999999962747,"forwardLatencyP95Ms":766.3000000007451,"reverseLatencyP95Ms":6.300000000745058,"requested":{"initial":1,"forward":328,"reverse":329,"leave":24},"fulfilled":{"initial":1,"forward":13,"reverse":28,"leave":1},"recoveryMs":{"forward":1383.9000000003725,"reverse":39.30000000074506},"reservedPeakBytes":92160000,"blobPeakBytes":3840879,"budgetViolations":0}
{"scenario":"slow-mobile","width":390,"repeat":3,"name":"tour","arm":"v21","slow":true,"forwardP95":291,"reverseP95":66,"forwardMax":330,"reverseMax":86,"sequenceBytes":3798365,"taskSeconds":9.388008,"scriptSeconds":2.549952,"bitmapCreations":62,"bitmapMeanMs":40.217741935543955,"bitmapP95Ms":69.70000000111759,"forwardLatencyP95Ms":733,"reverseLatencyP95Ms":7.599999999627471,"requested":{"initial":1,"forward":328,"reverse":329,"leave":24},"fulfilled":{"initial":1,"forward":13,"reverse":27,"leave":1},"recoveryMs":{"forward":1344.0999999996275,"reverse":24.40000000037253},"reservedPeakBytes":92160000,"blobPeakBytes":3790788,"budgetViolations":0}
{"scenario":"home-desktop","width":1440,"repeat":1,"name":"home","arm":"v2","slow":false,"forwardP95":0,"reverseP95":0,"forwardMax":0,"reverseMax":0,"sequenceBytes":18737796,"taskSeconds":5.3933,"scriptSeconds":0.841654,"bitmapCreations":0,"bitmapMeanMs":0,"bitmapP95Ms":0,"forwardLatencyP95Ms":1.800000000745058,"reverseLatencyP95Ms":0.900000000372529,"requested":{"initial":1,"forward":239,"reverse":237,"leave":34},"fulfilled":{"initial":1,"forward":239,"reverse":237,"leave":5},"recoveryMs":{"forward":36,"reverse":6.899999998509884},"reservedPeakBytes":165888000,"blobPeakBytes":18692350,"budgetViolations":0}
{"scenario":"home-desktop","width":1440,"repeat":2,"name":"home","arm":"v2","slow":false,"forwardP95":0,"reverseP95":0,"forwardMax":0,"reverseMax":0,"sequenceBytes":18737796,"taskSeconds":5.522299,"scriptSeconds":0.919122,"bitmapCreations":0,"bitmapMeanMs":0,"bitmapP95Ms":0,"forwardLatencyP95Ms":2,"reverseLatencyP95Ms":1.099999999627471,"requested":{"initial":1,"forward":239,"reverse":237,"leave":34},"fulfilled":{"initial":1,"forward":239,"reverse":237,"leave":4},"recoveryMs":{"forward":5.600000001490116,"reverse":4.699999999254942},"reservedPeakBytes":165888000,"blobPeakBytes":18692350,"budgetViolations":0}
{"scenario":"home-desktop","width":1440,"repeat":3,"name":"home","arm":"v2","slow":false,"forwardP95":0,"reverseP95":0,"forwardMax":0,"reverseMax":0,"sequenceBytes":18737796,"taskSeconds":5.544166,"scriptSeconds":0.922063,"bitmapCreations":0,"bitmapMeanMs":0,"bitmapP95Ms":0,"forwardLatencyP95Ms":2,"reverseLatencyP95Ms":1,"requested":{"initial":1,"forward":239,"reverse":237,"leave":34},"fulfilled":{"initial":1,"forward":239,"reverse":237,"leave":4},"recoveryMs":{"forward":9.300000000745058,"reverse":9.599999999627471},"reservedPeakBytes":165888000,"blobPeakBytes":18692350,"budgetViolations":0}
{"scenario":"home-desktop","width":1440,"repeat":1,"name":"home","arm":"v21","slow":false,"forwardP95":0,"reverseP95":0,"forwardMax":0,"reverseMax":0,"sequenceBytes":18737796,"taskSeconds":5.692535,"scriptSeconds":0.913944,"bitmapCreations":0,"bitmapMeanMs":0,"bitmapP95Ms":0,"forwardLatencyP95Ms":2,"reverseLatencyP95Ms":0.900000000372529,"requested":{"initial":1,"forward":239,"reverse":237,"leave":34},"fulfilled":{"initial":1,"forward":239,"reverse":237,"leave":3},"recoveryMs":{"forward":8,"reverse":7.300000000745058},"reservedPeakBytes":165888000,"blobPeakBytes":18692350,"budgetViolations":0}
{"scenario":"home-desktop","width":1440,"repeat":2,"name":"home","arm":"v21","slow":false,"forwardP95":0,"reverseP95":0,"forwardMax":0,"reverseMax":0,"sequenceBytes":18737796,"taskSeconds":5.821833,"scriptSeconds":0.932146,"bitmapCreations":0,"bitmapMeanMs":0,"bitmapP95Ms":0,"forwardLatencyP95Ms":2.199999999254942,"reverseLatencyP95Ms":1,"requested":{"initial":1,"forward":239,"reverse":237,"leave":34},"fulfilled":{"initial":1,"forward":239,"reverse":237,"leave":3},"recoveryMs":{"forward":8.699999999254942,"reverse":6.300000000745058},"reservedPeakBytes":165888000,"blobPeakBytes":18692350,"budgetViolations":0}
{"scenario":"home-desktop","width":1440,"repeat":3,"name":"home","arm":"v21","slow":false,"forwardP95":0,"reverseP95":0,"forwardMax":0,"reverseMax":0,"sequenceBytes":18737796,"taskSeconds":5.482813,"scriptSeconds":0.879642,"bitmapCreations":0,"bitmapMeanMs":0,"bitmapP95Ms":0,"forwardLatencyP95Ms":2,"reverseLatencyP95Ms":0.8000000007450581,"requested":{"initial":1,"forward":239,"reverse":237,"leave":34},"fulfilled":{"initial":1,"forward":239,"reverse":237,"leave":4},"recoveryMs":{"forward":1.5,"reverse":6.299999998882413},"reservedPeakBytes":165888000,"blobPeakBytes":18692350,"budgetViolations":0}
```
