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
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('onboarding_required')->default(false);
            $table->unsignedTinyInteger('onboarding_step')->default(1);
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->json('preferred_formats')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['onboarding_required', 'onboarding_step', 'onboarding_completed_at', 'preferred_formats']);
        });
    }
};
