# Audit of the actual implementation

Audited files: `SemanticLiteratureResolver.php`, `LiteratureIdentityNormalizer.php`, `CanonicalLiteratureProjector.php`, `CatalogSyncService.php`, `AuthorEntityResolver.php`, `AuthorNameNormalizer.php`, `CanonicalWork.php`, `LiteratureSourceMapping.php`, `CanonicalWorkIdentifier.php`, related migrations, and existing semantic/projection/sync feature tests. No production algorithm is modified by this framework. File hashes at export and run identify the exact implementation evaluated; preserve the corresponding source revision.

## Actual work-resolution decisions

`SemanticLiteratureResolver::resolve()` runs a transaction and loads the source/ordered authors. A previously existing mapping is enriched/refreshed, **not reclassified**; replay must therefore start without mappings.

`LiteratureIdentityNormalizer` emits a provider-scoped source external ID, optional Google KG identity, and optional catalog identifier. A checksum-valid ISBN-10/13 is normalized to its compact string; ISBN-10 is NOT converted to ISBN-13. Invalid numeric ISBN-like values remain provider-scoped catalog identifiers. Namespaced identifiers retain their scheme. Distinct identifiers are queried against `canonical_work_identifiers`.

- Exactly one identifier candidate of the same type: reuse it (`external_identifier`).
- Multiple identifier candidates or conflicting type: create a SEPARATE work (`identifier_conflict_separate`, confidence 0.25), without entering composite matching.
- No identifier candidate: use exact normalized raw `title` + type, then frozen primary author identity and publication year.
- One same-author/year candidate: reuse (`title_author_year`, 0.92; missing incoming year 0.86).
- One same-author candidate with a missing year, or novel edition differing by year: reuse (`title_author` 0.86 / `title_author_edition` 0.90).
- Existing title candidates without sufficient unique author/year evidence: create a SEPARATE work (`ambiguous_title_separate`, 0.45).
- Otherwise create a new work (`created`, 1.0).

`mapping_status=matched` appears on both successful merges and deliberately separate conflict/ambiguous records. Confidence is a rule constant, not a calibrated probability. Pairwise predicted SAME is exclusively equality of the two actual non-null canonical work IDs in the same replay.

Canonical enrichment fills missing primary author/year, so earlier records can affect later decisions. The method is rule-based identity resolution; it does not perform embedding similarity or general semantic understanding. Alternate/native title data is frozen for inspection but the composite rule normalizes the raw main title, not arbitrary alternate titles.

## Projector and source data

`CanonicalLiteratureProjector` scores source priority, authors, identifiers, metadata, cover quality, categories, edition wording, and mapping confidence to pick a preferred record. It may copy missing cover/backdrop fields and update provenance. The evaluator executes this real class, not a mock. It uses canonical identity, not preferred literature ID, to decide same-work.

`CatalogSyncService` acquires provider metadata and optional Knowledge Graph enrichment, resolves canonical authors, stores source records/categories/relations, calls the work resolver and availability sync. It can reuse an existing same-provider record by external ID, identifier, or title/year/author. Some source distinctions may already have been collapsed. The framework never calls sync/API clients and cannot reconstruct discarded raw payloads. Stored literature metadata can already contain inherited/enriched fields; curated display overrides are not applied to resolver input.

## Relations and isolation

`literature_source_mappings.literature_id` is unique; mappings link a source literature record to a canonical work and provider/external ID. Canonical identifiers have unique scheme/value identities. Canonical works reference primary author and preferred literature. Authors are ordered by `author_literature.position`; author and category relations are frozen for replay. Source stable IDs are provider key + exact external ID; local literature IDs are retained only as replay identity/tie-breaker inputs. No work mappings, canonical identifiers, manual work overrides, user interactions or user accounts are copied into the snapshot.

Actual migrations initialize a new SQLite memory database for every run/order. The runner seeds frozen source records, authors/categories/pivots, then calls the application's real resolver in the recorded order. The default connection is restored even on failure. No persistent database is cleared or migrated. Raw numeric canonical IDs are order-local; cluster hashes allow meaningful assignment comparison.

The existing semantic tests cover shared ISBN, normalized-title/author/year, novel editions, different authors, missing authors, scoped Comic Vine identifiers, and idempotent backfill; projection/sync tests provide adjacent regressions. These are correctness fixtures, never empirical research labels.

## Known demo exclusion and provenance limits

`CatalogSeeder` contains manually written examples attributed to real providers. The exporter conservatively excludes its five known non-manual identities: AniList 30012/5114/30035, Comic Vine 4050-1807, Google Books isbn-9780261102217. This applies even if a demo record was later refreshed. Manual-curated records are already excluded by provider policy. Provider attribution in the local database is not a cryptographically proven import log; original API payloads are unavailable. Verify identities and evidence links manually; do not describe the export as independently remote-validated API responses. The manifest records the exclusion list and the seeder file hash.
