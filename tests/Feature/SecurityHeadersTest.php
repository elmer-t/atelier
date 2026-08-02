<?php

use App\Models\Artifact;
use App\Models\Project;
use App\Models\User;

it('carries the baseline security headers on an app-origin response', function () {
    $project = Project::factory()->public()->create();
    Artifact::factory()->for($project)->markdown('# Brief')->create();

    $response = $this->get(route('project.show', $project))->assertOk();

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)->not->toBeNull()
        ->and($csp)->toContain("default-src 'self'")
        ->and($csp)->toContain("object-src 'none'")
        ->and($csp)->toContain("frame-ancestors 'self'");
});

it('lets the HTML-artifact iframe reach the sandbox origin under the policy', function () {
    config(['atelier.sandbox.url' => 'https://sandbox.example.test']);

    $project = Project::factory()->public()->create();

    $csp = $this->get(route('project.show', $project))
        ->assertOk()
        ->headers->get('Content-Security-Policy');

    expect($csp)->toContain('frame-src')
        ->and($csp)->toContain('https://sandbox.example.test');
});

it('also decorates the admin area', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});
