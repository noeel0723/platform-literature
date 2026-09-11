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
        Schema::table('literatures', function (Blueprint $table) {
            $table->text('backdrop_url')->nullable()->after('cover_url');
        });

        Schema::table('literature_metadata_overrides', function (Blueprint $table) {
            $table->text('backdrop_url')->nullable()->after('cover_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('literature_metadata_overrides', function (Blueprint $table) {
            $table->dropColumn('backdrop_url');
        });

        Schema::table('literatures', function (Blueprint $table) {
            $table->dropColumn('backdrop_url');
        });
    }
};
