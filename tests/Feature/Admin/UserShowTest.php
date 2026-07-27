<?php

use App\Livewire\Admin\Users\Show;
use App\Models\Artifact;
use App\Models\Comment;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    $this->creator = User::factory()->create();
    $this->actingAs($this->creator);
});

it('is forbidden to a non-creator', function () {
    $client = User::factory()->client()->create();

    $this->actingAs(User::factory()->client()->create(['email_verified_at' => now()]));

    $this->get(route('admin.users.show', $client))->assertForbidden();
});

it('lists the comments a client left, each linking to the artifact it was left on', function () {
    $project = Project::factory()->public()->create(['title' => 'Harbor District']);
    $artifact = Artifact::factory()->for($project)->markdown('# Brief')->create(['title' => 'Brief']);
    $client = User::factory()->client()->create(['name' => 'Casey Client']);

    Comment::factory()->create([
        'artifact_id' => $artifact->id,
        'user_id' => $client->id,
        'body' => 'The hero copy is too long.',
    ]);

    Livewire::test(Show::class, ['user' => $client])
        ->assertSee('Casey Client')
        ->assertSee('The hero copy is too long.')
        ->assertSee('Harbor District')
        ->assertSee(route('project.artifact', ['project' => $project, 'artifact' => $artifact]), escape: false);
});

it('leaves a creator following a comment link on the artifact rather than the password gate', function () {
    $project = Project::factory()->private()->create();
    $artifact = Artifact::factory()->for($project)->markdown('# Brief')->create(['title' => 'Brief']);

    $this->get(route('project.artifact', ['project' => $project, 'artifact' => $artifact]))
        ->assertOk()
        ->assertSee('Brief');
});

it('does not count a creator reading their own project as a view', function () {
    $project = Project::factory()->public()->create();
    Artifact::factory()->for($project)->markdown('# Brief')->create();

    $this->get(route('project.show', $project))->assertOk();

    expect($project->fresh()->view_count)->toBe(0)
        ->and($project->fresh()->last_viewed_at)->toBeNull();
});

it('says so when a client has left no feedback', function () {
    $client = User::factory()->client()->create(['name' => 'Quiet Quinn']);

    Livewire::test(Show::class, ['user' => $client])
        ->assertSee('Quiet Quinn has not left any feedback.');
});

it('deactivates and reactivates the client from their own page', function () {
    $client = User::factory()->client()->create();

    Livewire::test(Show::class, ['user' => $client])
        ->call('deactivate')
        ->assertHasNoErrors();

    expect($client->fresh()->isDeactivated())->toBeTrue();

    Livewire::test(Show::class, ['user' => $client->fresh()])
        ->call('reactivate');

    expect($client->fresh()->isDeactivated())->toBeFalse();
});

it('deletes the client with their comments and returns to the list', function () {
    $client = User::factory()->client()->create();
    Comment::factory()->count(2)->create(['user_id' => $client->id]);

    Livewire::test(Show::class, ['user' => $client])
        ->call('delete')
        ->assertRedirect(route('admin.users'));

    expect(User::find($client->id))->toBeNull()
        ->and(Comment::where('user_id', $client->id)->count())->toBe(0);
});

it('warns that deleting takes the comments too', function () {
    $client = User::factory()->client()->create();
    Comment::factory()->count(2)->create(['user_id' => $client->id]);

    Livewire::test(Show::class, ['user' => $client])
        ->assertSee('2 comments will be deleted with them');
});

it('refuses to delete the agent from its own page', function () {
    $agent = User::factory()->agent()->create();

    Livewire::test(Show::class, ['user' => $agent])
        ->call('delete')
        ->assertForbidden();

    expect($agent->fresh())->not->toBeNull();
});

it('offers no delete action for the last remaining creator', function () {
    Livewire::test(Show::class, ['user' => $this->creator])
        ->assertDontSee('wire:click="delete"', escape: false)
        ->call('delete')
        ->assertForbidden();
});
