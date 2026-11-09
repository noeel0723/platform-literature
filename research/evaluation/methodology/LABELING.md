# Manual ground-truth labeling

## Unit of identity (declare before labeling)

Label original **bibliographic work**, not provider record or physical edition. Editions/reprints/translations of the same underlying work can be SAME_WORK. A sequel, adaptation in another format, study guide, different original work with the same title, or different manga volume is DIFFERENT_WORK when evidence establishes the distinction. Series-level versus single-volume metadata is UNCERTAIN unless the represented work granularity is demonstrably identical. Do not guess from title/cover similarity.

## Procedure

1. Freeze and archive snapshot + manifest + untouched candidate CSV. Copy candidates to labels.csv using UTF-8 CSV, preserving quoted commas/newlines. Import spreadsheet columns **as text** (particularly ISBN/external IDs); do not execute formulas in imported metadata. Prefer a CSV-aware editor.
2. Annotators receive only candidate metadata and source evidence, NOT predictions, match rules, work canonical IDs or mapping statuses. Structural sampling strata are not positive/negative labels.
3. Open both provider pages when a saved link exists. Confirm author identity, original title, language, first-publication versus edition year, ISBN edition, series/volume, and adaptation status using reliable publisher/author/library evidence if provider records are insufficient. Missing links remain blank; do not fabricate URLs.
4. Enter SAME_WORK or DIFFERENT_WORK only after independent verification. Otherwise use UNCERTAIN (or leave UNLABELED). Keep `evidence_url_a/b` as exported trace links; put additional verification links in `evidence_url` and a concise rationale in `labeling_notes`, with `annotator_id`.
5. Prefer two independent annotators with separate CSV copies; adjudicate disagreements into one final labels.csv. Retain both original files and an adjudication log. Report the number of annotators, label dates, agreement/disagreement/uncertain counts and adjudication procedure. This framework does not invent a second annotator or agreement statistic.
6. Freeze labels before inspecting scores. Do not relabel solely because the method disagrees; justified evidence corrections require a versioned dataset and a documented audit trail.

SAME_WORK/DIFFERENT_WORK require an annotator plus evidence or rationale. Only definitive labels enter metrics; all methods use exactly the same subset. Missing/uncertain labels and coverage are reported. Duplicate pairs, contradictory transitive manual labels, unknown label tokens and modified title/author/year/type/identifier cells are rejected. This consistency check detects conflicts; it does not generate inferred labels.

Candidate strata intentionally include title variants, shared identifiers, equal titles with distinct authors, shared authors/different titles, incomplete metadata, and hash-selected cross-source controls. Any stratum may contain either truth class. If manual review reveals insufficient hard negatives or positives, document the shortage and predeclare an additional independently sampled export; do not synthesize real-data labels or select by observed error outcomes.
