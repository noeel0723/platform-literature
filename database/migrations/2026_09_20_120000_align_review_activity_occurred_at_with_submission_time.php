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
        DB::table('activities')
            ->whereIn('type', ['rated', 'reviewed'])
            ->whereNotNull('created_at')
            ->update(['occurred_at' => DB::raw('created_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Submission timestamps cannot be safely converted back into reading dates.
    }
};
