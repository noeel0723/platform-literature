# Literahaven

Literahaven is a web-based social cataloging and literature discovery platform for novels, manga, manhwa, and western comics. It integrates multiple external literature sources into a canonical local catalog using rule-based semantic entity resolution.

The project focuses on:

- multi-source literature catalog integration;
- semantic entity resolution and canonical literature representation;
- literature discovery and relationship exploration;
- social cataloging, reading tracking, reviews, and discussions;
- explainable personalized recommendations.

## Technology Stack

| Layer | Technology |
| --- | --- |
| Backend | Laravel 13, PHP 8.3+ |
| Frontend | React 19, Inertia.js 3, Tailwind CSS 4, Vite 8 |
| Database | MySQL |
| Local development | Laragon |
| Testing | PHPUnit 12 |

## Active Data Sources

| Literature | Providers |
| --- | --- |
| Novels | Hardcover, Google Books |
| Manga and manhwa | AniList, with Kitsu as fallback |
| Western comics | Comic Vine |
| Metadata enrichment | Google Knowledge Graph |

Provider responses are converted into a shared normalized representation before being synchronized into the internal catalog. Provider-specific source records remain stored for provenance.

## Catalog Architecture

```text
External APIs
     ↓
Provider Adapters
     ↓
Normalized Literature
     ↓
Catalog Sync
     ↓
Semantic Entity Resolution
     ↓
Canonical Work
     ↓
Canonical Literature Projection
     ↓
Literahaven
```

Multiple records from different providers can be mapped to one `CanonicalWork`. Literahaven selects a preferred representative for browsing and user interactions while retaining every mapped source record and its provenance.

The main implementation is located in:

- `app/Services/Literature/SemanticLiteratureResolver.php`
- `app/Services/Literature/LiteratureIdentityNormalizer.php`
- `app/Services/Literature/CanonicalLiteratureProjector.php`

Resolution rules consider strong identifiers, normalized titles, canonical authors, literature type, publication year, novel-edition tolerance, and ambiguity protection.

> The resolver is deterministic and rule-based. It does not use generative AI, embeddings, or vector similarity.

## Features

- Global Literature browsing and local canonical-aware search
- Catalog discovery through external APIs and local synchronization
- Literature details and genre browsing/filtering
- Relationship Explorer and More by these authors
- Canonical-aware Where to Read links
- Readlist, reading status, completion tracking, diary, and activity
- Reviews, ratings, discussions, and comments
- Custom lists and favorite literature/authors
- Reader profiles, profile statistics, follows, and social activity
- Personalized and explainable recommendations
- Community reporting and admin moderation
- Admin metadata overrides

## Recommendations

Recommendations use a hybrid rule-based approach that combines genre preference, author preference, literature type, local popularity, similar-reader similarity, and diversity reranking. Results include a human-readable reason so users can understand why a work was recommended.

## Where to Read

Availability is stored against `CanonicalWork`, allowing mapped source siblings to share the same official links. Links can come from Google Books or Google Play Books, supported official AniList external links, and curated official entries.

Existing catalog records can be enriched without making provider requests from the detail page:

```bash
php artisan catalog:backfill-where-to-read
```

Use `--limit`, `--type`, or `--refresh` when a narrower or refreshed backfill is needed.

## Local Development

The following setup targets Windows with Laragon, PHP, Composer, Node.js, npm, and MySQL available:

```bash
git clone https://github.com/noeel0723/platform-literature.git
cd platform-literature

composer install
npm install

copy .env.example .env
php artisan key:generate
```

Create a MySQL database, then update the `DB_*` values in `.env`. Run the database migrations and start the frontend development server:

```bash
php artisan migrate
php artisan storage:link
npm run dev
```

Serve the Laravel application through Laragon, or use the local server workflow configured for your environment.

For a production frontend bundle:

```bash
npm run build
```

### Environment Configuration

Use `.env.example` as the configuration reference. Relevant groups include:

```dotenv
DB_CONNECTION=
DB_HOST=
DB_PORT=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

GOOGLE_BOOKS_API_KEY=
HARDCOVER_API_TOKEN=
GOOGLE_KNOWLEDGE_GRAPH_API_KEY=
ANILIST_BASE_URL=
KITSU_BASE_URL=
COMIC_VINE_API_KEY=
```

Timeouts, cache durations, result limits, and provider base URLs are also configurable in `.env.example`. Never place real credentials in documentation or source control.

## Catalog Maintenance

```bash
# Map unmapped local records to canonical works
php artisan catalog:map-existing

# Re-evaluate identifiers and provenance for existing mappings
php artisan catalog:map-existing --refresh

# Report legacy duplicate user interactions (non-destructive by default)
php artisan catalog:audit-user-interactions

# Backfill canonical Where to Read availability
php artisan catalog:backfill-where-to-read
```

Additional provider-specific backfill commands can be inspected with `php artisan list` and should only be run when their corresponding local metadata needs enrichment.

## Testing

Run the full automated suite with:

```bash
php artisan test
```

Focused semantic mapping tests:

```bash
php artisan test --compact tests/Feature/Services/Literature/SemanticLiteratureResolverTest.php
php artisan test --compact tests/Feature/Services/Literature/CanonicalLiteratureProjectionTest.php
```

Automated tests use the configured testing database. Tests that cover provider integrations must fake or mock external HTTP calls; they must not depend on live provider availability or real API credentials.

## Project Structure

```text
app/Services/Literature/       Catalog normalization, mapping, and projection
app/Services/Recommendations/ Recommendation generation and ranking
app/Http/Controllers/         Web and API request handling
resources/js/                 React/Inertia pages and components
tests/Feature/                Application and integration behavior tests
docs/                         Architecture and project notes
```

## Academic Context

Literahaven was developed as an undergraduate final project focused on web-based cross-format literature social cataloging, multi-API integration, literature discovery, and rule-based semantic entity resolution.

## Repository Safety

- Never commit `.env`.
- Never commit API keys, access tokens, or other credentials.
- Keep safe configuration placeholders in `.env.example`.
