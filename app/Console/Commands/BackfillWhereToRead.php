<?php

namespace App\Console\Commands;

use App\Models\CanonicalWork;
use App\Models\Literature;
use App\Services\Literature\CanonicalWorkAvailabilityBackfillService;
use App\Services\Literature\CanonicalWorkAvailabilityService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('catalog:backfill-where-to-read
    {--limit= : Maximum canonical works to inspect}
    {--type= : Restrict to novel, manga, manhwa, or western-comic}
    {--refresh : Re-fetch works that already have verified availability}')]
#[Description('Backfill official Where to Read links for existing canonical literature')]
class BackfillWhereToRead extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(
        CanonicalWorkAvailabilityBackfillService $backfill,
        CanonicalWorkAvailabilityService $availability,
    ): int {
        $limit = $this->option('limit') === null
            ? 500
            : (int) $this->option('limit');
        $type = trim((string) $this->option('type'));

        if ($limit < 1) {
            $this->error('The --limit option must be at least 1.');

            return self::INVALID;
        }

        if ($type !== '' && ! in_array($type, Literature::supportedTypes(), true)) {
            $this->error('The --type option must be one of: '.implode(', ', Literature::supportedTypes()).'.');

            return self::INVALID;
        }

        $counts = [
            'scanned' => 0,
            'enriched' => 0,
            'skipped_existing' => 0,
            'no_availability' => 0,
            'ambiguous' => 0,
            'failed' => 0,
            'links_synced' => 0,
        ];
        $query = CanonicalWork::query()
            ->with([
                'identifiers',
                'links',
                'primaryAuthor',
                'sourceMappings.apiSource',
                'sourceMappings.literature.apiSource',
                'sourceMappings.literature.authors',
            ])
            ->when($type !== '', fn ($builder) => $builder->where('type', $type))
            ->orderBy('id');

        $query->chunkById(min(50, $limit), function ($canonicalWorks) use (
            $limit,
            $backfill,
            $availability,
            &$counts,
        ): bool {
            foreach ($canonicalWorks as $canonicalWork) {
                if ($counts['scanned'] >= $limit) {
                    return false;
                }

                $counts['scanned']++;

                if (! $this->option('refresh') && $availability->hasVerifiedLinks($canonicalWork)) {
                    $counts['skipped_existing']++;

                    continue;
                }

                try {
                    $result = $backfill->enrich($canonicalWork);
                    $counts[$result['status']]++;
                    $counts['links_synced'] += $result['links_synced'];
                } catch (Throwable $exception) {
                    $counts['failed']++;
                    $this->warn("Canonical work {$canonicalWork->id} failed: {$exception->getMessage()}");
                }
            }

            return $counts['scanned'] < $limit;
        });

        $this->newLine();
        $this->table(['Result', 'Count'], [
            ['Scanned', $counts['scanned']],
            ['Enriched', $counts['enriched']],
            ['Skipped existing', $counts['skipped_existing']],
            ['No availability', $counts['no_availability']],
            ['Ambiguous', $counts['ambiguous']],
            ['Failed', $counts['failed']],
            ['Links created/updated', $counts['links_synced']],
        ]);

        return self::SUCCESS;
    }
}
