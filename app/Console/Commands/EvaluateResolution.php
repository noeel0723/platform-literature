<?php

namespace App\Console\Commands;

use App\Services\Research\ResolutionEvaluationRunner;
use Illuminate\Console\Command;
use Throwable;

class EvaluateResolution extends Command
{
    protected $signature = 'research:evaluate
        {--snapshot= : Path to the exported snapshot.json}
        {--labels= : Path to the manually labeled CSV}
        {--out= : NEW output directory}
        {--seed=literahaven-v1 : Replay permutation seed}
        {--predict-only : Produce diagnostics with NO evaluation scores}';

    protected $description = 'Compare both baselines and the real resolver in fresh isolated memory databases';

    public function handle(ResolutionEvaluationRunner $runner): int
    {
        if (! app()->environment(['local', 'development', 'testing'])) {
            $this->error('Research evaluation is restricted to local/development/testing.');

            return self::FAILURE;
        }
        foreach (['snapshot', 'labels', 'out'] as $option) {
            if (! $this->option($option)) {
                $this->error("Missing --{$option}");

                return self::FAILURE;
            }
        }
        try {
            $manifest = $runner->run($this->option('snapshot'), $this->option('labels'), $this->option('out'), $this->option('seed'), (bool) $this->option('predict-only'));
            $this->info($manifest['status']);
            $this->info("Pairs: {$manifest['pair_count']}; manually labeled: {$manifest['labeled_pairs']}; scored per method: {$manifest['scored_pairs_per_method']}.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
