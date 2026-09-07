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
        Schema::create('literature_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('literature_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_literature_id')->constrained('literatures')->cascadeOnDelete();
            $table->string('relation_type', 40)->index();
            $table->string('source', 100)->nullable();
            $table->timestamps();

            $table->unique(
                ['literature_id', 'related_literature_id', 'relation_type'],
                'literature_relations_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('literature_relations');
    }
};
