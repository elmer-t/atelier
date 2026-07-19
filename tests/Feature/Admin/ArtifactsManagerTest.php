<?php

use App\Enums\ArtifactPlacement;
use App\Livewire\Admin\Projects\ArtifactsManager;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->project = Project::factory()->create();
});

it('adds a markdown artifact from the textarea', function () {
    Livewire::test(ArtifactsManager::class, ['project' => $this->project])
        ->call('startCreate', 'markdown')
        ->set('artifactTitle', 'Design Brief')
        ->set('body', '# The brief')
        ->call('save')
        ->assertHasNoErrors();

    $artifact = $this->project->artifacts()->first();
    expect($artifact->title)->toBe('Design Brief')
        ->and($artifact->isMarkdown())->toBeTrue()
        ->and($artifact->body)->toBe('# The brief');
});

it('adds an html artifact from an uploaded zip', function () {
    $zipPath = sys_get_temp_dir().'/am-'.uniqid().'.zip';
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE);
    $zip->addFromString('index.html', '<h1>Mock</h1>');
    $zip->close();

    $upload = UploadedFile::fake()->createWithContent('bundle.zip', file_get_contents($zipPath));

    Livewire::test(ArtifactsManager::class, ['project' => $this->project])
        ->call('startCreate', 'html')
        ->set('artifactTitle', 'Homepage Mockup')
        ->set('zipFile', $upload)
        ->call('save')
        ->assertHasNoErrors();

    $artifact = $this->project->artifacts()->first();
    expect($artifact->isHtml())->toBeTrue()
        ->and($artifact->entry_file)->toBe('index.html')
        ->and($artifact->bundle_path)->toBe($this->project->sandbox_token.'/'.$artifact->id);
});

it('adds a file artifact and defaults its placement from the mime type', function () {
    Storage::fake('local');

    Livewire::test(ArtifactsManager::class, ['project' => $this->project])
        ->call('startCreate', 'file')
        ->set('artifactTitle', 'Logo')
        ->set('file', UploadedFile::fake()->image('logo.png'))
        ->assertSet('placement', ArtifactPlacement::Stage->value)
        ->call('save')
        ->assertHasNoErrors();

    $artifact = $this->project->artifacts()->first();
    expect($artifact->isFile())->toBeTrue()
        ->and($artifact->placement)->toBe(ArtifactPlacement::Stage)
        ->and($artifact->original_filename)->toBe('logo.png');
    Storage::disk('local')->assertExists($artifact->stored_path);
});

it('adds a download-only file when placement is set to download', function () {
    Storage::fake('local');

    Livewire::test(ArtifactsManager::class, ['project' => $this->project])
        ->call('startCreate', 'file')
        ->set('artifactTitle', 'Spec')
        ->set('file', UploadedFile::fake()->create('spec.pdf', 20, 'application/pdf'))
        ->set('placement', ArtifactPlacement::Download->value)
        ->call('save')
        ->assertHasNoErrors();

    $artifact = $this->project->artifacts()->first();
    expect($artifact->isDownload())->toBeTrue()
        ->and($artifact->original_filename)->toBe('spec.pdf')
        ->and($artifact->mime_type)->toBe('application/pdf')
        ->and($artifact->size_bytes)->toBe(20 * 1024);
    Storage::disk('local')->assertExists($artifact->stored_path);
});

it('reorders artifacts across types', function () {
    $a = $this->project->artifacts()->create(['title' => 'A', 'type' => 'markdown', 'sort_order' => 1]);
    $b = $this->project->artifacts()->create(['title' => 'B', 'type' => 'markdown', 'sort_order' => 2]);

    Livewire::test(ArtifactsManager::class, ['project' => $this->project])->call('moveUp', $b->id);

    expect($this->project->artifacts()->pluck('title')->all())->toBe(['B', 'A']);
});

it('deletes an artifact and its stored file', function () {
    Storage::fake('local');

    $path = UploadedFile::fake()->create('gone.pdf', 5)->storeAs("artifacts/{$this->project->id}", 'gone.pdf', 'local');
    $artifact = $this->project->artifacts()->create([
        'title' => 'Gone',
        'type' => 'file',
        'placement' => ArtifactPlacement::Download,
        'stored_path' => $path,
        'original_filename' => 'gone.pdf',
    ]);

    Livewire::test(ArtifactsManager::class, ['project' => $this->project])->call('deleteArtifact', $artifact->id);

    expect($this->project->artifacts()->count())->toBe(0);
    Storage::disk('local')->assertMissing($path);
});
