<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A project's optional cover: points at one of its own artifacts. Nulled when
     * that artifact is deleted, so a project never references a missing cover.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('header_artifact_id')
                ->nullable()
                ->after('view_count')
                ->constrained('artifacts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('header_artifact_id');
        });
    }
};
