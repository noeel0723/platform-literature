<?php

namespace App\Console\Commands;

use App\Models\Literature;
use App\Services\Literature\KnowledgeGraphEnricher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('literature:enrich-entities
    {--limit=100 : Maximum records to inspect}
    {--query= : Only inspect titles containing this text}
    {--delay=250 : Delay between requests in milliseconds}')]
#[Description('Link catalog records to Google Knowledge Graph entities')]
class EnrichLiteratureEntities extends Command
{
    public function handle(KnowledgeGraphEnricher $knowledgeGraph): int
    {
        if (blank(config('services.knowledge_graph.key'))) {
            $this->components->warn('Google Knowledge Graph API key is not configured.');

            return self::FAILURE;
        }

        $limit = max(1, min((int) $this->option('limit'), 500));
        $query = trim((string) $this->option('query'));
        $delay = max(0, min((int) $this->option('delay'), 5000));
        $literatures = Literature::query()
            ->whereNull('knowledge_graph_id')
            ->when($query !== '', fn ($builder) => $builder->where('title', 'like', "%{$query}%"))
            ->with('authors')
            ->latest('updated_at')
            ->latest('id')
            ->limit($limit)
            ->get();

        if ($literatures->isEmpty()) {
            $this->components->info('No catalog records require entity enrichment.');

            return self::SUCCESS;
        }

        $updated = 0;
        $progress = $this->output->createProgressBar($literatures->count());
        $progress->start();

        foreach ($literatures as $literature) {
            $entity = $knowledgeGraph->find(
                $literature->title,
                $literature->authors->pluck('name')->all(),
            );

            if ($entity !== null) {
                $usesKnowledgeGraphSynopsis = $literature->synopsis === null
                    && $entity->detailedDescription !== null;

                $literature->fill([
                    'knowledge_graph_id' => $entity->id,
                    'knowledge_graph_types' => $entity->types,
                    'knowledge_graph_url' => $entity->sourceUrl ?? $entity->officialUrl,
                    'knowledge_graph_score' => $entity->score,
                    'tagline' => $literature->tagline ?? $entity->description,
                    'synopsis' => $literature->synopsis ?? $entity->detailedDescription,
                    'synopsis_source_name' => $usesKnowledgeGraphSynopsis
                        ? 'Google Knowledge Graph'
                        : $literature->synopsis_source_name,
                    'synopsis_source_url' => $usesKnowledgeGraphSynopsis
                        ? $entity->sourceUrl ?? $entity->officialUrl
                        : $literature->synopsis_source_url,
                ]);
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
        $this->components->info("Linked {$updated} of {$literatures->count()} inspected records.");

        return self::SUCCESS;
    }
}
