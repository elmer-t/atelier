<?php

use App\Enums\AssetOrigin;
use App\Enums\AssetPlacement;
use App\Models\Asset;
use App\Models\Project;

it('renders a markdown asset in the stage', function () {
    $project = Project::factory()->public()->create();
    Asset::factory()->for($project)->markdown('# Hello stage')->create(['title' => 'Brief']);

    $this->get(route('project.show', $project))
        ->assertOk()
        ->assertSee('Hello stage');
});

it('embeds an html asset via the sandbox origin', function () {
    $project = Project::factory()->public()->create();
    $asset = Asset::factory()->for($project)->html()->create(['title' => 'Mockup']);

    $this->get(route('project.asset', [$project, $asset]))
        ->assertOk()
        ->assertSee($asset->sandboxUrl(), escape: false);
});

it('shows staged files in the sidebar and download-only files in the downloads list', function () {
    $project = Project::factory()->public()->create();
    $staged = Asset::factory()->for($project)->file(AssetPlacement::Stage)->create(['title' => 'Diagram']);
    $download = Asset::factory()->for($project)->download()->create(['title' => 'Assets Zip']);

    $response = $this->get(route('project.show', $project))->assertOk();

    // Staged file is a stage link; download-only file links to the download route.
    $response->assertSee(route('project.asset', [$project, $staged]), escape: false);
    $response->assertSee(route('project.asset.download', [$project, $download]), escape: false);
    // The download-only file is absent from the stage sidebar (proven further by
    // the stage route 404ing for it).
    $response->assertDontSee('>Assets Zip<', escape: false);
});

it('404s the stage route for a download-only file', function () {
    $project = Project::factory()->public()->create();
    $download = Asset::factory()->for($project)->download()->create();

    $this->get(route('project.asset', [$project, $download]))->assertNotFound();
});

it('maps each type to the correct origin', function () {
    expect(Asset::factory()->markdown()->make()->origin())->toBe(AssetOrigin::App)
        ->and(Asset::factory()->html()->make()->origin())->toBe(AssetOrigin::Sandbox)
        ->and(Asset::factory()->file()->make()->origin())->toBe(AssetOrigin::App);
});

it('defaults file placement from the mime type', function () {
    expect(AssetPlacement::defaultForMime('image/png'))->toBe(AssetPlacement::Stage)
        ->and(AssetPlacement::defaultForMime('application/pdf'))->toBe(AssetPlacement::Stage)
        ->and(AssetPlacement::defaultForMime('application/zip'))->toBe(AssetPlacement::Download)
        ->and(AssetPlacement::defaultForMime(null))->toBe(AssetPlacement::Download);
});
