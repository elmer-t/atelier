<?php

use App\Models\Project;

it('renders a friendly branded 404 for an archived project link', function () {
    $project = Project::factory()->public()->archived()->create();

    $response = $this->get(route('project.show', $project))->assertNotFound();

    // Branded, audience-appropriate copy — not the bare Laravel screen.
    $response->assertSee('This page is not available');
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

it('writes the error pages in Simplified Technical English', function (string $code, string $sentence) {
    $html = view("errors.{$code}")->render();

    expect($html)->toContain($sentence);

    // ASD-STE100 forbids contractions, courtesy words and idioms in the body copy.
    foreach (['isn&#039;t', 'don&#039;t', 'that&#039;s', 'please', 'Please', 'head back', 'Head back'] as $banned) {
        expect($html)->not->toContain($banned);
    }
})->with([
    ['404', 'The link is not correct, or the content is not available.'],
    ['403', 'Your link does not give access to this page.'],
    ['419', 'The page was open too long.'],
    ['500', 'The error is in our system.'],
]);
