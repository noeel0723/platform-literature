# Reproducible evaluation protocol

## Predeclare the experiment

Record the research question, bibliographic work granularity, local-catalog capture date, code revision, provider/format inclusion, target (initially 150–200 pairs if available), seed, label policy and primary order before reading predictions. All records from the five supported real providers are eligible, regardless of mapping success/status. No canonical IDs are used for sampling or labels. Stable order is lexical provider/external identity; reverse and SHA-256(seed|identity)-sorted order are sensitivity checks, not opportunities to cherry-pick.

## Sampling

The exporter reads raw persisted literature attributes plus ordered canonical authors and categories, excluding users and credentials. URL user-info and credential-like query parameters are removed; non-HTTP(S) URLs are omitted. These safety transformations affect only URL fields, never identity titles/authors/years/identifiers, and are part of the recorded export procedure. It constructs blocks from normalized exact titles, Unicode title tokens of length >=4, local author IDs and normalized non-provider-scoped identifiers. Within each block, source identities are sorted by seed hash and capped at 50; all cross-provider pairs among retained members are considered. Separate hash-ordered cross-provider controls examine the next 12 records per anchor independent of similarity. Seven structural pools use lowest-hash bounded reservoirs (max(1000, target*20) per pool); selection proceeds round-robin with pair deduplication. Pool caps and truncation counts are reported. A pair belongs to one selected pool even when eligible for several.

This is a stratified challenge sample, not uniform sampling of all catalog pairs. Shared normalizers, author identities, block caps, stored-data survivorship, unequal provider coverage and class prevalence limit generalizability. Random controls help expose out-of-block cases but do not eliminate selection bias. Do not infer global false-merge prevalence or unbiased production accuracy from the sample. Report observed class/format/provider/metadata-missingness coverage after manual labeling; class counts cannot be guaranteed in advance.

## Inputs and replay

The source snapshot contains the full eligible catalog, including non-sampled context records, so clustering is not run independently on each pair. All three methods use the same source metadata. Baselines use raw main-title normalization and, for baseline 2, frozen primary canonical author IDs. The proposed method runs actual resolver/projector code and actual migrations in a new SQLite `:memory:` database per order. No preexisting work IDs/identifier mappings or predicted labels are seeded. Every record is present before resolution; only calls to `resolve()` are reordered. Original local source record IDs are retained for projector tie-breaking.

The snapshot checksum must match its adjacent export manifest; labels are validated against snapshot identities and immutable metadata. Run manifests record snapshot/label SHA-256, code/migration/Composer-lock hashes, PHP/Laravel/SQLite versions, seed and full insertion orders. Generated timestamps do not affect predictions. Archive code plus Composer lock and input/output manifests to reproduce. A change in algorithm hashes is a different evaluated implementation; do not silently pool versions. SQLite equality/collation and missing row-lock semantics may differ from MySQL: this runner is a controlled portable algorithm study, **not a MySQL production execution benchmark**.

## Measurements

For each definitively labeled pair: actual SAME_WORK and same canonical identity is TP; actual DIFFERENT_WORK and same identity is FP (false merge); actual SAME_WORK and different identity is FN (missed merge); actual DIFFERENT_WORK and different identity is TN. Baseline predictions use their respective equality rules. Status `matched`, confidence, preferred source, and quality scores never substitute for canonical equality.

- Accuracy = (TP+TN)/(TP+FP+FN+TN)
- Precision = TP/(TP+FP)
- Recall = TP/(TP+FN)
- F1 = 2TP/(2TP+FP+FN)
- False merge rate among predicted merges = FP/(TP+FP)

Undefined denominators are null/blank, including zero-sample accuracy. Do not present null as 100% or invent scores. False merges and missed merges are exported individually with source trace IDs and rule paths. Review them for identifier collision, title ambiguity, missing author, edition/year mismatch and granularity problems using evidence; do not automatically assert root causes.

Stable, reverse and seeded proposed scores are separate rows over the same labeled pairs; never pool them as independent observations. Report prediction changes and order-sensitive pair IDs. These are pairwise classification metrics on sampled pairs, not B-cubed/ARI/cluster-completeness metrics. Because pairs may share records, naive independence-based confidence intervals are inappropriate. If tuning any rules later, separate training/development and test by work/entity groups to prevent record leakage, freeze a held-out test set, and report the revised algorithm separately. The current framework performs no tuning.

## Completion criteria

Code/tests passing is framework validation. An UNLABELED export or --predict-only run is **not completed empirical evaluation**. A partial label set must be reported as partial. Journal results require evidence-backed final labels, declared coverage/limitations and archived reproducible outputs; no fictional tables of scores are included.

## Known demo exclusion and provenance limits

`CatalogSeeder` contains manually written examples attributed to real providers. The exporter conservatively excludes its five known non-manual identities: AniList 30012/5114/30035, Comic Vine 4050-1807, Google Books isbn-9780261102217. This applies even if a demo record was later refreshed. Manual-curated records are already excluded by provider policy. Provider attribution in the local database is not a cryptographically proven import log; original API payloads are unavailable. Verify identities and evidence links manually; do not describe the export as independently remote-validated API responses. The manifest records the exclusion list and the seeder file hash.
