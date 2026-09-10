<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $reviewedMappings = DB::table('literature_source_mappings')
                ->where('match_method', 'admin_review')
                ->orderBy('id')
                ->get();

            foreach ($reviewedMappings as $mapping) {
                $literature = DB::table('literatures')->find($mapping->literature_id);

                if ($literature === null) {
                    continue;
                }

                $primaryAuthorId = DB::table('author_literature')
                    ->where('literature_id', $literature->id)
                    ->orderBy('position')
                    ->value('author_id');
                $orphanQuery = DB::table('canonical_works')
                    ->whereNotExists(fn ($query) => $query
                        ->selectRaw('1')
                        ->from('literature_source_mappings')
                        ->whereColumn('literature_source_mappings.canonical_work_id', 'canonical_works.id'))
                    ->where('normalized_title', $this->normalizeTitle($literature->title))
                    ->where('type', $literature->type);

                $literature->publication_year === null
                    ? $orphanQuery->whereNull('publication_year')
                    : $orphanQuery->where('publication_year', $literature->publication_year);
                $primaryAuthorId === null
                    ? $orphanQuery->whereNull('primary_author_id')
                    : $orphanQuery->where('primary_author_id', $primaryAuthorId);

                $restoredWorkId = $orphanQuery->value('id');

                if ($restoredWorkId === null) {
                    $restoredWorkId = DB::table('canonical_works')->insertGetId([
                        'primary_author_id' => $primaryAuthorId,
                        'preferred_literature_id' => $literature->id,
                        'canonical_title' => $literature->title,
                        'normalized_title' => $this->normalizeTitle($literature->title),
                        'type' => $literature->type,
                        'publication_year' => $literature->publication_year,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('literature_source_mappings')
                    ->where('id', $mapping->id)
                    ->update([
                        'canonical_work_id' => $restoredWorkId,
                        'match_method' => 'restored_distinct',
                        'mapping_status' => 'matched',
                        'confidence' => 1,
                        'candidate_work_ids' => null,
                        'updated_at' => now(),
                    ]);
                DB::table('canonical_works')->where('id', $restoredWorkId)->update([
                    'preferred_literature_id' => $literature->id,
                    'updated_at' => now(),
                ]);

                $sourceKey = DB::table('api_sources')->where('id', $mapping->api_source_id)->value('key');

                if (is_string($sourceKey)) {
                    DB::table('canonical_work_identifiers')
                        ->where('canonical_work_id', $mapping->canonical_work_id)
                        ->where('scheme', 'source:'.$sourceKey)
                        ->where('value', $mapping->source_external_id)
                        ->update([
                            'canonical_work_id' => $restoredWorkId,
                            'updated_at' => now(),
                        ]);
                }

                $replacement = DB::table('literature_source_mappings')
                    ->where('canonical_work_id', $mapping->canonical_work_id)
                    ->orderByDesc('quality_score')
                    ->orderBy('literature_id')
                    ->value('literature_id');
                DB::table('canonical_works')->where('id', $mapping->canonical_work_id)->update([
                    'preferred_literature_id' => $replacement,
                    'updated_at' => now(),
                ]);
            }

            DB::table('literature_source_mappings')
                ->where('mapping_status', 'needs_review')
                ->update([
                    'mapping_status' => 'matched',
                    'match_method' => 'ambiguous_separate',
                    'candidate_work_ids' => null,
                    'updated_at' => now(),
                ]);

            DB::table('literature_source_mappings')
                ->where('match_method', 'admin_distinct')
                ->update([
                    'match_method' => 'manual_distinct',
                    'updated_at' => now(),
                ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restored work boundaries cannot be safely re-merged automatically.
    }

    private function normalizeTitle(string $title): string
    {
        $normalized = mb_strtolower(html_entity_decode(strip_tags($title)));
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized) ?? $normalized;

        return trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);
    }
};
