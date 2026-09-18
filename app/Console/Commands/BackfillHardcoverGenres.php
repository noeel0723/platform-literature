<?php

namespace App\Console\Commands;

use App\Exceptions\LiteratureSourceUnavailable;
use App\Services\Literature\CatalogSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('literahaven:backfill-hardcover-genres {--limit=500 : Maximum records to inspect}')]
#[Description('Enrich Hardcover novel records that do not have genre metadata')]
class BackfillHardcoverGenres extends Command
{
    public function handle(CatalogSyncService $catalogSync): int
    {
        $limit = max(1, min((int) $this->option('limit'), 500));

        try {
            $result = $catalogSync->backfillHardcoverGenres($limit);
        } catch (LiteratureSourceUnavailable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Inspected {$result['scanned']} Hardcover records; enriched {$result['enriched']}.");

        return self::SUCCESS;
    }
}
