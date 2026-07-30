<?php

use App\Livewire\Admin\Projects\Index;
use App\Models\Artifact;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('lists the newest projects first by default', function () {
    Project::factory()->create(['title' => 'Older', 'created_at' => now()->subWeek()]);
    Project::factory()->create(['title' => 'Newer', 'created_at' => now()]);

    Livewire::test(Index::class)
        ->assertSet('sortBy', 'created_at')
        ->assertSet('sortDirection', 'desc')
        ->assertSeeInOrder(['Newer', 'Older']);
});

it('sorts by title and flips direction when the same column is clicked again', function () {
    Project::factory()->create(['title' => 'Zephyr']);
    Project::factory()->create(['title' => 'Apex']);

    Livewire::test(Index::class)
        ->call('sort', 'title')
        ->assertSet('sortBy', 'title')
        ->assertSet('sortDirection', 'asc')
        ->assertSeeInOrder(['Apex', 'Zephyr'])
        ->call('sort', 'title')
        ->assertSet('sortDirection', 'desc')
        ->assertSeeInOrder(['Zephyr', 'Apex']);
});

it('starts each column at its most useful direction', function () {
    Livewire::test(Index::class)
        ->call('sort', 'last_viewed_at')
        ->assertSet('sortDirection', 'desc')
        ->call('sort', 'title')
        ->assertSet('sortDirection', 'asc');
});

it('sorts by artifact count', function () {
    Project::factory()->has(Artifact::factory()->count(3))->create(['title' => 'Busy']);
    Project::factory()->create(['title' => 'Empty']);

    Livewire::test(Index::class)
        ->call('sort', 'artifacts_count')
        ->assertSeeInOrder(['Busy', 'Empty']);
});

it('ignores a column that is not sortable', function () {
    Livewire::test(Index::class)
        ->call('sort', 'password_hash')
        ->assertSet('sortBy', 'created_at');
});

it('falls back to the default ordering when the query string names an unsortable column', function () {
    Project::factory()->create(['title' => 'Older', 'created_at' => now()->subWeek()]);
    Project::factory()->create(['title' => 'Newer', 'created_at' => now()]);

    Livewire::withQueryParams(['sortBy' => 'password_hash'])
        ->test(Index::class)
        ->assertSeeInOrder(['Newer', 'Older']);
});

it('filters by a title search', function () {
    Project::factory()->create(['title' => 'Harbor District']);
    Project::factory()->create(['title' => 'Mountain Lodge']);

    Livewire::test(Index::class)
        ->set('search', 'harbor')
        ->assertSee('Harbor District')
        ->assertDontSee('Mountain Lodge');
});

it('filters by status', function () {
    Project::factory()->create(['title' => 'Live Work']);
    Project::factory()->archived()->create(['title' => 'Shelved Work']);

    Livewire::test(Index::class)
        ->set('status', 'archived')
        ->assertSee('Shelved Work')
        ->assertDontSee('Live Work');
});

it('filters by visibility', function () {
    Project::factory()->public()->create(['title' => 'Open House']);
    Project::factory()->private()->create(['title' => 'Sealed Envelope']);

    Livewire::test(Index::class)
        ->set('visibility', 'public')
        ->assertSee('Open House')
        ->assertDontSee('Sealed Envelope');
});

it('ignores a filter value that is not a known enum case', function () {
    Project::factory()->create(['title' => 'Harbor District']);

    Livewire::withQueryParams(['status' => 'nonsense'])
        ->test(Index::class)
        ->assertSee('Harbor District');
});

it('explains an empty result set differently when filters are applied', function () {
    Project::factory()->create(['title' => 'Harbor District']);

    Livewire::test(Index::class)
        ->set('search', 'nothing matches this')
        ->assertSee('No projects match these filters.')
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSee('Harbor District');
});

it('tells a first-time creator there are no projects yet', function () {
    Livewire::test(Index::class)->assertSee('No projects yet.');
});

it('renders the full page with sortable headers and the filter controls', function () {
    Project::factory()->create(['title' => 'Harbor District']);

    $this->get(route('admin.projects'))
        ->assertOk()
        ->assertSee('Harbor District')
        ->assertSee('wire:click="sort(\'title\')"', escape: false)
        ->assertSee('wire:model.live="status"', escape: false)
        ->assertSee('wire:model.live="visibility"', escape: false);
});

it('paginates the list and returns to the first page when a filter changes', function () {
    Project::factory()->count(20)->create();

    Livewire::test(Index::class)
        ->assertCount('projects', 15)
        ->set('paginators.page', 2)
        ->assertCount('projects', 5)
        ->set('search', 'a')
        ->assertSet('paginators.page', 1);
});

it('copies the shareable link with a fallback for insecure contexts', function () {
    $project = Project::factory()->public()->create();

    $this->get(route('admin.projects'))
        ->assertOk()
        ->assertSee('Copy link')
        ->assertSee(route('project.show', $project))
        ->assertSee('window.isSecureContext', escape: false)
        ->assertSee("document.execCommand('copy')", escape: false);
});
