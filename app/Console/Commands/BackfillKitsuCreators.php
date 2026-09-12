<?php

namespace App\Console\Commands;

use App\Services\Literature\CatalogSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('literahaven:backfill-kitsu-creators {--limit=100 : Maximum records to inspect}')]
#[Description('Enrich Kitsu manga and manhwa records that do not have author or artist credits')]
class BackfillKitsuCreators extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CatalogSyncService $catalogSync): int
    {
        $limit = max(1, min((int) $this->option('limit'), 100));
        $result = $catalogSync->backfillKitsuCreators($limit);

        $this->info("Inspected {$result['scanned']} Kitsu records; enriched {$result['enriched']}.");

        return self::SUCCESS;
    }
}
