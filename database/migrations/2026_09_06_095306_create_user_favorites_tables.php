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
        Schema::create('user_favorite_literatures', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('literature_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->timestamps();

            $table->primary(['user_id', 'literature_id']);
            $table->unique(['user_id', 'position']);
        });

        Schema::create('user_favorite_authors', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->timestamps();

            $table->primary(['user_id', 'author_id']);
            $table->unique(['user_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_favorite_authors');
        Schema::dropIfExists('user_favorite_literatures');
    }
};
