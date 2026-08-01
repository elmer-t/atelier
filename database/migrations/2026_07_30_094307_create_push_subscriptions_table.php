<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The browser push subscriptions behind the native web-push channel (#35), one row
     * per device a User has granted permission on. Polymorphic (`subscribable`) because
     * the package binds subscriptions to any notifiable — here always a User, Creator or
     * passwordless Client alike. The endpoint is unique: a device re-subscribing updates
     * its row rather than minting a second.
     *
     * Published by laravel-notification-channels/webpush and kept in the repo's own
     * migration style; the connection and table name stay config-driven so the package's
     * PushSubscription model and this table always agree.
     */
    public function up(): void
    {
        Schema::connection(config('webpush.database_connection'))->create(config('webpush.table_name'), function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->morphs('subscribable', 'push_subscriptions_subscribable_morph_idx');
            $table->string('endpoint', 500)->unique();
            $table->string('public_key')->nullable();
            $table->string('auth_token')->nullable();
            $table->string('content_encoding')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection(config('webpush.database_connection'))->dropIfExists(config('webpush.table_name'));
    }
};
