<?php

use App\Models\Artifact;
use App\Models\Project;

it('lists only active public projects on the index', function () {
    $public = Project::factory()->public()->create(['title' => 'Public One']);
    $private = Project::factory()->private()->create(['title' => 'Secret']);
    $archivedPublic = Project::factory()->public()->archived()->create(['title' => 'Old Public']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Public One')
        ->assertDontSee('Secret')
        ->assertDontSee('Old Public');
});

it('opens a public project without a password', function () {
    $project = Project::factory()->public()->create();
    Artifact::factory()->for($project)->markdown('# Hello world')->create(['title' => 'Brief']);

    $this->get(route('project.show', $project))
        ->assertOk()
        ->assertSee('Hello world');
});

it('redirects a private project to the password gate', function () {
    $project = Project::factory()->private()->create();

    $this->get(route('project.show', $project))
        ->assertRedirect(route('project.gate', $project));
});

it('rejects an incorrect password', function () {
    $project = Project::factory()->private('letmein')->create();

    $this->from(route('project.gate', $project))
        ->post(route('project.unlock', $project), ['password' => 'wrong'])
        ->assertRedirect(route('project.gate', $project))
        ->assertSessionHasErrors('password');

    $this->get(route('project.show', $project))->assertRedirect(route('project.gate', $project));
});

it('unlocks a private project with the correct password and persists the session', function () {
    $project = Project::factory()->private('letmein')->create();
    Artifact::factory()->for($project)->markdown('# Confidential brief')->create();

    $this->post(route('project.unlock', $project), ['password' => 'letmein'])
        ->assertRedirect(route('project.show', $project));

    $this->get(route('project.show', $project))
        ->assertOk()
        ->assertSee('Confidential brief');
});

it('hard-disables archived projects even by direct link', function () {
    $project = Project::factory()->public()->archived()->create();

    $this->get(route('project.show', $project))->assertNotFound();
    $this->get(route('project.gate', $project))->assertNotFound();
});

it('hard-disables a project past its expiry', function () {
    $project = Project::factory()->public()->expired()->create();

    $this->get(route('project.show', $project))->assertNotFound();
    $this->get(route('project.gate', $project))->assertNotFound();
});

it('keeps a project reachable before its expiry and after clearing it', function () {
    $project = Project::factory()->public()->expiresAt(now()->addDay())->create();
    Artifact::factory()->for($project)->markdown('# Still live')->create();

    $this->get(route('project.show', $project))->assertOk()->assertSee('Still live');

    // Move expiry into the past → 404, then clear it → reachable again.
    $project->forceFill(['expires_at' => now()->subMinute()])->save();
    $this->get(route('project.show', $project))->assertNotFound();

    $project->forceFill(['expires_at' => null])->save();
    $this->get(route('project.show', $project))->assertOk()->assertSee('Still live');
});

it('records first and last viewed timestamps when a project is opened', function () {
    $project = Project::factory()->public()->create();
    Artifact::factory()->for($project)->markdown('# Hi')->create();

    $project->refresh();
    expect($project->last_viewed_at)->toBeNull()
        ->and($project->view_count)->toBe(0);

    $this->get(route('project.show', $project))->assertOk();

    $project->refresh();
    expect($project->first_viewed_at)->not->toBeNull()
        ->and($project->last_viewed_at)->not->toBeNull()
        ->and($project->view_count)->toBe(1);

    $firstViewedAt = $project->first_viewed_at;

    $this->get(route('project.show', $project))->assertOk();

    $project->refresh();
    expect($project->view_count)->toBe(2)
        ->and($project->first_viewed_at->equalTo($firstViewedAt))->toBeTrue();
});

it('does not touch updated_at when a project is merely viewed', function () {
    $project = Project::factory()->public()->create();
    Artifact::factory()->for($project)->markdown('# Hi')->create();

    // Pretend the project was last edited a while ago, reading back the value
    // the database actually stored (column precision may differ from memory).
    $project->forceFill(['updated_at' => now()->subWeek()])->saveQuietly();
    $project->refresh();
    $editedAt = $project->updated_at;

    $this->travel(1)->days();

    $this->get(route('project.show', $project))->assertOk();

    $project->refresh();
    expect($project->view_count)->toBe(1)
        ->and($project->last_viewed_at)->not->toBeNull()
        ->and($project->updated_at->equalTo($editedAt))->toBeTrue();
});

it('invalidates the viewer session when the password is rotated', function () {
    $project = Project::factory()->private('first')->create();

    $this->post(route('project.unlock', $project), ['password' => 'first'])
        ->assertRedirect(route('project.show', $project));
    $this->get(route('project.show', $project))->assertOk();

    // Rotate the password: existing session should no longer be valid.
    $project->setPassword('second');

    $this->get(route('project.show', $project))->assertRedirect(route('project.gate', $project));
});
