<?php

use App\Models\Artifact;
use App\Models\Project;

it('gives the stage a bar carrying the project, the current artifact and the way back', function () {
    $project = Project::factory()->public()->create(['title' => 'Harbor District']);
    Artifact::factory()->for($project)->markdown('# Brief')->create(['title' => 'Concept Brief']);

    $this->get(route('project.show', $project))
        ->assertOk()
        ->assertSee('stage-bar', escape: false)
        ->assertSee('Harbor District')
        ->assertSee('Concept Brief')
        ->assertSee(route('home'), escape: false);
});

it('offers a control for each panel and for focus mode', function () {
    $project = Project::factory()->public()->create();
    Artifact::factory()->for($project)->markdown('# Brief')->create();

    $response = $this->get(route('project.show', $project))->assertOk();

    $response->assertSee('Toggle pages panel');
    $response->assertSee('Toggle feedback panel');
    $response->assertSee('Toggle focus mode');

    // The panels answer to the state published on the chrome wrapper, not to bindings
    // on the panels themselves — the rail is a Livewire root and a morph would drop them.
    $response->assertSee('data-stage-pages', escape: false);
    $response->assertSee('data-stage-feedback', escape: false);
    $response->assertSee('data-stage-nav', escape: false);
});

it('offers all three appearances', function () {
    $project = Project::factory()->public()->create();
    Artifact::factory()->for($project)->markdown('# Brief')->create();

    $response = $this->get(route('project.show', $project))->assertOk();

    foreach (['System', 'Light', 'Dark'] as $appearance) {
        $response->assertSee('aria-label="'.$appearance.'"', escape: false);
    }
});

it('does not double the way back now that the bar carries it', function () {
    $project = Project::factory()->public()->create();
    Artifact::factory()->for($project)->markdown('# Brief')->create();

    $this->get(route('project.show', $project))
        ->assertOk()
        ->assertDontSee('Back to '.config('app.name'));
});

it('bars a project with no pages at all, so the way out never depends on content', function () {
    $project = Project::factory()->public()->create(['title' => 'Empty Project']);

    $this->get(route('project.show', $project))
        ->assertOk()
        ->assertSee('stage-bar', escape: false)
        ->assertSee('Empty Project')
        ->assertSee(route('home'), escape: false);
});
