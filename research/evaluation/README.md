# Literahaven entity-resolution evaluation

This is a reproducible **work-resolution evaluation on a frozen local, post-ingestion catalog**, not an evaluation of API availability or of upstream author resolution. No empirical ground truth or performance scores are shipped. All exported pairs are UNLABELED. Synthetic test fixtures are never research observations.

## Requirements and safety

Use the existing Composer installation, PHP with PDO SQLite and SQLite3, and the local application environment. In this Windows checkout PHP is available at `D:/laragon/bin/php/php-8.5.10-Win32-vs17-x64/php.exe`; if `php` is not on PATH, replace it in commands with `& "D:/laragon/bin/php/php-8.5.10-Win32-vs17-x64/php.exe"`. No npm changes or new packages are required. Both commands refuse production. Export performs SELECTs in a source transaction; evaluation reads local files and invokes the actual resolver only inside fresh SQLite `:memory:` databases. There is no `migrate:fresh`, truncation, source-mapping reset, network sync, or user-data export. Do not run these commands against a remote/production connection or while importing catalog data.

The sandbox uses the application's real migrations, `SemanticLiteratureResolver`, `LiteratureIdentityNormalizer`, and `CanonicalLiteratureProjector`. It accepts **no database name/URL option**. It cannot be redirected to the primary MySQL database. All source work mappings/identifiers are discarded; frozen author identities are retained.

## Export (local database, no API calls)

From the repository root:

```powershell
php artisan research:export-candidates --pairs=180 --seed=literahaven-v1 --out=research/evaluation/exports/study-v1
```

An output directory must not already exist. Supported real-provider source keys: `hardcover`, `google-books`, `anilist`, `kitsu`, `comic-vine`; manual-curated and unknown sources are excluded. Supported application formats only. Missing stable external IDs are excluded. All eligible records, mapped **and unmapped**, are frozen into `snapshot.json`; the pair sample is selected independently of existing work mappings.

Files: `candidate_pairs.csv`, `snapshot.json`, `export_manifest.json`. The manifest contains source/format counts, structural stratum counts, seed, sampling caps, hashes, and limitations. A target is not a guaranteed sample size. A shortage is reported; records and labels are never invented. These generated metadata files are ignored by Git; archive the frozen snapshot, manifest, labels and relevant code revision together in controlled research storage.

## Label manually

Read [LABELING.md](methodology/LABELING.md). Copy `candidate_pairs.csv` to `labels.csv` **in the same export directory**. Change only `ground_truth_label`, `evidence_url`, `labeling_notes`, `annotator_id`. Valid labels are `SAME_WORK`, `DIFFERENT_WORK`, `UNCERTAIN`, `UNLABELED`. A definitive label requires an annotator ID plus evidence or rationale. Do not infer labels from canonical IDs, algorithm predictions, shared title/ISBN alone, or AI. Source columns are immutable; metadata edits and duplicate/reversed duplicate pairs are rejected.

## Run all three methods

```powershell
php artisan research:evaluate --snapshot=research/evaluation/exports/study-v1/snapshot.json --labels=research/evaluation/exports/study-v1/labels.csv --seed=literahaven-v1 --out=research/evaluation/outputs/study-v1
```

One command runs independent title and title+author baselines and three clean replays of the real resolver (stable primary order, reverse, seeded permutation). Every method scores exactly the same definitively labeled pair IDs. A wholly UNLABELED/UNCERTAIN file is rejected before replay or output creation. Mixed files are explicitly marked partial; excluded counts are reported. Zero denominators yield JSON `null` / blank CSV fields.

Optional engineering diagnostics, **after keeping annotators blind to outputs**:

```powershell
php artisan research:evaluate --snapshot=research/evaluation/exports/study-v1/snapshot.json --labels=research/evaluation/exports/study-v1/candidate_pairs.csv --seed=literahaven-v1 --predict-only --out=research/evaluation/outputs/diagnostic-v1
```

`--predict-only` never emits metric scores, even if some pairs happen to have labels. Its status is `PREDICTIONS_ONLY_NOT_EVALUATED`.

## Results

Each fresh output directory contains:

- `predictions.csv/json`: individual predictions, stable source identifiers, canonical assignments and match methods.
- `metrics.csv/json`: TP, FP, FN, TN, accuracy, precision, recall, F1, false-merge rate, independently per method/order. Empty in prediction-only mode (CSV header only).
- `error_analysis.csv/json`: false merges and missed merges with evidence fields and an editable analysis-notes column; join `pair_id` to the label CSV for full metadata.
- `canonical_assignments.json`: every replayed record's canonical ID, stable cluster-membership hash, status, rule, confidence. IDs are meaningful only within an order. **Status `matched` is not a same-work prediction.**
- `order_sensitivity.json`: pairs whose proposed prediction changes across the three orders.
- `run_manifest.json`: input hashes, algorithm/framework/migration hashes, versions, complete insertion orders, label counts and scope limitations. No credentials are serialized.

Read [AUDIT.md](methodology/AUDIT.md), [PROTOCOL.md](methodology/PROTOCOL.md), and [baseline definitions](baselines/README.md) before reporting journal results. SQLite replay is portable but does not test MySQL collation/row locks/concurrency/performance; disclose the replay engine explicitly. Never select the best order after seeing labels.

## Automated checks

```powershell
php artisan test --compact tests/Unit/Research/ResolutionMetricsTest.php tests/Feature/Research/ResolutionEvaluationTest.php tests/Feature/Services/Literature/SemanticLiteratureResolverTest.php tests/Feature/Services/Literature/CanonicalLiteratureProjectionTest.php tests/Feature/Services/Literature/CatalogSyncServiceTest.php
```

Automated tests use synthetic, explicitly defined fixture labels only, on the test database. Passing tests validate the framework, not the empirical accuracy of Literahaven.

## Known demo exclusion and provenance limits

`CatalogSeeder` contains manually written examples attributed to real providers. The exporter conservatively excludes its five known non-manual identities: AniList 30012/5114/30035, Comic Vine 4050-1807, Google Books isbn-9780261102217. This applies even if a demo record was later refreshed. Manual-curated records are already excluded by provider policy. Provider attribution in the local database is not a cryptographically proven import log; original API payloads are unavailable. Verify identities and evidence links manually; do not describe the export as independently remote-validated API responses. The manifest records the exclusion list and the seeder file hash.
