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
            $table->string('knowledge_graph_id')->nullable()->index()->after('external_id');
            $table->json('knowledge_graph_types')->nullable()->after('knowledge_graph_id');
            $table->text('knowledge_graph_url')->nullable()->after('knowledge_graph_types');
            $table->decimal('knowledge_graph_score', 14, 4)->nullable()->after('knowledge_graph_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('literatures', function (Blueprint $table) {
            $table->dropIndex(['knowledge_graph_id']);
            $table->dropColumn([
                'knowledge_graph_id',
                'knowledge_graph_types',
                'knowledge_graph_url',
                'knowledge_graph_score',
            ]);
        });
    }
};
