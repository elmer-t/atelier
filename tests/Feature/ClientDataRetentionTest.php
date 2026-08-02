<?php

use App\Models\Artifact;
use App\Models\Comment;
use App\Models\Project;
use App\Models\User;
use App\Services\ClientDataEraser;

it('leaves everything alone when time-based retention is disabled', function () {
    config(['atelier.privacy.retention_days' => null]);

    $archived = Project::factory()->public()->archived()->create();
    $artifact = Artifact::factory()->for($archived)->markdown()->create();
    $client = User::factory()->client()->create();
    Comment::factory()->for($artifact)->for($client, 'author')->create(['created_at' => now()->subYear()]);

    $this->artisan('atelier:prune-client-data')
        ->expectsOutputToContain('on request only')
        ->assertSuccessful();

    expect($client->refresh()->name)->not->toBe(ClientDataEraser::ANONYMISED_NAME);
});

it('erases a stale Client once every project they touched is inactive', function () {
    config(['atelier.privacy.retention_days' => 30]);

    $archived = Project::factory()->public()->archived()->create();
    $artifact = Artifact::factory()->for($archived)->markdown()->create();

    $client = User::factory()->client()->create();
    Comment::factory()->for($artifact)->for($client, 'author')
        ->create(['body' => 'Old feedback', 'created_at' => now()->subDays(90)]);

    $this->artisan('atelier:prune-client-data')->assertSuccessful();

    expect($client->refresh()->name)->toBe(ClientDataEraser::ANONYMISED_NAME)
        ->and($client->email)->toEndWith('@atelier.invalid');
});

it('keeps a Client still active on a live project', function () {
    config(['atelier.privacy.retention_days' => 30]);

    $active = Project::factory()->public()->create();
    $artifact = Artifact::factory()->for($active)->markdown()->create();

    $client = User::factory()->client()->create();
    // Old comment, but the project is still live, so the person is still participating.
    Comment::factory()->for($artifact)->for($client, 'author')->create(['created_at' => now()->subDays(90)]);

    $this->artisan('atelier:prune-client-data')->assertSuccessful();

    expect($client->refresh()->name)->not->toBe(ClientDataEraser::ANONYMISED_NAME);
});

it('keeps a Client whose most recent comment is still within the window', function () {
    config(['atelier.privacy.retention_days' => 30]);

    $archived = Project::factory()->public()->archived()->create();
    $artifact = Artifact::factory()->for($archived)->markdown()->create();

    $client = User::factory()->client()->create();
    Comment::factory()->for($artifact)->for($client, 'author')->create(['created_at' => now()->subDays(5)]);

    $this->artisan('atelier:prune-client-data')->assertSuccessful();

    expect($client->refresh()->name)->not->toBe(ClientDataEraser::ANONYMISED_NAME);
});
