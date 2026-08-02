<?php

use App\Livewire\Admin\Projects\Index;
use App\Livewire\Admin\Projects\Manage;
use App\Models\Artifact;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->creator = User::factory()->create();
    $this->actingAs($this->creator);
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

it('owns a new project by the Creator who created it', function () {
    Livewire::test(Index::class)
        ->set('newTitle', 'Acme Redesign')
        ->call('create')
        ->assertRedirect();

    $project = Project::firstWhere('title', 'Acme Redesign');

    expect($project->owner->id)->toBe($this->creator->id)
        ->and($this->creator->projects()->pluck('title')->all())->toBe(['Acme Redesign']);
});

it('keeps a project when its owning Creator is deleted, leaving it unowned', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $owner->delete();

    expect($project->refresh()->owner)->toBeNull()
        ->and(Project::find($project->id))->not->toBeNull();
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
        ->set('newPassword', 'hunter2-open')
        ->call('saveSettings')
        ->assertHasNoErrors();

    expect($project->refresh()->checkPassword('hunter2-open'))->toBeTrue();
});

it('rejects a gate password below the minimum length', function () {
    $project = Project::factory()->public()->create();

    Livewire::test(Manage::class, ['project' => $project])
        ->set('visibility', 'private')
        ->set('newPassword', 'short')
        ->call('saveSettings')
        ->assertHasErrors(['newPassword' => 'min']);

    expect($project->refresh()->password_hash)->toBeNull();
});

it('generates a memorable passphrase that fills the field and clears the minimum', function () {
    $project = Project::factory()->public()->create();

    $component = Livewire::test(Manage::class, ['project' => $project])
        ->call('generatePassphrase');

    $passphrase = $component->get('newPassword');

    // Sentence-style, hyphen-joined, and comfortably past the raised minimum.
    expect($passphrase)->toMatch('/^[a-z]+(-[a-z]+){3,}$/')
        ->and(strlen($passphrase))->toBeGreaterThanOrEqual(8);

    // And it saves through the same validation/hashing path with no errors.
    $component->set('visibility', 'private')
        ->call('saveSettings')
        ->assertHasNoErrors();

    expect($project->refresh()->checkPassword($passphrase))->toBeTrue();
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
        ->assertSee('wire:model="newPassword"', escape: false)
        ->assertSee('x-bind:disabled="$wire.isPublic"', escape: false);
});

it('renders the manage page with the artifact list and the settings modal', function () {
    $project = Project::factory()->create(['title' => 'Harbor District']);

    $this->get(route('admin.projects.manage', $project))
        ->assertOk()
        ->assertSee('Harbor District')
        ->assertSee('Artifacts')
        ->assertSee('project-settings', escape: false);
});

it('names the owning Creator in the header', function () {
    $owner = User::factory()->create(['name' => 'Ada Operator']);
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->get(route('admin.projects.manage', $project))
        ->assertOk()
        ->assertSee('Ada Operator')
        ->assertDontSee('Unowned');
});

it('flags an unowned project in the header instead of naming an owner', function () {
    $project = Project::factory()->unowned()->create();

    $this->get(route('admin.projects.manage', $project))
        ->assertOk()
        ->assertSee('Unowned');
});

it('offers the shareable link as a new-tab link', function () {
    $project = Project::factory()->public()->create();

    $this->get(route('admin.projects.manage', $project))
        ->assertOk()
        ->assertSeeInOrder([
            'href="'.route('project.show', $project).'"',
            'target="_blank"',
        ], escape: false);
});

it('mirrors the segmented controls back onto the toggles', function () {
    $project = Project::factory()->private('secret')->create();

    Livewire::test(Manage::class, ['project' => $project])
        ->set('visibility', 'public')
        ->assertSet('isPublic', true)
        ->set('status', 'archived')
        ->assertSet('isArchived', true);
});

it('shows the cover thumbnail for the artifact picked in the form', function () {
    $project = Project::factory()->public()->create();
    $cover = Artifact::factory()->for($project)->file()->create();

    // Tracks the unsaved form value, not the persisted column.
    Livewire::test(Manage::class, ['project' => $project])
        ->assertDontSee(route('admin.projects.artifact-preview', [$project, $cover]), escape: false)
        ->set('headerArtifactId', (string) $cover->id)
        ->assertSee(route('admin.projects.artifact-preview', [$project, $cover]), escape: false);
});

it('serves an artifact preview to the creator on a private project', function () {
    Storage::fake('local');
    $project = Project::factory()->private('secret')->create();
    $cover = Artifact::factory()->for($project)->file()->create(['stored_path' => 'artifacts/cover.png']);
    Storage::disk('local')->put('artifacts/cover.png', 'not-really-a-png');

    $this->get(route('admin.projects.artifact-preview', [$project, $cover]))->assertOk();
});

it('reissues the shareable link, rotating slug and sandbox token', function () {
    $project = Project::factory()->create();
    $oldSlug = $project->slug;
    $oldToken = $project->sandbox_token;

    Livewire::test(Manage::class, ['project' => $project])->call('reissueLink');

    $project->refresh();

    expect($project->slug)->not->toBe($oldSlug)
        ->and($project->sandbox_token)->not->toBe($oldToken);
});

it('shows whether and when each project was last viewed on the index', function () {
    Project::factory()->create(['title' => 'Never Opened']);
    $seen = Project::factory()->create(['title' => 'Already Opened']);
    $seen->recordView();

    Livewire::test(Index::class)
        ->assertSee('Never Opened')
        ->assertSee('Not yet viewed')
        ->assertSee('Already Opened')
        ->assertSee('ago');
});

it('saves an optional link expiry and clears it', function () {
    $project = Project::factory()->public()->create();

    Livewire::test(Manage::class, ['project' => $project])
        ->set('expiresAt', now()->addWeek()->format('Y-m-d\TH:i'))
        ->call('saveSettings')
        ->assertHasNoErrors();

    expect($project->refresh()->expires_at)->not->toBeNull();

    Livewire::test(Manage::class, ['project' => $project])
        ->set('expiresAt', '')
        ->call('saveSettings')
        ->assertHasNoErrors();

    expect($project->refresh()->expires_at)->toBeNull();
});

it('sets and clears a cover image from an image artifact', function () {
    $project = Project::factory()->public()->create();
    $cover = Artifact::factory()->for($project)->file()->create();

    Livewire::test(Manage::class, ['project' => $project])
        ->set('headerArtifactId', (string) $cover->id)
        ->call('saveSettings')
        ->assertHasNoErrors();

    expect($project->refresh()->header_artifact_id)->toBe($cover->id);

    Livewire::test(Manage::class, ['project' => $project])
        ->set('headerArtifactId', '')
        ->call('saveSettings')
        ->assertHasNoErrors();

    expect($project->refresh()->header_artifact_id)->toBeNull();
});

it('rejects a non-image artifact as the cover', function () {
    $project = Project::factory()->public()->create();
    $zip = Artifact::factory()->for($project)->download()->create();

    Livewire::test(Manage::class, ['project' => $project])
        ->set('headerArtifactId', (string) $zip->id)
        ->call('saveSettings')
        ->assertHasErrors('headerArtifactId');

    expect($project->refresh()->header_artifact_id)->toBeNull();
});

it('rejects an artifact from another project as the cover', function () {
    $project = Project::factory()->public()->create();
    $foreign = Artifact::factory()->for(Project::factory()->create())->file()->create();

    Livewire::test(Manage::class, ['project' => $project])
        ->set('headerArtifactId', (string) $foreign->id)
        ->call('saveSettings')
        ->assertHasErrors('headerArtifactId');

    expect($project->refresh()->header_artifact_id)->toBeNull();
});
