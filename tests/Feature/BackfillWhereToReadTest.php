<?php

namespace Tests\Feature;

use App\Models\ApiSource;
use App\Models\Author;
use App\Models\CanonicalWork;
use App\Models\CanonicalWorkLink;
use App\Models\Literature;
use App\Models\LiteratureSourceMapping;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BackfillWhereToReadTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'services.google_books.base_url' => 'https://books-api.test',
            'services.google_books.key' => 'test-key',
            'services.google_books.connect_timeout' => 1,
            'services.google_books.timeout' => 2,
            'services.anilist.base_url' => 'https://anilist-api.test',
            'services.anilist.connect_timeout' => 1,
            'services.anilist.timeout' => 2,
        ]);
    }

    public function test_existing_novel_is_backfilled_and_isbn_13_is_looked_up_first(): void
    {
        $work = $this->canonicalWork('Dune', 'novel', 'hardcover', 'hardcover-1', '9780441172719');
        Http::preventStrayRequests();
        Http::fake([
            'https://books-api.test/volumes*' => Http::response([
                'items' => [$this->googleVolume('Dune', 'Frank Herbert', '9780441172719')],
            ]),
        ]);

        $this->artisan('catalog:backfill-where-to-read', ['--limit' => 1])
            ->assertSuccessful();

        $this->assertDatabaseHas('canonical_work_links', [
            'canonical_work_id' => $work->id,
            'provider' => 'Google Play Books',
            'link_type' => 'buy',
            'is_official' => true,
            'is_active' => true,
        ]);
        Http::assertSent(fn (Request $request): bool => $request['q'] === 'isbn:9780441172719');
        Http::assertSentCount(1);
    }

    public function test_ambiguous_title_and_author_fallback_is_not_saved(): void
    {
        $work = $this->canonicalWork('Dune', 'novel', 'hardcover', 'hardcover-2', null);
        Http::preventStrayRequests();
        Http::fake([
            'https://books-api.test/volumes*' => Http::response([
                'items' => [
                    $this->googleVolume('Dune', 'Frank Herbert', null, 'edition-a'),
                    $this->googleVolume('Dune', 'Frank Herbert', null, 'edition-b'),
                ],
            ]),
        ]);

        $this->artisan('catalog:backfill-where-to-read', ['--limit' => 1])
            ->expectsOutputToContain('Ambiguous')
            ->assertSuccessful();

        $this->assertDatabaseMissing('canonical_work_links', ['canonical_work_id' => $work->id]);
        Http::assertSent(fn (Request $request): bool => str_contains((string) $request['q'], 'intitle:"Dune"')
            && str_contains((string) $request['q'], 'inauthor:"Frank Herbert"'));
    }

    public function test_existing_anilist_manga_is_backfilled_by_media_id_and_unsupported_links_are_ignored(): void
    {
        $work = $this->canonicalWork('Naruto', 'manga', 'anilist', '30013', 'ANILIST:30013');
        Http::preventStrayRequests();
        Http::fake([
            'https://anilist-api.test*' => Http::response([
                'data' => ['Media' => $this->aniListMedia('Naruto', 'JP', [
                    ['site' => 'MANGA Plus', 'url' => 'https://mangaplus.shueisha.co.jp/title/100018', 'type' => 'STREAMING'],
                    ['site' => 'Twitter', 'url' => 'https://x.com/naruto', 'type' => 'SOCIAL'],
                    ['site' => 'MyAnimeList', 'url' => 'https://myanimelist.net/manga/13', 'type' => 'INFO'],
                ])],
            ]),
        ]);

        $this->artisan('catalog:backfill-where-to-read', ['--type' => 'manga'])
            ->assertSuccessful();

        $this->assertDatabaseHas('canonical_work_links', [
            'canonical_work_id' => $work->id,
            'provider' => 'MANGA Plus',
            'link_type' => 'read',
        ]);
        $this->assertDatabaseCount('canonical_work_links', 1);
        Http::assertSent(fn (Request $request): bool => $request->data()['variables']['id'] === 30013
            && str_contains($request->data()['query'], 'LiteratureById'));
    }

    public function test_existing_anilist_manhwa_can_store_webtoon_link(): void
    {
        $work = $this->canonicalWork('Tower of God', 'manhwa', 'anilist', '85143', 'ANILIST:85143');
        Http::preventStrayRequests();
        Http::fake([
            'https://anilist-api.test*' => Http::response([
                'data' => ['Media' => $this->aniListMedia('Tower of God', 'KR', [
                    ['site' => 'WEBTOON', 'url' => 'https://www.webtoons.com/en/fantasy/tower-of-god/list', 'type' => 'STREAMING'],
                ])],
            ]),
        ]);

        $this->artisan('catalog:backfill-where-to-read', ['--type' => 'manhwa'])
            ->assertSuccessful();

        $this->assertDatabaseHas('canonical_work_links', [
            'canonical_work_id' => $work->id,
            'provider' => 'WEBTOON',
            'link_type' => 'read',
        ]);
    }

    public function test_missing_provider_record_is_reported_as_no_availability_instead_of_failing(): void
    {
        $this->canonicalWork('Naruto', 'manga', 'anilist', '30013', 'ANILIST:30013');
        Http::fake([
            'https://anilist-api.test*' => Http::response(['data' => ['Media' => null]]),
        ]);

        $this->artisan('catalog:backfill-where-to-read', ['--type' => 'manga'])
            ->expectsOutputToContain('No availability')
            ->expectsOutputToContain('Failed')
            ->assertSuccessful();

        $this->assertDatabaseCount('canonical_work_links', 0);
    }

    public function test_running_backfill_twice_does_not_duplicate_links(): void
    {
        $this->canonicalWork('Naruto', 'manga', 'anilist', '30013', 'ANILIST:30013');
        Http::fake([
            'https://anilist-api.test*' => Http::response([
                'data' => ['Media' => $this->aniListMedia('Naruto', 'JP', [
                    ['site' => 'VIZ', 'url' => 'https://www.viz.com/naruto', 'type' => 'INFO'],
                ])],
            ]),
        ]);

        $this->artisan('catalog:backfill-where-to-read', ['--refresh' => true])->assertSuccessful();
        $this->artisan('catalog:backfill-where-to-read', ['--refresh' => true])->assertSuccessful();

        $this->assertDatabaseCount('canonical_work_links', 1);
    }

    public function test_verified_existing_work_is_skipped_without_refresh(): void
    {
        $work = $this->canonicalWork('Naruto', 'manga', 'anilist', '30013', 'ANILIST:30013');
        CanonicalWorkLink::query()->create([
            'canonical_work_id' => $work->id,
            'provider' => 'VIZ',
            'url' => 'https://www.viz.com/naruto',
            'link_type' => 'read',
            'source' => 'curated',
            'is_official' => true,
            'is_active' => true,
            'verified_at' => now(),
        ]);
        Http::preventStrayRequests();

        $this->artisan('catalog:backfill-where-to-read')
            ->expectsOutputToContain('Skipped existing')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_refresh_adds_api_links_without_deleting_curated_links(): void
    {
        $work = $this->canonicalWork('Naruto', 'manga', 'anilist', '30013', 'ANILIST:30013');
        CanonicalWorkLink::query()->create([
            'canonical_work_id' => $work->id,
            'provider' => 'VIZ',
            'url' => 'https://www.viz.com/curated-naruto',
            'link_type' => 'read',
            'source' => 'curated',
            'is_official' => true,
            'is_active' => true,
            'verified_at' => now()->subYear(),
        ]);
        Http::fake([
            'https://anilist-api.test*' => Http::response([
                'data' => ['Media' => $this->aniListMedia('Naruto', 'JP', [
                    ['site' => 'MANGA Plus', 'url' => 'https://mangaplus.shueisha.co.jp/title/100018', 'type' => 'STREAMING'],
                ])],
            ]),
        ]);

        $this->artisan('catalog:backfill-where-to-read', ['--refresh' => true])->assertSuccessful();

        $this->assertDatabaseHas('canonical_work_links', [
            'canonical_work_id' => $work->id,
            'url' => 'https://www.viz.com/curated-naruto',
            'source' => 'curated',
        ]);
        $this->assertDatabaseHas('canonical_work_links', [
            'canonical_work_id' => $work->id,
            'provider' => 'MANGA Plus',
            'source' => 'anilist',
        ]);
        $this->assertDatabaseCount('canonical_work_links', 2);
    }

    private function canonicalWork(
        string $title,
        string $type,
        string $sourceKey,
        string $externalId,
        ?string $identifier,
    ): CanonicalWork {
        $source = ApiSource::factory()->create([
            'key' => $sourceKey,
            'name' => $sourceKey,
            'supported_types' => [$type],
        ]);
        $author = Author::factory()->create(['name' => $this->authorFor($title)]);
        $literature = Literature::factory()->create([
            'api_source_id' => $source->id,
            'external_id' => $externalId,
            'title' => $title,
            'type' => $type,
            'publication_year' => $title === 'Dune' ? 1965 : 1999,
            'identifier' => $identifier,
        ]);
        $literature->authors()->attach($author->id, ['role' => 'author', 'position' => 0]);
        $work = CanonicalWork::factory()->create([
            'primary_author_id' => $author->id,
            'preferred_literature_id' => $literature->id,
            'canonical_title' => $title,
            'normalized_title' => strtolower($title),
            'type' => $type,
            'publication_year' => $literature->publication_year,
        ]);
        LiteratureSourceMapping::factory()->create([
            'canonical_work_id' => $work->id,
            'literature_id' => $literature->id,
            'api_source_id' => $source->id,
            'source_external_id' => $externalId,
        ]);

        return $work;
    }

    /** @return array<string, mixed> */
    private function googleVolume(
        string $title,
        string $author,
        ?string $isbn,
        string $externalId = 'google-volume-1',
    ): array {
        return [
            'id' => $externalId,
            'volumeInfo' => [
                'title' => $title,
                'authors' => [$author],
                'publisher' => 'Test Publisher',
                'publishedDate' => '1965',
                'categories' => ['Fiction'],
                'industryIdentifiers' => $isbn === null ? [] : [[
                    'type' => strlen($isbn) === 13 ? 'ISBN_13' : 'ISBN_10',
                    'identifier' => $isbn,
                ]],
                'language' => 'en',
                'printType' => 'BOOK',
                'previewLink' => "https://books.google.test/preview/{$externalId}",
            ],
            'saleInfo' => [
                'buyLink' => "https://play.google.test/books/{$externalId}",
            ],
            'accessInfo' => [
                'viewability' => 'PARTIAL',
                'webReaderLink' => "https://books.google.test/reader/{$externalId}",
            ],
        ];
    }

    /** @param list<array<string, string>> $externalLinks */
    private function aniListMedia(string $title, string $country, array $externalLinks): array
    {
        return [
            'id' => $title === 'Naruto' ? 30013 : 85143,
            'title' => ['english' => $title, 'romaji' => $title, 'native' => $title],
            'description' => 'A serialized story.',
            'startDate' => ['year' => 1999],
            'genres' => ['Action'],
            'countryOfOrigin' => $country,
            'coverImage' => ['large' => 'https://images.test/cover.jpg'],
            'format' => 'MANGA',
            'externalLinks' => $externalLinks,
            'staff' => ['edges' => [[
                'role' => 'Story & Art',
                'node' => [
                    'id' => 1,
                    'name' => ['full' => $this->authorFor($title)],
                ],
            ]]],
        ];
    }

    private function authorFor(string $title): string
    {
        return match ($title) {
            'Dune' => 'Frank Herbert',
            'Naruto' => 'Masashi Kishimoto',
            default => 'SIU',
        };
    }
}
