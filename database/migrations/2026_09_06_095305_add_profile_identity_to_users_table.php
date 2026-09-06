<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->after('id');
            $table->string('location', 100)->nullable()->after('email');
            $table->string('bio', 500)->nullable()->after('location');
        });

        DB::table('users')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->each(function (object $user): void {
                $base = Str::of((string) $user->name)
                    ->lower()
                    ->ascii()
                    ->replaceMatches('/[^a-z0-9]+/', '_')
                    ->trim('_')
                    ->limit(40, '')
                    ->toString();
                $base = $base !== '' ? $base : 'reader';
                $candidate = $base;
                $suffix = 1;

                while (DB::table('users')->where('username', $candidate)->exists()) {
                    $suffix++;
                    $candidate = Str::limit($base, 45, '').'_'.$suffix;
                }

                DB::table('users')->where('id', $user->id)->update(['username' => $candidate]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'location', 'bio']);
        });
    }
};
