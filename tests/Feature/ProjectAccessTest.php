<?php

use App\Models\Asset;
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
    Asset::factory()->for($project)->markdown('# Hello world')->create(['title' => 'Brief']);

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
    Asset::factory()->for($project)->markdown('# Confidential brief')->create();

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

it('invalidates the viewer session when the password is rotated', function () {
    $project = Project::factory()->private('first')->create();

    $this->post(route('project.unlock', $project), ['password' => 'first'])
        ->assertRedirect(route('project.show', $project));
    $this->get(route('project.show', $project))->assertOk();

    // Rotate the password: existing session should no longer be valid.
    $project->setPassword('second');

    $this->get(route('project.show', $project))->assertRedirect(route('project.gate', $project));
});
