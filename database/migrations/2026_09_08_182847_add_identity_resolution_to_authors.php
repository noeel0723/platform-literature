<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            $table->string('normalized_name')->nullable()->index()->after('name');
            $table->string('external_entity_id')->nullable()->unique()->after('normalized_name');
        });

        $normalizeName = static function (string $name): string {
            $normalized = Str::lower(Str::squish(html_entity_decode(strip_tags($name))));
            $normalized = preg_replace('/[\.\x{FF0E}\'\x{2019}`]+/u', '', $normalized) ?? $normalized;
            $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized) ?? $normalized;
            $tokens = preg_split('/\s+/u', trim($normalized), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $parts = [];
            $initials = '';

            foreach ($tokens as $token) {
                if (Str::length($token) === 1) {
                    $initials .= $token;

                    continue;
                }

                if ($initials !== '') {
                    $parts[] = $initials;
                    $initials = '';
                }

                $parts[] = $token;
            }

            if ($initials !== '') {
                $parts[] = $initials;
            }

            return implode(' ', $parts);
        };

        DB::table('authors')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->chunkById(200, function ($authors) use ($normalizeName): void {
                foreach ($authors as $author) {
                    DB::table('authors')
                        ->where('id', $author->id)
                        ->update(['normalized_name' => $normalizeName($author->name)]);
                }
            });

        Schema::create('author_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('normalized_name')->index();
            $table->string('source', 50);
            $table->string('external_id')->nullable();
            $table->text('source_url')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->unique(['author_id', 'source', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('author_aliases');

        Schema::table('authors', function (Blueprint $table) {
            $table->dropIndex(['normalized_name']);
            $table->dropUnique(['external_entity_id']);
            $table->dropColumn(['normalized_name', 'external_entity_id']);
        });
    }
};
