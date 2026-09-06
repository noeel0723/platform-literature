<?php

namespace Tests\Feature\Services\Literature;

use App\Services\Literature\WorkMetadataEnricher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorkMetadataEnricherTest extends TestCase
{
    public function test_it_resolves_an_original_title_and_attributed_summary(): void
    {
        config()->set([
            'services.work_metadata.wikidata_url' => 'https://www.wikidata.org/w/api.php',
            'services.work_metadata.wikipedia_summary_url' => 'https://{language}.wikipedia.org/api/rest_v1/page/summary/{title}',
            'services.work_metadata.user_agent' => 'LiteratureSocialDiscovery/1.0 tests',
            'services.work_metadata.cache_days' => 30,
        ]);
        Cache::flush();
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_starts_with($request->url(), 'https://www.wikidata.org/w/api.php') && $request['action'] === 'wbsearchentities') {
                return Http::response(['search' => [[
                    'id' => 'Q46758',
                    'description' => 'fantasy novel by J. K. Rowling',
                    'match' => ['text' => 'Harry Potter dan Relikui Kematian'],
                ]]]);
            }

            if (str_starts_with($request->url(), 'https://www.wikidata.org/w/api.php') && $request['action'] === 'wbgetentities') {
                return Http::response(['entities' => ['Q46758' => [
                    'descriptions' => ['id' => ['value' => 'novel fantasi karya J. K. Rowling']],
                    'claims' => ['P1476' => [[
                        'rank' => 'normal',
                        'mainsnak' => ['datavalue' => ['value' => [
                            'text' => 'Harry Potter and the Deathly Hallows',
                            'language' => 'en',
                        ]]],
                    ]]],
                    'sitelinks' => ['idwiki' => ['title' => 'Harry Potter dan Relikui Kematian']],
                ]]]);
            }

            return Http::response([
                'extract' => 'Harry menghadapi bagian terakhir dari perjalanannya melawan Voldemort.',
                'content_urls' => ['desktop' => [
                    'page' => 'https://id.wikipedia.org/wiki/Harry_Potter_dan_Relikui_Kematian',
                ]],
            ]);
        });

        $metadata = app(WorkMetadataEnricher::class)->find(
            'Harry Potter dan Relikui Kematian',
            ['J. K. Rowling'],
            'id',
        );

        $this->assertSame('Harry Potter and the Deathly Hallows', $metadata->originalTitle);
        $this->assertSame('novel fantasi karya J. K. Rowling', $metadata->tagline);
        $this->assertSame('Harry menghadapi bagian terakhir dari perjalanannya melawan Voldemort.', $metadata->synopsis);
        $this->assertSame('Wikipedia ID', $metadata->synopsisSourceName);
        $this->assertSame('https://id.wikipedia.org/wiki/Harry_Potter_dan_Relikui_Kematian', $metadata->synopsisSourceUrl);
        Http::assertSentCount(3);
    }

    public function test_it_ignores_an_exact_title_that_is_not_a_literary_work(): void
    {
        config()->set([
            'services.work_metadata.wikidata_url' => 'https://www.wikidata.org/w/api.php',
            'services.work_metadata.user_agent' => 'LiteratureSocialDiscovery/1.0 tests',
        ]);
        Cache::flush();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.wikidata.org/w/api.php*' => Http::response(['search' => [[
                'id' => 'Q1',
                'description' => 'main character of the book, film and video game series',
                'match' => ['text' => 'Example Title'],
            ]]]),
        ]);

        $metadata = app(WorkMetadataEnricher::class)->find('Example Title', ['Example Author'], 'en');

        $this->assertNull($metadata->originalTitle);
        $this->assertNull($metadata->synopsis);
        Http::assertSentCount(1);
    }

    public function test_it_keeps_structured_metadata_when_wikipedia_is_unavailable(): void
    {
        config()->set([
            'services.work_metadata.wikidata_url' => 'https://www.wikidata.org/w/api.php',
            'services.work_metadata.wikipedia_summary_url' => 'https://{language}.wikipedia.org/api/rest_v1/page/summary/{title}',
            'services.work_metadata.user_agent' => 'LiteratureSocialDiscovery/1.0 tests',
        ]);
        Cache::flush();
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_starts_with($request->url(), 'https://www.wikidata.org/w/api.php') && $request['action'] === 'wbsearchentities') {
                return Http::response(['search' => [[
                    'id' => 'Q46887',
                    'description' => 'fantasy novel by J. K. Rowling',
                    'match' => ['text' => 'Harry Potter dan Pangeran Berdarah-Campuran'],
                ]]]);
            }

            if (str_starts_with($request->url(), 'https://www.wikidata.org/w/api.php') && $request['action'] === 'wbgetentities') {
                return Http::response(['entities' => ['Q46887' => [
                    'descriptions' => ['id' => ['value' => 'novel fantasi karya J. K. Rowling']],
                    'claims' => ['P1476' => [[
                        'rank' => 'normal',
                        'mainsnak' => ['datavalue' => ['value' => ['text' => 'Harry Potter and the Half-Blood Prince']]],
                    ]]],
                    'sitelinks' => ['idwiki' => ['title' => 'Harry Potter dan Pangeran Berdarah-Campuran']],
                ]]]);
            }

            return Http::failedConnection();
        });

        $metadata = app(WorkMetadataEnricher::class)->find(
            'Harry Potter dan Pangeran Berdarah-Campuran',
            ['J. K. Rowling'],
            'id',
        );

        $this->assertSame('Harry Potter and the Half-Blood Prince', $metadata->originalTitle);
        $this->assertSame('novel fantasi karya J. K. Rowling', $metadata->tagline);
        $this->assertNull($metadata->synopsis);
    }
}
