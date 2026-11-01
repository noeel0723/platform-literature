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
            $table->string('banner_path')->nullable();
            $table->unsignedTinyInteger('banner_position_x')->default(50);
            $table->unsignedTinyInteger('banner_position_y')->default(50);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['banner_path', 'banner_position_x', 'banner_position_y']);
        });
    }
};
