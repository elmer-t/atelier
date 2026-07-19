<?php

use App\Models\Artifact;
use App\Models\Project;
use App\Services\LinkReissuer;
use Illuminate\Support\Facades\File;

/**
 * Write a fake unpacked bundle to sandbox storage for an HTML artifact and point
 * the artifact's bundle_path at it, mirroring what BundleUnpacker produces.
 */
function seedBundle(Project $project, Artifact $artifact): string
{
    $relative = $project->sandbox_token.'/'.$artifact->id;
    $dir = config('atelier.sandbox.path').'/'.$relative;

    File::ensureDirectoryExists($dir);
    File::put($dir.'/index.html', '<h1>Bundle</h1>');

    $artifact->update(['bundle_path' => $relative, 'entry_file' => 'index.html']);

    return $relative;
}

it('reissue makes the old slug 404 while the new slug works', function () {
    $project = Project::factory()->public()->create();
    Artifact::factory()->for($project)->markdown('# Live brief')->create();

    $oldSlug = $project->slug;

    app(LinkReissuer::class)->reissue($project);
    $project->refresh();

    $this->get('/p/'.$oldSlug)->assertNotFound();
    $this->get(route('project.show', $project))->assertOk()->assertSee('Live brief');
});

it('reissue relocates the sandbox bundle and re-points the artifact path', function () {
    $project = Project::factory()->public()->create();
    $artifact = Artifact::factory()->for($project)->html()->create();
    $oldRelative = seedBundle($project, $artifact);
    $oldDir = config('atelier.sandbox.path').'/'.$oldRelative;

    expect(File::exists($oldDir.'/index.html'))->toBeTrue();

    app(LinkReissuer::class)->reissue($project);
    $project->refresh();
    $artifact->refresh();

    $newRelative = $project->sandbox_token.'/'.$artifact->id;
    $newDir = config('atelier.sandbox.path').'/'.$newRelative;

    // Old sandbox path is gone; the bundle now lives under the new token.
    expect(File::exists($oldDir))->toBeFalse()
        ->and(File::exists($newDir.'/index.html'))->toBeTrue()
        ->and($artifact->bundle_path)->toBe($newRelative)
        ->and($artifact->sandboxUrl())->toContain($project->sandbox_token)
        ->and($artifact->sandboxUrl())->not->toContain($oldRelative);
});

it('reissue invalidates an unlocked private viewer session', function () {
    $project = Project::factory()->private('letmein')->create();

    $this->post(route('project.unlock', $project), ['password' => 'letmein'])
        ->assertRedirect(route('project.show', $project));
    $this->get(route('project.show', $project))->assertOk();

    app(LinkReissuer::class)->reissue($project);

    $this->get(route('project.show', $project->refresh()))
        ->assertRedirect(route('project.gate', $project));
});
