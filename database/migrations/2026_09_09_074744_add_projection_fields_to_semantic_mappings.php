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
        Schema::table('canonical_works', function (Blueprint $table) {
            $table->foreignId('preferred_literature_id')
                ->nullable()
                ->after('primary_author_id')
                ->constrained('literatures')
                ->nullOnDelete();
        });

        Schema::table('literature_source_mappings', function (Blueprint $table) {
            $table->integer('quality_score')->default(0)->after('confidence');
        });

        DB::table('canonical_works')
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function ($works): void {
                foreach ($works as $work) {
                    $literatureId = DB::table('literature_source_mappings')
                        ->where('canonical_work_id', $work->id)
                        ->min('literature_id');

                    if ($literatureId !== null) {
                        DB::table('canonical_works')
                            ->where('id', $work->id)
                            ->update(['preferred_literature_id' => $literatureId]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('canonical_works', function (Blueprint $table) {
            $table->dropConstrainedForeignId('preferred_literature_id');
        });

        Schema::table('literature_source_mappings', function (Blueprint $table) {
            $table->dropColumn('quality_score');
        });
    }
};
