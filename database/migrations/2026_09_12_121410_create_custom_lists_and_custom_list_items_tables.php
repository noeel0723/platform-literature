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
        Schema::create('custom_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_private')->default(false)->index();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('custom_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('canonical_work_id')->constrained()->cascadeOnDelete();
            $table->foreignId('literature_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->unique(['custom_list_id', 'canonical_work_id']);
            $table->unique(['custom_list_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_list_items');
        Schema::dropIfExists('custom_lists');
    }
};
