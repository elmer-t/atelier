<?php

use App\Enums\AssetPlacement;
use App\Livewire\Admin\Projects\AssetsManager;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->project = Project::factory()->create();
});

it('adds a markdown asset from the textarea', function () {
    Livewire::test(AssetsManager::class, ['project' => $this->project])
        ->call('startCreate', 'markdown')
        ->set('assetTitle', 'Design Brief')
        ->set('body', '# The brief')
        ->call('save')
        ->assertHasNoErrors();

    $asset = $this->project->assets()->first();
    expect($asset->title)->toBe('Design Brief')
        ->and($asset->isMarkdown())->toBeTrue()
        ->and($asset->body)->toBe('# The brief');
});

it('adds an html asset from an uploaded zip', function () {
    $zipPath = sys_get_temp_dir().'/am-'.uniqid().'.zip';
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE);
    $zip->addFromString('index.html', '<h1>Mock</h1>');
    $zip->close();

    $upload = UploadedFile::fake()->createWithContent('bundle.zip', file_get_contents($zipPath));

    Livewire::test(AssetsManager::class, ['project' => $this->project])
        ->call('startCreate', 'html')
        ->set('assetTitle', 'Homepage Mockup')
        ->set('zipFile', $upload)
        ->call('save')
        ->assertHasNoErrors();

    $asset = $this->project->assets()->first();
    expect($asset->isHtml())->toBeTrue()
        ->and($asset->entry_file)->toBe('index.html')
        ->and($asset->bundle_path)->toBe($this->project->sandbox_token.'/'.$asset->id);
});

it('adds a file asset and defaults its placement from the mime type', function () {
    Storage::fake('local');

    Livewire::test(AssetsManager::class, ['project' => $this->project])
        ->call('startCreate', 'file')
        ->set('assetTitle', 'Logo')
        ->set('file', UploadedFile::fake()->image('logo.png'))
        ->assertSet('placement', AssetPlacement::Stage->value)
        ->call('save')
        ->assertHasNoErrors();

    $asset = $this->project->assets()->first();
    expect($asset->isFile())->toBeTrue()
        ->and($asset->placement)->toBe(AssetPlacement::Stage)
        ->and($asset->original_filename)->toBe('logo.png');
    Storage::disk('local')->assertExists($asset->stored_path);
});

it('adds a download-only file when placement is set to download', function () {
    Storage::fake('local');

    Livewire::test(AssetsManager::class, ['project' => $this->project])
        ->call('startCreate', 'file')
        ->set('assetTitle', 'Spec')
        ->set('file', UploadedFile::fake()->create('spec.pdf', 20, 'application/pdf'))
        ->set('placement', AssetPlacement::Download->value)
        ->call('save')
        ->assertHasNoErrors();

    $asset = $this->project->assets()->first();
    expect($asset->isDownload())->toBeTrue()
        ->and($asset->original_filename)->toBe('spec.pdf')
        ->and($asset->mime_type)->toBe('application/pdf')
        ->and($asset->size_bytes)->toBe(20 * 1024);
    Storage::disk('local')->assertExists($asset->stored_path);
});

it('reorders assets across types', function () {
    $a = $this->project->assets()->create(['title' => 'A', 'type' => 'markdown', 'sort_order' => 1]);
    $b = $this->project->assets()->create(['title' => 'B', 'type' => 'markdown', 'sort_order' => 2]);

    Livewire::test(AssetsManager::class, ['project' => $this->project])->call('moveUp', $b->id);

    expect($this->project->assets()->pluck('title')->all())->toBe(['B', 'A']);
});

it('deletes an asset and its stored file', function () {
    Storage::fake('local');

    $path = UploadedFile::fake()->create('gone.pdf', 5)->storeAs("assets/{$this->project->id}", 'gone.pdf', 'local');
    $asset = $this->project->assets()->create([
        'title' => 'Gone',
        'type' => 'file',
        'placement' => AssetPlacement::Download,
        'stored_path' => $path,
        'original_filename' => 'gone.pdf',
    ]);

    Livewire::test(AssetsManager::class, ['project' => $this->project])->call('deleteAsset', $asset->id);

    expect($this->project->assets()->count())->toBe(0);
    Storage::disk('local')->assertMissing($path);
});
