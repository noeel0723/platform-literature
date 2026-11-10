# Literahaven manual entity-resolution study

This is a fresh manual-labeling study, not a restoration of previous datasets. No historical labels, predictions, proxy references, or exploratory scores were imported. Only four framework code classes from commit `6fca355` were used as architectural references and revised. The production resolver is unchanged.

## Reproduce and launch (PowerShell)

From `D:\laragon\www\literahaven`, with PHP 8.5 available on PATH:

```powershell
php artisan research:export --dataset=manual-study-v1 --pairs=180 --subset=80 --seed=manual-study-v1
npm run build
php artisan research:label --dataset=manual-study-v1 --port=8765
```

Open **http://127.0.0.1:8765**. Keep the command running. Stop it with Ctrl+C. Restart with the same command to recover server-side progress. If PHP is not on PATH, use `& 'D:/laragon/bin/php/php-8.5.10-Win32-vs17-x64/php.exe' artisan ...`.

Existing export directories are never overwritten. To generate another dataset, use a new dataset name, e.g. `manual-study-v2`. Same seed + same frozen inputs yield the same candidate identities and initial subset. A changed live catalog can produce a different snapshot despite the same seed.

The interface is a separate React/Tailwind entrypoint served by PHP on **127.0.0.1 only**. It is not an authenticated Literahaven page, not linked from the main UI, and has no main-site routes. Runtime environment checks reject production. It does not use main-site sessions or write user/catalog tables. Do not expose this server with a tunnel or reverse proxy. The usual production web root must remain `public/`.

## Immutable files and working files

`exports/<dataset>/` contains:

- `snapshot.json`: eligible provider-attributed, post-ingestion local catalog records. Includes frozen local author identities for replay; not raw API responses.
- `candidate_pairs.csv`: complete original candidate pool, every label initially `UNLABELED`.
- `selected_pairs.csv`: deterministic initial evaluation subset, frozen before predictions.
- `export_manifest.json`: seed, counts, provider/stratum coverage, checksums, UTC export time, commit SHA, dirty-worktree flag and source-code hashes.
- `progress.json`: separate working decisions and append-only decision audit within an atomic JSON file; written only after manual input.
- `.progress.lock`: serializes progress writes. Revision checking rejects stale browser tabs.
- `labels.csv`: exported working decisions, with every source row/column/order preserved. Can be regenerated from progress. Original files are never overwritten.

Generated exports/outputs are Git-ignored. Back up the entire study directory yourself before collecting real labels; keep its manifest and snapshot together. Do not commit private information or credentials. CSV is UTF-8, quoted correctly for commas, quotes, Unicode and multiline fields. Import identifier columns **as text** in spreadsheet software to retain leading zeros; do not let it evaluate CSV cells as formulas. The browser/evaluator treat values as strings and escape displayed content.

## Interface controls

- **Scope** defaults to the frozen initial subset (target 80); choose All candidates to examine the pool (target 180).
- **Search pairs** searches only title, original title, author, provider and pair ID. **Filter unlabeled** restricts to UNLABELED rows. Filters do not change data.
- **Open source A / B** opens the provider evidence/discovery link in a new tab. Hardcover links are provider searches, not verified identity evidence. Find the actual record and copy its bibliographic evidence URL yourself. Covers load only when the browser opens the interface; server-side export/replay makes no provider API requests.
- **SAME WORK / DIFFERENT WORK** records your judgment. Supply an annotator ID plus a valid HTTP(S) evidence URL or a substantive rationale of at least 30 characters. Check the personal-verification attestation. These requirements document your judgment; they cannot prove an actual human assessment took place.
- **UNCERTAIN** records that evidence is insufficient. Explain why in notes. It is never promoted to a definite label automatically.
- **Clear label** returns to UNLABELED and preserves notes/evidence; it clears definitive verification attribution.
- **Previous / Next** save valid pending input before moving. Navigation is blocked when a pending definitive judgment is undocumented.
- **Skip** moves without changing the saved label. If there are pending edits, explicitly confirm keeping them in browser recovery.
- **Save progress** forces a save. Valid edits also autosave after 800 ms. Invalid incomplete decisions stay only in browser recovery; browser-close warnings protect unsaved edits. The status line reports saved, saving, or pending work.
- **Restore browser draft / Discard browser draft** appears when this browser has unsaved manual edits. Recovery is keyed by snapshot checksum and pair ID. Never import a prior study's drafts or labels.
- **Export labels CSV** saves valid pending work, downloads labels.csv, and writes a compatible copy in the study directory. If there are contradictory manual SAME/DIFFERENT relations, adjudicate them before exporting/evaluating.

All controls use native keyboard focus and Enter/Space activation. Progress is definitive labeled / total candidate pairs; uncertain counts are separate and never count as verified. Search/filter counters refer to the current view. A second browser tab with an outdated revision cannot overwrite recent work; reload and recover its manual draft deliberately.

## Manual bibliographic labeling protocol

Evaluation unit: **the same bibliographic work**, not merely an identical cover, API listing, edition, ISBN, or title string. Before labeling, verify both source records using their original links and reliable bibliographic evidence.

- SAME_WORK: evidence establishes the same underlying work, author/creator and scope. Different editions or translations may represent the same work; document that relationship. The same ISBN alone is not sufficient when other evidence conflicts.
- DIFFERENT_WORK: evidence identifies distinct works, unrelated same-title publications, a different creator/work, a derivative study guide, adaptation, sequel, or different scope (e.g. series vs individual volume). Do not use title dissimilarity alone.
- UNCERTAIN: insufficient original identity evidence, unclear series/volume boundaries, unreliable authorship, provider attribution doubts, or contradictory bibliographic evidence. Record the ambiguity instead of guessing.
- UNLABELED: not yet assessed or intentionally cleared.

For each definite decision record what was compared, relevant authors, publication/edition details and scope, source URLs, access date if relevant, and the basis for the conclusion. Use the notes for additional evidence URLs. No baseline predictions, resolver outputs, canonical work IDs, confidence values or sampling-stratum hints are shown during labeling. Do not consult evaluation results while completing this study's labels. A second independent assessor and adjudication are recommended for ambiguous cases; do not invent an assessor or claim inter-rater agreement without collecting it.

## Evaluate after manual labeling

Export labels.csv from the interface, then:

```powershell
php artisan research:evaluate --dataset=manual-study-v1 --run=manual-initial-v1 --scope=selected
```

This command runs **both baselines and the production resolver** on the identical scope. It refuses metrics if no documented manual definitive labels exist. UNLABELED and UNCERTAIN pairs are excluded, and the excluded count is reported. Partial runs are explicitly marked partial and must not be described as completed-dataset results. Complete the preselected subset before primary reporting. `--scope=all` is a separate, explicitly declared full-pool analysis, not a post-hoc replacement for a poor initial score. New runs require new output names.

The original resolver runs three times, with an empty ephemeral SQLite database migrated afresh each time: stable source identity (predeclared primary), reverse, seeded hash ordering. **Every eligible frozen snapshot record** is replayed, not only selected pair endpoints, preserving intervening-record effects. The configured MySQL connection is never migrated, seeded, deleted or used for replay writes. HTTP stray requests are blocked during replay. Source mappings are recreated and the prediction is SAME only when the two endpoints actually share a canonical identity in that replay. `mapping_status=matched` is not a prediction.

Outputs: `outputs/<run>/metrics.csv/json`, `predictions.csv/json`, `error_analysis.csv/json`, `confusion_matrix.csv/json`, `run_manifest.json`, `order_sensitivity.json`, and SVG charts for precision, recall, F1, and stable-order confusion matrices per method. All three ordering results remain in CSV/JSON; charts show the predeclared stable primary order, never the best order selected afterwards.

Definitions: accuracy=(TP+TN)/N; precision=TP/(TP+FP); recall=TP/(TP+FN); F1=2TP/(2TP+FP+FN); false_merge_rate=FP/(TP+FP), i.e. proportion of predicted merges that are false, **not** FP/(FP+TN). Zero denominators produce null/empty CSV fields and chart N/A. False merges are FP rows in error_analysis; examine their source evidence rather than editing labels to fit predictions.

## Baselines and study limitations

1. Exact normalized title: existing production LiteratureIdentityNormalizer (HTML decode/strip, Unicode lowercase, whitespace collapse, punctuation converted to spaces), nonempty normalized title equality. No fuzzy matching, author, type, identifier or year criterion.
2. Exact title + canonical author: same title criterion plus equality of the **frozen local primary author ID**; both must have an author. This is an upstream canonical-author input, not author truth inferred from the proposed work resolver. Record that upstream author resolution/enrichment may contain errors. It does not attempt a new author-resolution algorithm.
3. Proposed: unmodified SemanticLiteratureResolver plus its actual projector, identifiers, author/type/year rules. Full source fingerprints and algorithm commit SHA are recorded for each export/run. Never pool runs across changed implementations without reporting versions.

Sampling uses local title/author-name/identifier blocks, candidate condition strata, seeded hash ordering and cross-provider controls. Block cap=50 and per-stratum pool limits bound memory/work; truncated block counts are recorded. Candidate sampling uses no canonical work IDs, mapping states, confidence, label, or predictions. Shared author sampling is based on normalized stored names, not work clusters. The initial subset is balanced round-robin across metadata strata and provider combinations and hash-ranked within buckets. These are challenge strata, **not ground-truth classes**. The sample is not uniformly random over the entire catalog; precision/recall depend on selected pair distribution. Random controls reduce but do not remove blocking bias. A missing/zero-count stratum reflects data availability, not a fabricated example.

Known CatalogSeeder provider identities are conservatively excluded even if refreshed. Obvious test/mock/demo IDs and IDs not matching provider syntax are excluded. Syntax and local provider attribution do not independently prove remote identity. Provider-specific original responses are not retained as raw archival payloads in these tables. Snapshot titles/authors/years and images may have been normalized, merged, manually curated, or enriched previously. No local data are corrected or relabeled by AI during this study. Manual verification must evaluate doubtful provenance. SQLite collation and single-process replay do not test MySQL concurrency. Pairwise metrics on sampled pairs are not full-cluster quality metrics, and author-identity correctness is not independently measured.

## Tests

```powershell
php artisan test --compact tests/Feature/Research/ManualStudyTest.php tests/Unit/Research/ResolutionMetricsTest.php
node --test tests/js/research-labeling.test.mjs
npm run build
```

All definitive labels in automated tests are explicitly **synthetic test fixtures**, restricted to temporary testing files/SQLite and never exported into the real manual study. They test software behavior, not empirical method accuracy. No empirical metrics may be reported until actual human labels are collected.
