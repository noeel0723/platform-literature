<?php

namespace App\Console\Commands;

use App\Services\Research\CandidatePairExporter;
use Illuminate\Console\Command;
use Throwable;

class ExportResolutionCandidates extends Command
{
    protected $signature = 'research:export-candidates
        {--out= : New output directory, required}
        {--pairs=180 : Target number of cross-source unlabeled pairs}
        {--seed=literahaven-v1 : Reproducible sampling seed}';

    protected $description = 'Read the local catalog and export a frozen snapshot with UNLABELED candidate pairs';

    public function handle(CandidatePairExporter $exporter): int
    {
        if (! app()->environment(['local', 'development', 'testing'])) {
            $this->error('Research export is restricted to local/development/testing.');

            return self::FAILURE;
        }
        if (! $this->option('out') || ! ctype_digit((string) $this->option('pairs'))) {
            $this->error('Supply --out as a NEW directory and --pairs as an integer.');

            return self::FAILURE;
        }
        try {
            $manifest = $exporter->export($this->option('out'), (int) $this->option('pairs'), $this->option('seed'));
            $this->info("Exported {$manifest['exported_pairs']} UNLABELED pairs from {$manifest['snapshot_records']} local source records. No accuracy has been measured.");
            if ($manifest['exported_pairs'] < $manifest['requested_pairs']) {
                $this->warn('Insufficient eligible cross-source candidates for the requested target; no synthetic pairs were added.');
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
