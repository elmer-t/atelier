<?php

use App\Models\Artifact;
use App\Models\Project;

it('resolves the header image url when the header artifact is an image', function () {
    $project = Project::factory()->public()->create();
    $cover = Artifact::factory()->for($project)->file()->create();
    $project->forceFill(['header_artifact_id' => $cover->id])->save();

    expect($project->refresh()->headerImageUrl())
        ->toBe(route('project.artifact.file', [$project, $cover]));
});

it('has no header image url when the header artifact is not an image', function () {
    $project = Project::factory()->public()->create();
    $doc = Artifact::factory()->for($project)->markdown('# Brief')->create();
    $project->forceFill(['header_artifact_id' => $doc->id])->save();

    expect($project->refresh()->headerImageUrl())->toBeNull();
});

it('has no header image url when the project has no header artifact', function () {
    $project = Project::factory()->public()->create();

    expect($project->headerImageUrl())->toBeNull();
});

it('renders the cover image on the public index when set', function () {
    $project = Project::factory()->public()->create(['title' => 'Covered Project']);
    $cover = Artifact::factory()->for($project)->file()->create();
    $project->forceFill(['header_artifact_id' => $cover->id])->save();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('project.artifact.file', [$project, $cover]), escape: false);
});

it('falls back to the generated cover when no header image is set', function () {
    Project::factory()->public()->create(['title' => 'Bare Project']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Bare Project')
        ->assertDontSee('/file', escape: false);
});

it('nulls the header reference when the cover artifact is deleted', function () {
    $project = Project::factory()->public()->create();
    $cover = Artifact::factory()->for($project)->file()->create();
    $project->forceFill(['header_artifact_id' => $cover->id])->save();

    $cover->delete();

    expect($project->refresh()->header_artifact_id)->toBeNull();
});
