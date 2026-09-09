<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('literature_source_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canonical_work_id')->constrained()->cascadeOnDelete();
            $table->foreignId('literature_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('api_source_id')->constrained()->restrictOnDelete();
            $table->string('source_external_id');
            $table->string('match_method', 50);
            $table->string('mapping_status', 30)->index();
            $table->decimal('confidence', 5, 4);
            $table->json('candidate_work_ids')->nullable();
            $table->json('field_provenance')->nullable();
            $table->timestamps();

            $table->unique(['api_source_id', 'source_external_id'], 'literature_source_identity_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('literature_source_mappings');
    }
};
