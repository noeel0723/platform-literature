<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('literature_metadata_overrides', function (Blueprint $table) {
            $table->foreignId('canonical_work_id')
                ->nullable()
                ->after('literature_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();
        });

        DB::table('literature_metadata_overrides')
            ->orderBy('id')
            ->eachById(function (object $override): void {
                $canonicalWorkId = DB::table('literature_source_mappings')
                    ->where('literature_id', $override->literature_id)
                    ->value('canonical_work_id');

                if ($canonicalWorkId !== null) {
                    DB::table('literature_metadata_overrides')
                        ->where('id', $override->id)
                        ->update(['canonical_work_id' => $canonicalWorkId]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('literature_metadata_overrides', function (Blueprint $table) {
            $table->dropUnique(['canonical_work_id']);
            $table->dropConstrainedForeignId('canonical_work_id');
        });
    }
};
