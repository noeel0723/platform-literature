<?php

namespace App\Console\Commands;

use App\Exceptions\LiteratureSourceUnavailable;
use App\Services\Literature\CatalogSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('literahaven:backfill-comic-creators {--limit=30 : Maximum records to inspect}')]
#[Description('Enrich Comic Vine catalog records that do not have writer or artist credits')]
class BackfillComicCreators extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CatalogSyncService $catalogSync): int
    {
        $limit = max(1, min((int) $this->option('limit'), 100));

        try {
            $result = $catalogSync->backfillComicCreators($limit);
        } catch (LiteratureSourceUnavailable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Inspected {$result['scanned']} Comic Vine records; enriched {$result['enriched']}.");

        return self::SUCCESS;
    }
}
