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
        Schema::create('literature_metadata_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('literature_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('original_title')->nullable();
            $table->unsignedSmallInteger('publication_year')->nullable();
            $table->text('tagline')->nullable();
            $table->longText('synopsis')->nullable();
            $table->text('cover_url')->nullable();
            $table->string('publisher')->nullable();
            $table->string('language', 30)->nullable();
            $table->string('format', 100)->nullable();
            $table->text('source_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('literature_metadata_overrides');
    }
};
