<?php

use App\Livewire\Dashboard;
use App\Models\Artifact;
use App\Models\Comment;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated creators can visit the dashboard', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertOk();
});

test('feedback queue lists projects with unresolved threads, most first', function () {
    $this->actingAs(User::factory()->create());

    $quiet = Project::factory()->has(Artifact::factory())->create(['title' => 'Quiet project']);
    $busy = Project::factory()->has(Artifact::factory())->create(['title' => 'Busy project']);

    Comment::factory()->for($quiet->artifacts->first(), 'artifact')->create();
    Comment::factory()->count(3)->for($busy->artifacts->first(), 'artifact')->create();

    Livewire::test(Dashboard::class)
        ->assertSee('Feedback to review')
        ->assertSee('Busy project')
        ->assertSee('Quiet project')
        ->assertSeeInOrder(['Busy project', 'Quiet project']);
});

test('resolved threads are excluded from the feedback queue', function () {
    $this->actingAs(User::factory()->create());

    $project = Project::factory()
        ->has(Artifact::factory())
        ->create(['title' => 'Settled project', 'last_viewed_at' => now()]);
    Comment::factory()->for($project->artifacts->first(), 'artifact')->resolved()->create();

    Livewire::test(Dashboard::class)
        ->assertDontSee('Feedback to review')
        ->assertSee("You're all caught up");
});

test('empty active projects surface in the finish-setup queue', function () {
    $this->actingAs(User::factory()->create());

    Project::factory()->create(['title' => 'Needs content project']);

    Livewire::test(Dashboard::class)
        ->assertSee('Finish setup')
        ->assertSee('Needs content project');
});

test('built but never-viewed projects surface in the ready-to-share queue', function () {
    $this->actingAs(User::factory()->create());

    Project::factory()
        ->has(Artifact::factory())
        ->create(['title' => 'Unopened project', 'last_viewed_at' => null]);

    Livewire::test(Dashboard::class)
        ->assertSee('Ready to share')
        ->assertSee('Unopened project');
});

test('viewed projects with content do not appear in any action queue', function () {
    $this->actingAs(User::factory()->create());

    Project::factory()
        ->has(Artifact::factory())
        ->create(['title' => 'Handled project', 'last_viewed_at' => now()]);

    Livewire::test(Dashboard::class)
        ->assertDontSee('Handled project')
        ->assertSee("You're all caught up");
});

test('an all-clear dashboard shows the caught-up state', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Dashboard::class)
        ->assertSee("You're all caught up")
        ->assertDontSee('Feedback to review');
});
