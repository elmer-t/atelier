<?php

use App\Livewire\Admin\Projects\Index;
use App\Livewire\Admin\Projects\Manage;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('requires authentication for the admin area', function () {
    auth()->logout();

    $this->get(route('admin.projects'))->assertRedirect(route('login'));
});

it('creates a project as private with generated tokens', function () {
    Livewire::test(Index::class)
        ->set('newTitle', 'Acme Redesign')
        ->call('create')
        ->assertRedirect();

    $project = Project::firstWhere('title', 'Acme Redesign');

    expect($project)->not->toBeNull()
        ->and($project->isPrivate())->toBeTrue()
        ->and(strlen($project->slug))->toBeGreaterThanOrEqual(32)
        ->and(strlen($project->sandbox_token))->toBeGreaterThanOrEqual(32);
});

it('archives and unarchives a project', function () {
    $project = Project::factory()->create();

    Livewire::test(Index::class)->call('toggleStatus', $project->id);
    expect($project->refresh()->isArchived())->toBeTrue();

    Livewire::test(Index::class)->call('toggleStatus', $project->id);
    expect($project->refresh()->isActive())->toBeTrue();
});

it('makes a project public and clears its password', function () {
    $project = Project::factory()->private('secret')->create();

    Livewire::test(Manage::class, ['project' => $project])
        ->set('visibility', 'public')
        ->call('saveSettings')
        ->assertHasNoErrors();

    expect($project->refresh()->isPublic())->toBeTrue()
        ->and($project->password_hash)->toBeNull();
});

it('requires a password when making a project private', function () {
    $project = Project::factory()->public()->create();

    Livewire::test(Manage::class, ['project' => $project])
        ->set('visibility', 'private')
        ->set('newPassword', '')
        ->call('saveSettings')
        ->assertHasErrors('newPassword');

    expect($project->refresh()->isPublic())->toBeTrue();
});

it('sets a password and verifies it', function () {
    $project = Project::factory()->public()->create();

    Livewire::test(Manage::class, ['project' => $project])
        ->set('visibility', 'private')
        ->set('newPassword', 'hunter2')
        ->call('saveSettings')
        ->assertHasNoErrors();

    expect($project->refresh()->checkPassword('hunter2'))->toBeTrue();
});

it('mounts the toggles from the project state', function () {
    $project = Project::factory()->public()->create(['status' => 'archived']);

    Livewire::test(Manage::class, ['project' => $project])
        ->assertSet('isPublic', true)
        ->assertSet('isArchived', true);
});

it('saves visibility and status from the toggles', function () {
    $project = Project::factory()->private('secret')->create();

    Livewire::test(Manage::class, ['project' => $project])
        ->set('isPublic', true)
        ->set('isArchived', true)
        ->call('saveSettings')
        ->assertHasNoErrors();

    expect($project->refresh()->isPublic())->toBeTrue()
        ->and($project->isArchived())->toBeTrue()
        ->and($project->password_hash)->toBeNull();
});

it('requires a password when the visibility toggle is switched to private', function () {
    $project = Project::factory()->public()->create();

    Livewire::test(Manage::class, ['project' => $project])
        ->set('isPublic', false)
        ->call('saveSettings')
        ->assertHasErrors('newPassword');

    expect($project->refresh()->isPublic())->toBeTrue();
});

it('keeps the password field in the markup for public projects', function () {
    $project = Project::factory()->public()->create();

    Livewire::test(Manage::class, ['project' => $project])
        ->assertSee('Set password')
        ->assertSee('x-bind:disabled="$wire.isPublic"', escape: false);
});

it('regenerates the shareable slug', function () {
    $project = Project::factory()->create();
    $old = $project->slug;

    Livewire::test(Manage::class, ['project' => $project])->call('regenerateSlug');

    expect($project->refresh()->slug)->not->toBe($old);
});
