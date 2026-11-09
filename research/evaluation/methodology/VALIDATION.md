# Validation record — 2026-11-09

## Passed engineering checks

- Relevant PHPUnit suite: 46 tests, 272 assertions, passed. Includes research metrics/baselines/export/dataset/replay tests plus the existing SemanticLiteratureResolver, CanonicalLiteratureProjection and CatalogSyncService feature tests.
- Local MySQL export `exports/local-2026-11-09-v2`: 180 candidate pairs, 6,303 frozen eligible source records; all 180 labels UNLABELED. Five provider source keys represented. Known CatalogSeeder demo identities excluded conservatively.
- The initial v1 engineering snapshot was superseded after auditing CatalogSeeder provenance; its diagnostic replay was stopped. v1 directories contain SUPERSEDED notices and must not be used for journal results.
- A regular evaluation command on an entirely UNLABELED dataset was deliberately rejected. This is an expected guard, not a test failure. It produced no scored results.
- Tests demonstrate deterministic repeated snapshots/predictions, zero-denominator handling, canonical equality rather than mapping-status classification, actual shared-ISBN resolution, order sensitivity, snapshot checksums, duplicate/contradictory/edited labels, restoration after a sandbox exception, existing-output protection, and production rejection.

## Not yet empirically established

- No candidate has received a human ground-truth label; no empirical accuracy, precision, recall, F1 or false-merge rate is available.
- No remote API identity verification or source-page validation was performed by this implementation task.
- No inter-annotator agreement, adjudication, held-out evaluation, population-level generalization or MySQL performance/concurrency benchmark has been performed.
- No frontend changes were made; npm build and browser interaction tests were not needed or run for this CLI-only framework.

The definitive status of a diagnostic or scored run is its generated `run_manifest.json`. Diagnostic output is predictions-only, not empirical evaluation. Preserve hashes and the exact code revision with research artifacts.

## Final real-snapshot diagnostic

`outputs/diagnostic-2026-11-09-v2` completed successfully with status PREDICTIONS_ONLY_NOT_EVALUATED: 180 pairs, 6,303 source records replayed in each of three clean orders, 900 method/order prediction rows, 18,909 canonical assignment rows. All actual truth values are null, scored pairs per method is 0, and metrics.json is an empty array. Export/run code fingerprints and the snapshot checksum were checked for equality after completion. This validates execution on real local metadata; it does not establish identity accuracy.
