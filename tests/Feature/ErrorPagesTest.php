<?php

use App\Models\Project;

it('renders a friendly branded 404 for an archived project link', function () {
    $project = Project::factory()->public()->archived()->create();

    $response = $this->get(route('project.show', $project))->assertNotFound();

    // Branded, audience-appropriate copy — not the bare Laravel screen.
    $response->assertSee('This project');
    $response->assertSee('available');
    $response->assertSee(config('app.name', 'Atelier'));
    // Generic on purpose: never state this project's actual status (specs §10).
    $response->assertDontSee('This project has been archived');
});

it('renders a friendly branded 404 for a hard-disabled project gate', function () {
    $project = Project::factory()->private('secret')->archived()->create();

    $this->get(route('project.gate', $project))
        ->assertNotFound()
        ->assertSee(config('app.name', 'Atelier'));
});

it('serves branded pages for 419, 403 and 500', function () {
    foreach (['419', '403', '500'] as $code) {
        $html = view("errors.{$code}")->render();

        expect($html)
            ->toContain($code)
            ->toContain(config('app.name', 'Atelier'));
    }
});
