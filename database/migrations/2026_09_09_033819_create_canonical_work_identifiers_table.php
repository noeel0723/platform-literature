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
        Schema::create('canonical_work_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canonical_work_id')->constrained()->cascadeOnDelete();
            $table->string('scheme', 50);
            $table->string('value');
            $table->string('source_key', 50)->nullable();
            $table->timestamps();

            $table->unique(['scheme', 'value']);
            $table->index(['canonical_work_id', 'scheme']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('canonical_work_identifiers');
    }
};
