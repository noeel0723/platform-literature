<?php

namespace App\Console\Commands;

use App\Services\Research\CandidatePairExporter;
use Illuminate\Console\Command;

class ExportManualStudy extends Command
{
    protected $signature = 'research:export {--dataset=manual-study-v1} {--pairs=180} {--subset=80} {--seed=manual-study-v1}';

    protected $description = 'Export a fresh UNLABELED cross-provider study from local catalog metadata (read-only).';

    public function handle(CandidatePairExporter $exporter): int
    {
        try {
            $result = $exporter->export($this->option('dataset'), (int) $this->option('pairs'), $this->option('seed'), (int) $this->option('subset'));
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
