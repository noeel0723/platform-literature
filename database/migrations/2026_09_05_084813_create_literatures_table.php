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
        Schema::create('literatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_source_id')->constrained()->restrictOnDelete();
            $table->string('external_id');
            $table->string('slug')->unique();
            $table->string('title')->index();
            $table->string('original_title')->nullable();
            $table->string('type', 30)->index();
            $table->unsignedSmallInteger('publication_year')->nullable();
            $table->string('tagline')->nullable();
            $table->text('synopsis')->nullable();
            $table->string('publisher')->nullable();
            $table->string('language', 100)->nullable();
            $table->string('format', 100)->nullable();
            $table->string('identifier')->nullable();
            $table->string('cover_url')->nullable();
            $table->string('theme', 30)->default('cream');
            $table->timestamps();

            $table->unique(['api_source_id', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('literatures');
    }
};
