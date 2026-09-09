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
        Schema::create('canonical_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('primary_author_id')->nullable()->constrained('authors')->nullOnDelete();
            $table->string('canonical_title');
            $table->string('normalized_title');
            $table->string('type', 30);
            $table->unsignedSmallInteger('publication_year')->nullable();
            $table->timestamps();

            $table->index(['normalized_title', 'type', 'publication_year'], 'canonical_works_title_type_year_index');
            $table->index(['primary_author_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('canonical_works');
    }
};
