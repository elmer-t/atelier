<?php

use App\Models\Asset;
use App\Models\Project;
use App\Services\BundleUnpacker;
use Illuminate\Support\Facades\File;

/**
 * Build a zip on disk and return its path.
 *
 * @param  array<string, string>  $files  entry name => contents
 * @param  array<string>  $symlinks  entry names to flag as unix symlinks
 */
function makeZip(array $files, array $symlinks = []): string
{
    $path = sys_get_temp_dir().'/atelier-bundle-'.uniqid().'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);

    foreach ($files as $name => $contents) {
        $zip->addFromString($name, $contents);
    }

    foreach ($symlinks as $name) {
        $zip->addFromString($name, '/etc/passwd');
        $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, (0xA000 | 0o777) << 16);
    }

    $zip->close();

    return $path;
}

beforeEach(function () {
    $this->unpacker = new BundleUnpacker;
    $this->project = Project::factory()->create();
    $this->page = Asset::factory()->for($this->project)->html()->create(['bundle_path' => null, 'entry_file' => null]);
});

it('unpacks a bundle and auto-detects index.html', function () {
    $zip = makeZip([
        'index.html' => '<h1>Mock</h1>',
        'assets/app.css' => 'body{}',
    ]);

    $result = $this->unpacker->unpack($zip, $this->project, $this->page);

    expect($result['entry_file'])->toBe('index.html')
        ->and($result['bundle_path'])->toBe($this->project->sandbox_token.'/'.$this->page->id);

    $dir = config('atelier.sandbox.path').'/'.$result['bundle_path'];
    expect(File::exists($dir.'/index.html'))->toBeTrue()
        ->and(File::exists($dir.'/assets/app.css'))->toBeTrue();
});

it('strips symlink entries', function () {
    $zip = makeZip(['index.html' => 'ok'], symlinks: ['evil-link']);

    $result = $this->unpacker->unpack($zip, $this->project, $this->page);

    $dir = config('atelier.sandbox.path').'/'.$result['bundle_path'];
    expect(File::exists($dir.'/evil-link'))->toBeFalse();
});

it('honours an explicit entry file', function () {
    $zip = makeZip(['home.html' => 'ok', 'other.html' => 'x']);

    $result = $this->unpacker->unpack($zip, $this->project, $this->page, 'home.html');

    expect($result['entry_file'])->toBe('home.html');
});

it('throws when the specified entry file is missing', function () {
    $zip = makeZip(['index.html' => 'ok']);

    $this->unpacker->unpack($zip, $this->project, $this->page, 'missing.html');
})->throws(RuntimeException::class);

it('throws when no html file is present', function () {
    $zip = makeZip(['readme.txt' => 'ok']);

    $this->unpacker->unpack($zip, $this->project, $this->page);
})->throws(RuntimeException::class);

it('replaces an existing bundle on re-upload', function () {
    $first = $this->unpacker->unpack(makeZip(['index.html' => 'v1', 'old.html' => 'gone']), $this->project, $this->page);
    $this->page->update($first);

    $second = $this->unpacker->unpack(makeZip(['index.html' => 'v2']), $this->project, $this->page);

    $dir = config('atelier.sandbox.path').'/'.$second['bundle_path'];
    expect(File::get($dir.'/index.html'))->toBe('v2')
        ->and(File::exists($dir.'/old.html'))->toBeFalse();
});
