<?php

namespace App\Console\Commands;

use App\Exceptions\LiteratureSourceUnavailable;
use App\Services\Literature\CatalogSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('literahaven:backfill-kitsu-genres {--limit=500 : Maximum records to inspect}')]
#[Description('Enrich Kitsu manga and manhwa records that do not have genre metadata')]
class BackfillKitsuGenres extends Command
{
    public function handle(CatalogSyncService $catalogSync): int
    {
        $limit = max(1, min((int) $this->option('limit'), 500));

        try {
            $result = $catalogSync->backfillKitsuGenres($limit);
        } catch (LiteratureSourceUnavailable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Inspected {$result['scanned']} Kitsu records; enriched {$result['enriched']}.");

        return self::SUCCESS;
    }
}
