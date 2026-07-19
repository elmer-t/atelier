<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feedback lives as a flat, one-level Thread: an anchored root Comment plus its
     * Replies. Anchor / resolution state belong to roots only; a Reply carries neither
     * (ADR-0004 for the anchor shape, ADR-0003 for the passwordless author).
     */
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artifact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Null on roots; set to a root's id on replies (depth capped at 1 in code).
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->text('body');
            // Root comments only: the spot the Comment marks, discriminated by artifact origin.
            $table->json('anchor')->nullable();
            // Root comments only, Creator-only: when/who marked the Thread Resolved.
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['artifact_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
