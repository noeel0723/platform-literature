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
        Schema::table('custom_lists', function (Blueprint $table) {
            $table->boolean('is_ranked')->default(false)->after('is_private');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_lists', function (Blueprint $table) {
            $table->dropColumn('is_ranked');
        });
    }
};
