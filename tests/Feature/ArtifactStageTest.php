<?php

use App\Enums\ArtifactOrigin;
use App\Enums\ArtifactPlacement;
use App\Models\Artifact;
use App\Models\Project;
use App\Models\User;
use App\Support\Artifacts\MarkdownRevisionWriter;

it('renders a markdown artifact in the stage', function () {
    $project = Project::factory()->public()->create();
    Artifact::factory()->for($project)->markdown('# Hello stage')->create(['title' => 'Brief']);

    $this->get(route('project.show', $project))
        ->assertOk()
        ->assertSee('Hello stage');
});

it('embeds an html artifact via the sandbox origin', function () {
    $project = Project::factory()->public()->create();
    $artifact = Artifact::factory()->for($project)->html()->create(['title' => 'Mockup']);

    $this->get(route('project.artifact', [$project, $artifact]))
        ->assertOk()
        ->assertSee($artifact->sandboxUrl(), escape: false);
});

it('shows staged files in the sidebar and download-only files in the downloads list', function () {
    $project = Project::factory()->public()->create();
    $staged = Artifact::factory()->for($project)->file(ArtifactPlacement::Stage)->create(['title' => 'Diagram']);
    $download = Artifact::factory()->for($project)->download()->create(['title' => 'Artifacts Zip']);

    $response = $this->get(route('project.show', $project))->assertOk();

    // Staged file is a stage link; download-only file links to the download route.
    $response->assertSee(route('project.artifact', [$project, $staged]), escape: false);
    $response->assertSee(route('project.artifact.download', [$project, $download]), escape: false);
    // The download-only file is absent from the stage sidebar (proven further by
    // the stage route 404ing for it).
    $response->assertDontSee('>Artifacts Zip<', escape: false);
});

it('404s the stage route for a download-only file', function () {
    $project = Project::factory()->public()->create();
    $download = Artifact::factory()->for($project)->download()->create();

    $this->get(route('project.artifact', [$project, $download]))->assertNotFound();
});

it('maps each type to the correct origin', function () {
    expect(Artifact::factory()->markdown()->make()->origin())->toBe(ArtifactOrigin::App)
        ->and(Artifact::factory()->html()->make()->origin())->toBe(ArtifactOrigin::Sandbox)
        ->and(Artifact::factory()->file()->make()->origin())->toBe(ArtifactOrigin::App);
});

it('defaults file placement from the mime type', function () {
    expect(ArtifactPlacement::defaultForMime('image/png'))->toBe(ArtifactPlacement::Stage)
        ->and(ArtifactPlacement::defaultForMime('application/pdf'))->toBe(ArtifactPlacement::Stage)
        ->and(ArtifactPlacement::defaultForMime('application/zip'))->toBe(ArtifactPlacement::Download)
        ->and(ArtifactPlacement::defaultForMime(null))->toBe(ArtifactPlacement::Download);
});

it('renders the latest Revision of a markdown artifact on the shared page', function () {
    $project = Project::factory()->public()->create();
    $author = User::factory()->create();
    $artifact = Artifact::factory()->for($project)->markdown('# Old heading')->create(['title' => 'Brief']);

    app(MarkdownRevisionWriter::class)->update($artifact, '# New heading', $author);

    $this->get(route('project.artifact', [$project, $artifact]))
        ->assertOk()
        ->assertSee('New heading')
        ->assertDontSee('Old heading');
});
