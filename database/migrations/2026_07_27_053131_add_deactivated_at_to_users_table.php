<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deactivation blocks a Client from commenting again without destroying the
     * feedback they already left — the middle ground between tolerating a nuisance
     * commenter and cascade-deleting their whole history (#30).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deactivated_at')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('deactivated_at');
        });
    }
};
