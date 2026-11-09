# Independent baselines

Implementation: `app/Services/Research/ResolutionBaselines.php` (PSR-4 application autoload; no copied resolver or dependency). Both consume the same frozen records as the proposed method.

1. **Exact Normalized Title Matching**: nonempty `normalizeTitle(raw title A) == normalizeTitle(raw title B)`.
2. **Exact Normalized Title + Canonical Author Matching**: baseline 1 AND both primary authors exist AND their frozen local author IDs are equal. Primary author is the first `authors` relation member (pivot position order), just as in the proposed resolver. Missing authors never count as equal. Equal names with different author IDs do not count as the same canonical author.

Title normalization calls the production `LiteratureIdentityNormalizer::normalizeTitle`: strip HTML, decode entities, lowercase, squish whitespace, replace non-Unicode-letter/non-digit sequences with spaces, then squish. There is no translation, transliteration, fuzzy threshold, stemming, stopword removal, alternate-title fallback or ISBN matching. E.g. `The Silver-Chair` and `THE SILVER CHAIR` agree; `Haikyu` and `Haikyuu` do not.

These deliberately simple baselines have no type/year/identifier guards and do not query work mappings, resolver outputs or labels. They share a documented normalization primitive, not resolution logic. Author IDs are existing upstream author resolution outputs shared equally by baseline 2 and proposed; the study does **not** independently validate `AuthorEntityResolver`.
