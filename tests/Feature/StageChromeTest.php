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

/**
 * The bar names the app, the project and the panel each toggle opens, standing directly
 * above the columns it describes. The pages panel used to repeat all three in its own
 * header; it now opens straight onto the list of pages.
 */
it('leaves the naming to the bar and starts the pages panel on its list', function () {
    $project = Project::factory()->public()->create(['title' => 'Harbor District']);
    Artifact::factory()->for($project)->markdown('# Brief')->create(['title' => 'Concept Brief']);

    $html = $this->get(route('project.show', $project))->assertOk()->getContent();

    preg_match('/<aside data-stage-nav.*?<\/aside>/s', $html, $match);
    $sidebar = $match[0] ?? '';

    expect($sidebar)->toContain('Concept Brief')
        ->and($sidebar)->not->toContain('Harbor District')
        ->and($sidebar)->not->toContain(config('app.name'))
        ->and($sidebar)->not->toContain('Pages');
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
