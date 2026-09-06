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
            $table->string('synopsis_source_name')->nullable()->after('synopsis');
            $table->text('synopsis_source_url')->nullable()->after('synopsis_source_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('literatures', function (Blueprint $table) {
            $table->dropColumn(['synopsis_source_name', 'synopsis_source_url']);
        });
    }
};
