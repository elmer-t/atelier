<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives a Project an owning Creator. Until now every Creator was an operator of
 * the whole instance, so anything "belonging to a Creator" had to be inferred —
 * which made comment notifications fan out to all of them.
 *
 * Nullable on purpose: existing rows predate the concept, and a deleted operator
 * must not take their projects with them, so ownership degrades to null rather
 * than cascading. Callers treat a null owner as "unowned" and fall back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        $this->backfillExistingProjectsToTheFirstCreator();
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }

    /**
     * Every project that exists today was made by the single operator running this
     * instance, so hand them all to the earliest Creator. If there is no Creator
     * yet there is also nothing to own.
     */
    private function backfillExistingProjectsToTheFirstCreator(): void
    {
        $creatorId = DB::table('users')
            ->where('role', UserRole::Creator->value)
            ->orderBy('id')
            ->value('id');

        if ($creatorId !== null) {
            DB::table('projects')->whereNull('user_id')->update(['user_id' => $creatorId]);
        }
    }
};
