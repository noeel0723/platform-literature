<?php

namespace App\Console\Commands;

use App\Services\Research\StudyDataset;
use App\Services\Research\StudyFiles;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class ServeManualLabeling extends Command
{
    protected $signature = 'research:label {--dataset=manual-study-v1} {--port=8765}';

    protected $description = 'Start a dedicated manual-labeling server bound strictly to 127.0.0.1 (not the website).';

    public function handle(StudyDataset $datasets): int
    {
        try {
            StudyFiles::localOnly();
            $datasets->load($this->option('dataset'));
            $port = filter_var($this->option('port'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1024, 'max_range' => 65535]]);
            if ($port === false) {
                throw new \InvalidArgumentException('Port must be 1024–65535.');
            }
            if (! is_file(public_path('build/manifest.json'))) {
                throw new \RuntimeException('Run npm run build first.');
            }
            $this->info('Open http://127.0.0.1:'.$port.' — Ctrl+C to stop. Progress is stored separately from immutable source files.');
            $process = new Process([PHP_BINARY, '-S', '127.0.0.1:'.$port, base_path('research/evaluation/server.php')], base_path(), [
                'RESEARCH_DATASET' => $this->option('dataset'), 'RESEARCH_LOCAL_TOKEN' => bin2hex(random_bytes(32)),
            ]);
            $process->setTimeout(null);

            return $process->run(fn ($type, $text) => $this->output->write($text));
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
