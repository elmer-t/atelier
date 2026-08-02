<?php

use App\Enums\ArtifactPlacement;
use App\Models\Artifact;
use App\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function storeFileArtifact(Project $project, ArtifactPlacement $placement = ArtifactPlacement::Download): Artifact
{
    $path = UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf')
        ->storeAs("artifacts/{$project->id}", 'brief.pdf', 'local');

    return $project->artifacts()->create([
        'type' => 'file',
        'placement' => $placement,
        'title' => 'Brief',
        'original_filename' => 'brief.pdf',
        'stored_path' => $path,
        'mime_type' => 'application/pdf',
        'size_bytes' => 10,
    ]);
}

it('downloads a file artifact for a public project', function () {
    $project = Project::factory()->public()->create();
    $artifact = storeFileArtifact($project);

    $this->get(route('project.artifact.download', [$project, $artifact]))
        ->assertOk()
        ->assertDownload('brief.pdf');
});

it('streams a staged file artifact inline', function () {
    $project = Project::factory()->public()->create();
    $artifact = storeFileArtifact($project, ArtifactPlacement::Stage);

    $response = $this->get(route('project.artifact.file', [$project, $artifact]));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toStartWith('inline');
});

it('sends nosniff so an inline file cannot be sniffed into executable HTML', function () {
    $project = Project::factory()->public()->create();
    $artifact = storeFileArtifact($project, ArtifactPlacement::Stage);

    $this->get(route('project.artifact.file', [$project, $artifact]))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('sends nosniff on the forced download too', function () {
    $project = Project::factory()->public()->create();
    $artifact = storeFileArtifact($project);

    $this->get(route('project.artifact.download', [$project, $artifact]))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('gates file downloads for a private project', function () {
    $project = Project::factory()->private('secret')->create();
    $artifact = storeFileArtifact($project);

    $this->get(route('project.artifact.download', [$project, $artifact]))
        ->assertRedirect(route('project.gate', $project));

    $this->post(route('project.unlock', $project), ['password' => 'secret']);

    $this->get(route('project.artifact.download', [$project, $artifact]))->assertOk();
});

it('404s file downloads for an archived project', function () {
    $project = Project::factory()->public()->archived()->create();
    $artifact = storeFileArtifact($project);

    $this->get(route('project.artifact.download', [$project, $artifact]))->assertNotFound();
});

it('does not allow a file artifact from another project', function () {
    $project = Project::factory()->public()->create();
    $other = Project::factory()->public()->create();
    $foreign = storeFileArtifact($other);

    $this->get(route('project.artifact.download', [$project, $foreign]))->assertNotFound();
});

it('404s the file routes for a non-file artifact', function () {
    $project = Project::factory()->public()->create();
    $markdown = Artifact::factory()->for($project)->markdown()->create();

    $this->get(route('project.artifact.file', [$project, $markdown]))->assertNotFound();
    $this->get(route('project.artifact.download', [$project, $markdown]))->assertNotFound();
});
