<?php

namespace App\Console\Commands;

use App\Models\Literature;
use App\Services\Literature\WorkMetadataEnricher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('literature:enrich-metadata
    {--limit=100 : Maximum records to inspect}
    {--query= : Only inspect titles containing this text}
    {--delay=250 : Delay between records in milliseconds}')]
#[Description('Fill missing original titles and synopses from structured public metadata')]
class EnrichLiteratureMetadata extends Command
{
    public function handle(WorkMetadataEnricher $metadataEnricher): int
    {
        $limit = max(1, min((int) $this->option('limit'), 500));
        $query = trim((string) $this->option('query'));
        $delay = max(0, min((int) $this->option('delay'), 5000));
        $literatures = Literature::query()
            ->whereHas('apiSource', fn ($query) => $query->where('key', 'google-books'))
            ->where(function ($query): void {
                $query->whereNull('synopsis')
                    ->orWhere(function ($translated): void {
                        $translated->whereNull('original_title')
                            ->whereNotNull('language')
                            ->where('language', '!=', 'en');
                    });
            })
            ->when($query !== '', fn ($builder) => $builder->where('title', 'like', "%{$query}%"))
            ->with('authors')
            ->limit($limit)
            ->get();

        if ($literatures->isEmpty()) {
            $this->components->info('No incomplete Google Books records were found.');

            return self::SUCCESS;
        }

        $updated = 0;
        $progress = $this->output->createProgressBar($literatures->count());
        $progress->start();

        foreach ($literatures as $literature) {
            $metadata = $metadataEnricher->find(
                $literature->title,
                $literature->authors->pluck('name')->all(),
                $literature->language,
                $literature->synopsis === null,
            );

            if ($literature->original_title === null && $metadata->originalTitle !== null) {
                $literature->original_title = $metadata->originalTitle;
            }

            if ($literature->tagline === null && $metadata->tagline !== null) {
                $literature->tagline = $metadata->tagline;
            }

            if ($literature->synopsis === null && $metadata->synopsis !== null) {
                $literature->synopsis = $metadata->synopsis;
                $literature->synopsis_source_name = $metadata->synopsisSourceName;
                $literature->synopsis_source_url = $metadata->synopsisSourceUrl;
            }

            if ($literature->isDirty()) {
                $literature->save();
                $updated++;
            }

            $progress->advance();

            if ($delay > 0) {
                usleep($delay * 1000);
            }
        }

        $progress->finish();
        $this->newLine(2);
        $this->components->info("Updated {$updated} of {$literatures->count()} inspected records.");

        return self::SUCCESS;
    }
}
