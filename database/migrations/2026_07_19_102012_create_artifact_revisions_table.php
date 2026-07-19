<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Move markdown content out of the Artifact row into an append-only log of
     * Revisions (ADR-0005). Each existing markdown Artifact becomes its own
     * Revision 1 (authored by today's sole Creator) before the body column drops,
     * so nothing is lost in the transition.
     *
     * `current_revision_id` intentionally carries no DB foreign key: artifacts and
     * artifact_revisions reference each other, and a plain indexed column sidesteps
     * the cyclic cascade while the pointer invariant is upheld in application code.
     */
    public function up(): void
    {
        Schema::create('artifact_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artifact_id')->constrained()->cascadeOnDelete();
            $table->longText('body');
            // Nullable only to tolerate a backfill with no discoverable author;
            // application writes always attribute a Revision to its author.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['artifact_id', 'id']);
        });

        Schema::table('artifacts', function (Blueprint $table) {
            $table->unsignedBigInteger('current_revision_id')->nullable()->after('sort_order');
            $table->index('current_revision_id');
        });

        $this->backfill();

        Schema::table('artifacts', function (Blueprint $table) {
            $table->dropColumn('body');
        });
    }

    public function down(): void
    {
        Schema::table('artifacts', function (Blueprint $table) {
            $table->longText('body')->nullable();
        });

        // Restore each Artifact's live body from its current Revision.
        foreach (DB::table('artifacts')->whereNotNull('current_revision_id')->get() as $artifact) {
            $body = DB::table('artifact_revisions')->where('id', $artifact->current_revision_id)->value('body');
            DB::table('artifacts')->where('id', $artifact->id)->update(['body' => $body]);
        }

        Schema::table('artifacts', function (Blueprint $table) {
            $table->dropIndex(['current_revision_id']);
            $table->dropColumn('current_revision_id');
        });

        Schema::dropIfExists('artifact_revisions');
    }

    /**
     * Seed a Revision 1 for every existing markdown Artifact and point it at that
     * Revision, attributed to the account's Creator (falling back to any User).
     */
    private function backfill(): void
    {
        $author = DB::table('users')->where('role', 'creator')->orderBy('id')->value('id')
            ?? DB::table('users')->orderBy('id')->value('id');

        foreach (DB::table('artifacts')->where('type', 'markdown')->get() as $artifact) {
            $revisionId = DB::table('artifact_revisions')->insertGetId([
                'artifact_id' => $artifact->id,
                'body' => $artifact->body ?? '',
                'user_id' => $author,
                'created_at' => $artifact->created_at ?? now(),
            ]);

            DB::table('artifacts')->where('id', $artifact->id)->update([
                'current_revision_id' => $revisionId,
            ]);
        }
    }
};
