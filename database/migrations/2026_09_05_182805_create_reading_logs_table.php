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
        Schema::create('reading_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reading_list_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 30)->index();
            $table->string('status', 30);
            $table->unsignedInteger('progress_value')->nullable();
            $table->unsignedInteger('progress_total')->nullable();
            $table->string('progress_unit', 20)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reading_logs');
    }
};
