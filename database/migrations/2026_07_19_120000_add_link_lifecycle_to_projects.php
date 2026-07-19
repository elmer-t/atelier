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
        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('session_version');
            $table->timestamp('first_viewed_at')->nullable()->after('expires_at');
            $table->timestamp('last_viewed_at')->nullable()->after('first_viewed_at');
            $table->unsignedInteger('view_count')->default(0)->after('last_viewed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['expires_at', 'first_viewed_at', 'last_viewed_at', 'view_count']);
        });
    }
};
