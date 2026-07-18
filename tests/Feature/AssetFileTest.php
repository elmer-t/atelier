<?php

use App\Enums\AssetPlacement;
use App\Models\Asset;
use App\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function storeFileAsset(Project $project, AssetPlacement $placement = AssetPlacement::Download): Asset
{
    $path = UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf')
        ->storeAs("assets/{$project->id}", 'brief.pdf', 'local');

    return $project->assets()->create([
        'type' => 'file',
        'placement' => $placement,
        'title' => 'Brief',
        'original_filename' => 'brief.pdf',
        'stored_path' => $path,
        'mime_type' => 'application/pdf',
        'size_bytes' => 10,
    ]);
}

it('downloads a file asset for a public project', function () {
    $project = Project::factory()->public()->create();
    $asset = storeFileAsset($project);

    $this->get(route('project.asset.download', [$project, $asset]))
        ->assertOk()
        ->assertDownload('brief.pdf');
});

it('streams a staged file asset inline', function () {
    $project = Project::factory()->public()->create();
    $asset = storeFileAsset($project, AssetPlacement::Stage);

    $response = $this->get(route('project.asset.file', [$project, $asset]));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toStartWith('inline');
});

it('gates file downloads for a private project', function () {
    $project = Project::factory()->private('secret')->create();
    $asset = storeFileAsset($project);

    $this->get(route('project.asset.download', [$project, $asset]))
        ->assertRedirect(route('project.gate', $project));

    $this->post(route('project.unlock', $project), ['password' => 'secret']);

    $this->get(route('project.asset.download', [$project, $asset]))->assertOk();
});

it('404s file downloads for an archived project', function () {
    $project = Project::factory()->public()->archived()->create();
    $asset = storeFileAsset($project);

    $this->get(route('project.asset.download', [$project, $asset]))->assertNotFound();
});

it('does not allow a file asset from another project', function () {
    $project = Project::factory()->public()->create();
    $other = Project::factory()->public()->create();
    $foreign = storeFileAsset($other);

    $this->get(route('project.asset.download', [$project, $foreign]))->assertNotFound();
});

it('404s the file routes for a non-file asset', function () {
    $project = Project::factory()->public()->create();
    $markdown = Asset::factory()->for($project)->markdown()->create();

    $this->get(route('project.asset.file', [$project, $markdown]))->assertNotFound();
    $this->get(route('project.asset.download', [$project, $markdown]))->assertNotFound();
});
