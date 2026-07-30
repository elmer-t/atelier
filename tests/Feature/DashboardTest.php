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

test('feedback queue lists artifacts with unresolved threads, most first', function () {
    $this->actingAs(User::factory()->create());

    $quiet = Project::factory()->has(Artifact::factory(['title' => 'Quiet artifact']))->create(['title' => 'Quiet project']);
    $busy = Project::factory()->has(Artifact::factory(['title' => 'Busy artifact']))->create(['title' => 'Busy project']);

    Comment::factory()->for($quiet->artifacts->first(), 'artifact')->create();
    Comment::factory()->count(3)->for($busy->artifacts->first(), 'artifact')->create();

    Livewire::test(Dashboard::class)
        ->assertSee('Feedback to review')
        ->assertSee('Busy artifact')
        ->assertSee('Busy project')
        ->assertSee('Quiet artifact')
        ->assertSeeInOrder(['Busy artifact', 'Quiet artifact']);
});

test('each artifact in a project gets its own feedback row', function () {
    $this->actingAs(User::factory()->create());

    $project = Project::factory()->create(['title' => 'Shared project']);
    $brief = Artifact::factory()->for($project)->create(['title' => 'The brief']);
    $moodboard = Artifact::factory()->for($project)->create(['title' => 'The moodboard']);

    Comment::factory()->for($brief, 'artifact')->create();
    Comment::factory()->for($moodboard, 'artifact')->create();

    Livewire::test(Dashboard::class)
        ->assertSee('The brief')
        ->assertSee('The moodboard');
});

test('a feedback row links straight to the artifact the feedback sits on', function () {
    $this->actingAs(User::factory()->create());

    $project = Project::factory()->create();
    $artifact = Artifact::factory()->for($project)->create(['title' => 'The brief']);

    Comment::factory()->for($artifact, 'artifact')->create();

    Livewire::test(Dashboard::class)
        ->assertSee(route('project.artifact', ['project' => $project, 'artifact' => $artifact]), escape: false)
        ->assertDontSee(route('admin.projects.manage', $project));
});

test('a feedback row on an unavailable project is not linked', function () {
    $this->actingAs(User::factory()->create());

    $project = Project::factory()->archived()->create();
    $artifact = Artifact::factory()->for($project)->create(['title' => 'The brief']);

    Comment::factory()->for($artifact, 'artifact')->create();

    Livewire::test(Dashboard::class)
        ->assertSee('The brief')
        ->assertSee('Project unavailable')
        ->assertDontSee(route('project.artifact', ['project' => $project, 'artifact' => $artifact]), escape: false);
});

test('resolved threads are excluded from the feedback queue', function () {
    $this->actingAs(User::factory()->create());

    $project = Project::factory()
        ->has(Artifact::factory())
        ->create(['title' => 'Settled project', 'last_viewed_at' => now()]);
    Comment::factory()->for($project->artifacts->first(), 'artifact')->resolved()->create();

    Livewire::test(Dashboard::class)
        ->assertDontSee('Feedback to review')
        ->assertSee("You're all caught up", escape: false);
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
        ->assertSee("You're all caught up", escape: false);
});

test('an all-clear dashboard shows the caught-up state', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Dashboard::class)
        ->assertSee("You're all caught up", escape: false)
        ->assertDontSee('Feedback to review');
});

test('the search term filters every queue by project name', function () {
    $this->actingAs(User::factory()->create());

    $noisy = Project::factory()->has(Artifact::factory(['title' => 'Noisy deck']))->create(['title' => 'Acme redesign']);
    Comment::factory()->for($noisy->artifacts->first(), 'artifact')->create();

    Project::factory()->create(['title' => 'Globex branding']);
    Project::factory()->has(Artifact::factory())->create(['title' => 'Initech site', 'last_viewed_at' => null]);

    Livewire::test(Dashboard::class)
        ->set('search', 'Acme')
        ->assertSee('Acme redesign')
        ->assertDontSee('Globex branding')
        ->assertDontSee('Initech site');
});

test('the search term filters the feedback queue by artifact name', function () {
    $this->actingAs(User::factory()->create());

    $project = Project::factory()->create(['title' => 'Shared project']);
    $brief = Artifact::factory()->for($project)->create(['title' => 'The brief']);
    $moodboard = Artifact::factory()->for($project)->create(['title' => 'The moodboard']);

    Comment::factory()->for($brief, 'artifact')->create();
    Comment::factory()->for($moodboard, 'artifact')->create();

    Livewire::test(Dashboard::class)
        ->set('search', 'moodboard')
        ->assertSee('The moodboard')
        ->assertDontSee('The brief');
});

test('a search matching an artifact keeps its project in the ready-to-share queue', function () {
    $this->actingAs(User::factory()->create());

    Project::factory()
        ->has(Artifact::factory(['title' => 'Kickoff deck']))
        ->create(['title' => 'Unopened project', 'last_viewed_at' => null]);

    Livewire::test(Dashboard::class)
        ->set('search', 'Kickoff')
        ->assertSee('Ready to share')
        ->assertSee('Unopened project');
});

test('a search matching nothing shows the no-matches state rather than the caught-up one', function () {
    $this->actingAs(User::factory()->create());

    Project::factory()->create(['title' => 'Acme redesign']);

    Livewire::test(Dashboard::class)
        ->set('search', 'nothing-like-this')
        ->assertSee('No project or artifact matches that name.')
        ->assertDontSee("You're all caught up", escape: false);
});

test('clearing the filters restores every queue', function () {
    $this->actingAs(User::factory()->create());

    Project::factory()->create(['title' => 'Acme redesign']);

    Livewire::test(Dashboard::class)
        ->set('search', 'nothing-like-this')
        ->assertDontSee('Acme redesign')
        ->call('clearFilters')
        ->assertSee('Acme redesign');
});

test('sorting by date orders the feedback queue newest first, ignoring thread counts', function () {
    $this->actingAs(User::factory()->create());

    $busy = Project::factory()->has(Artifact::factory(['title' => 'Older busy artifact']))->create();
    $quiet = Project::factory()->has(Artifact::factory(['title' => 'Newer quiet artifact']))->create();

    Comment::factory()->count(3)->for($busy->artifacts->first(), 'artifact')->create(['created_at' => now()->subWeek()]);
    Comment::factory()->for($quiet->artifacts->first(), 'artifact')->create(['created_at' => now()]);

    Livewire::test(Dashboard::class)
        ->assertSeeInOrder(['Older busy artifact', 'Newer quiet artifact'])
        ->set('sort', 'date')
        ->assertSeeInOrder(['Newer quiet artifact', 'Older busy artifact']);
});

test('sorting by project name orders the feedback queue alphabetically', function () {
    $this->actingAs(User::factory()->create());

    $zeta = Project::factory()->has(Artifact::factory(['title' => 'Zeta artifact']))->create(['title' => 'Zeta project']);
    $alpha = Project::factory()->has(Artifact::factory(['title' => 'Alpha artifact']))->create(['title' => 'Alpha project']);

    Comment::factory()->count(3)->for($zeta->artifacts->first(), 'artifact')->create();
    Comment::factory()->for($alpha->artifacts->first(), 'artifact')->create();

    Livewire::test(Dashboard::class)
        ->set('sort', 'project')
        ->assertSeeInOrder(['Alpha artifact', 'Zeta artifact']);
});

test('sorting by artifact name orders the feedback queue alphabetically', function () {
    $this->actingAs(User::factory()->create());

    $first = Project::factory()->has(Artifact::factory(['title' => 'Alpha artifact']))->create(['title' => 'Zeta project']);
    $second = Project::factory()->has(Artifact::factory(['title' => 'Zeta artifact']))->create(['title' => 'Alpha project']);

    Comment::factory()->for($first->artifacts->first(), 'artifact')->create();
    Comment::factory()->count(3)->for($second->artifacts->first(), 'artifact')->create();

    Livewire::test(Dashboard::class)
        ->set('sort', 'artifact')
        ->assertSeeInOrder(['Alpha artifact', 'Zeta artifact']);
});

test('sorting by name orders the project queues alphabetically', function () {
    $this->actingAs(User::factory()->create());

    Project::factory()->create(['title' => 'Alpha project', 'created_at' => now()->subWeek()]);
    Project::factory()->create(['title' => 'Zeta project', 'created_at' => now()]);

    Livewire::test(Dashboard::class)
        ->assertSeeInOrder(['Zeta project', 'Alpha project'])
        ->set('sort', 'project')
        ->assertSeeInOrder(['Alpha project', 'Zeta project']);
});

test('an unknown sort in the query string falls back to priority order', function () {
    $this->actingAs(User::factory()->create());

    $quiet = Project::factory()->has(Artifact::factory(['title' => 'Quiet artifact']))->create();
    $busy = Project::factory()->has(Artifact::factory(['title' => 'Busy artifact']))->create();

    Comment::factory()->for($quiet->artifacts->first(), 'artifact')->create();
    Comment::factory()->count(3)->for($busy->artifacts->first(), 'artifact')->create();

    Livewire::test(Dashboard::class)
        ->set('sort', 'title); drop table projects;--')
        ->assertSeeInOrder(['Busy artifact', 'Quiet artifact']);
});
