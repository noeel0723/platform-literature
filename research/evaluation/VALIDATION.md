# Verification record — 2026-11-10

Branch: research/manual-ground-truth-v1. Starting HEAD: e1407b2b049012fca762489d41eddd5ba1a5791b. Changes are not committed automatically. Export and evaluation manifests record dirty-worktree state and source file hashes.

Fresh dataset exported through local MySQL in a read-only REPEATABLE READ transaction. No provider API requests made. No historical dataset, AI labels, predictions or exploratory outputs restored. PHP framework source architecture was reused only where documented in AUDIT.md.

Eligible source records: 6303. Candidate pairs: 180. Preselected subset: 80. Seed: manual-study-v1.

Provider counts: anilist: 715, comic-vine: 3310, google-books: 506, hardcover: 1711, kitsu: 61.

Snapshot SHA-256: `28a08d939fbaf3e048153c0926408907b27ed60fbe4655328b541157bb85b3b8`. Snapshot, candidate CSV and selected CSV checksums verified after testing; all 180 pairs remain UNLABELED. No working labels/progress were fabricated.

Executed checks:

- `php artisan test --compact tests/Feature/Research/ManualStudyTest.php tests/Unit/Research/ResolutionMetricsTest.php tests/Feature/Services/Literature/SemanticLiteratureResolverTest.php tests/Feature/Services/Literature/CanonicalLiteratureProjectionTest.php tests/Feature/Services/Literature/CatalogSyncServiceTest.php`: **49 passed, 284 assertions** (23 research tests + 26 existing regression tests).
- `node --test tests/js/research-labeling.test.mjs`: **6 passed**. Covers fresh unlabeled defaults, manual documentation validation, clear/uncertain, search/filter/progress, draft serialization/namespace and URL safety. This is state logic coverage, not real-browser interaction coverage.
- `npm run build`: passed. Existing optional fontaine font-optimization warning; no dependency installed.
- `vendor/bin/pint --dirty --format agent`: passed after formatting.
- `git diff --check`: passed.
- Local server HTTP smoke check: GET /, API /api/study with local request token, entrypoint JavaScript and stylesheet all HTTP 200; API returned 180 pairs, all UNLABELED.
- `research:evaluate --dataset=manual-study-v1 --run=unlabeled-must-refuse`: exit 1 with explicit unavailable-human-ground-truth explanation; no output run directory created.
- Automated tests cover source immutability, CSV/Unicode/leading zeros, checksums, duplicate pairs, arbitrary paths, manual evidence validation, save recovery, stale revision conflict, HTTP save/export, contradictory labels, real-resolver replay isolation including failure restoration, production environment rejection, and lack of main-site research routes.
- Existing production resolver, catalog sync, models, migrations, main-site routes and UI components have no diff. Only existing Vite build entry list is extended.

Browser visual/interactive QA: **not completed**. Both available browser/computer-use runtimes failed initialization with `failed to write kernel assets: The system cannot find the path specified (os error 3)`. HTTP checks do not establish visual layout correctness. Desktop/mobile presentation, keyboard traversal and browser-close recovery should be checked in the actual browser before annotating a large set.

Runtime: server started at http://127.0.0.1:8765. MySQL Laragon was initially stopped; the existing MySQL 8.0.30 instance was started using its existing my.ini/datadir and loopback binding. No database initialization/reset was run.

Remaining manual work: personally verify the frozen 80-pair subset, record evidence/annotator IDs, adjudicate uncertain/contradictory cases, export labels.csv, then execute evaluation. Do not report accuracy/precision/recall/F1 yet. Automated synthetic test results are software-validation artifacts only and are deleted from temporary testing directories. The full 6,303-record scientific replay has not been scored because no valid human ground truth exists.
