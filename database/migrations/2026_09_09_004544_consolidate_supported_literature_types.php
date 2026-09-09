<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('literatures')
            ->whereIn('type', ['book', 'light-novel'])
            ->update(['type' => 'novel']);

        DB::table('api_sources')
            ->where('key', 'google-books')
            ->update(['supported_types' => json_encode(['novel'])]);

        DB::table('api_sources')
            ->where('key', 'anilist')
            ->update(['supported_types' => json_encode(['manga', 'manhwa'])]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('literatures')
            ->where('type', 'novel')
            ->whereIn('format', ['Book', 'Buku'])
            ->update(['type' => 'book']);

        DB::table('literatures')
            ->where('type', 'novel')
            ->whereIn('format', ['Light Novel', 'Light novel'])
            ->update(['type' => 'light-novel']);

        DB::table('api_sources')
            ->where('key', 'google-books')
            ->update(['supported_types' => json_encode(['book', 'novel'])]);

        DB::table('api_sources')
            ->where('key', 'anilist')
            ->update(['supported_types' => json_encode(['manga', 'manhwa', 'light-novel'])]);
    }
};
