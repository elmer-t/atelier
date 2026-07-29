<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When a User last saw a Thread as it stood. Append-only: a row is written each
     * time a reader opens a Thread that has moved on since they last opened it, so the
     * table is both the live "have I seen this?" answer and the history of when each
     * batch of activity was seen.
     *
     * Recognition is device-bound (ADR-0003): a Client is known by their return-visit
     * cookie, so these rows describe this identity on this device, never a verified
     * person. They drive navigation cues only, and are never surfaced to the Creator
     * as evidence that a Client saw something.
     */
    public function up(): void
    {
        Schema::create('comment_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The root Comment. A Thread is the unit that is read, as it is the unit
            // that is Resolved; deleting it makes its read history meaningless.
            $table->foreignId('comment_id')->constrained('comments')->cascadeOnDelete();
            // The newest Comment in that Thread when it was opened. Deliberately
            // unconstrained: it is a high-water mark rather than a live reference, so
            // deleting the Comment it happens to name must neither regress the mark nor
            // erase the fact that the reader had caught up.
            $table->unsignedBigInteger('seen_through_comment_id');
            $table->timestamps();

            $table->index(['user_id', 'comment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_reads');
    }
};
