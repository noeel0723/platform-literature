<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canonical_work_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('canonical_work_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 120);
            $table->text('url');
            $table->string('link_type', 20);
            $table->string('region', 12)->nullable();
            $table->string('language', 12)->nullable();
            $table->string('source', 120);
            $table->boolean('is_official')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['canonical_work_id', 'is_active', 'is_official'], 'canonical_work_links_visibility_index');
            $table->index(['provider', 'link_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canonical_work_links');
    }
};
