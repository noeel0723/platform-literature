<?php

namespace App\Console\Commands;

use App\Services\Literature\AuthorEntityConsolidator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('authors:consolidate
    {--dry-run : Report exact normalized-name duplicates without changing data}
    {--name= : Only process this author name or normalized name}')]
#[Description('Safely consolidate exact author-name variants into canonical authors')]
class ConsolidateAuthors extends Command
{
    public function handle(AuthorEntityConsolidator $consolidator): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $summary = $consolidator->consolidate($dryRun, $this->option('name'));

        $this->components->twoColumnDetail('Duplicate groups safe to consolidate', (string) $summary['groups']);
        $this->components->twoColumnDetail('Duplicate author records', (string) $summary['merged_authors']);
        $this->components->twoColumnDetail('Groups skipped because identities conflict', (string) $summary['skipped_groups']);

        if ($dryRun) {
            $this->components->warn('Dry run only. No author records were changed.');
        } else {
            $this->components->info('Author consolidation completed.');
        }

        return self::SUCCESS;
    }
}
