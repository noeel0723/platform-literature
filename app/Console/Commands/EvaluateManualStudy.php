<?php

namespace App\Console\Commands;

use App\Services\Research\ResolutionEvaluationRunner;
use Illuminate\Console\Command;

class EvaluateManualStudy extends Command
{
    protected $signature = 'research:evaluate {--dataset=manual-study-v1} {--run=} {--scope=selected}';

    protected $description = 'Compare two baselines and original resolver using documented manual labels in an ephemeral SQLite replay.';

    public function handle(ResolutionEvaluationRunner $runner): int
    {
        try {
            $run = $this->option('run') ?: 'manual-'.now()->format('Ymd-His');
            $this->line(json_encode($runner->run($this->option('dataset'), $run, $this->option('scope')), JSON_PRETTY_PRINT));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
