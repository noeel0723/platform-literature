<?php

namespace App\Console\Commands;

use App\Models\Literature;
use App\Models\LiteratureSourceMapping;
use App\Services\Literature\SemanticLiteratureResolver;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('catalog:map-existing
    {--chunk=200 : Number of literature records processed per database chunk}
    {--refresh : Refresh identifiers and provenance for records that are already mapped}')]
#[Description('Build canonical-work mappings for literature records that have not been mapped yet')]
class MapExistingLiterature extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SemanticLiteratureResolver $resolver): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $query = Literature::query()
            ->when(! $this->option('refresh'), fn ($query) => $query->whereDoesntHave('sourceMapping'));
        $total = (clone $query)->count();

        if ($total === 0) {
            $this->components->info('All literature records already have a canonical mapping. Use --refresh to rebuild identifier metadata.');

            return self::SUCCESS;
        }

        $progress = $this->output->createProgressBar($total);
        $progress->start();

        $query
            ->with(['apiSource', 'authors'])
            ->chunkById($chunkSize, function ($literatures) use ($resolver, $progress): void {
                foreach ($literatures as $literature) {
                    $resolver->resolve($literature);
                    $progress->advance();
                }
            });

        $progress->finish();
        $this->newLine(2);
        $action = $this->option('refresh') ? 'Refreshed' : 'Mapped';
        $this->components->info("{$action} {$total} literature records.");

        $reviewCount = LiteratureSourceMapping::query()->needsReview()->count();

        if ($reviewCount > 0) {
            $this->components->warn("{$reviewCount} mappings need manual review; no automatic merge was performed for them.");
        }

        return self::SUCCESS;
    }
}
