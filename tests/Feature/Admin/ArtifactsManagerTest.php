<?php

use App\Enums\ArtifactPlacement;
use App\Livewire\Admin\Projects\ArtifactsManager;
use App\Models\Artifact;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->creator = User::factory()->create();
    $this->actingAs($this->creator);
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
        ->assertHasNoErrors()
        ->assertSet('showForm', true);

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
        ->assertHasNoErrors()
        ->assertSet('showForm', true);

    $artifact = $this->project->artifacts()->first();
    expect($artifact->isDownload())->toBeTrue()
        ->and($artifact->original_filename)->toBe('spec.pdf')
        ->and($artifact->mime_type)->toBe('application/pdf')
        ->and($artifact->size_bytes)->toBe(20 * 1024);
    Storage::disk('local')->assertExists($artifact->stored_path);
});

it('rejects an HTML file upload that would run as script on the app origin', function () {
    Storage::fake('local');

    Livewire::test(ArtifactsManager::class, ['project' => $this->project])
        ->call('startCreate', 'file')
        ->set('artifactTitle', 'Sneaky')
        ->set('file', UploadedFile::fake()->createWithContent('evil.html', '<script>alert(1)</script>'))
        ->call('save')
        ->assertHasErrors(['file']);

    expect($this->project->artifacts()->count())->toBe(0);
});

it('rejects an SVG file upload (SVG can carry inline script)', function () {
    Storage::fake('local');

    Livewire::test(ArtifactsManager::class, ['project' => $this->project])
        ->call('startCreate', 'file')
        ->set('artifactTitle', 'Vector')
        ->set('file', UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        ))
        ->call('save')
        ->assertHasErrors(['file']);

    expect($this->project->artifacts()->count())->toBe(0);
});

it('keeps the artifact open after saving, so several edits fit in one sitting', function () {
    $artifact = Artifact::factory()->for($this->project)->markdown('# One')->create();

    Livewire::test(ArtifactsManager::class, ['project' => $this->project])
        ->call('startEdit', $artifact->id)
        ->set('body', '# Two')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', true)
        ->assertSet('editingArtifactId', $artifact->id)
        ->assertSet('body', '# Two')
        // A second edit in the same sitting appends to the same artifact.
        ->set('body', '# Three')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->project->artifacts()->count())->toBe(1)
        ->and($artifact->refresh()->revisions()->count())->toBe(3)
        ->and($artifact->body)->toBe('# Three');
});

it('switches to editing an artifact it just created, rather than creating a second', function () {
    Livewire::test(ArtifactsManager::class, ['project' => $this->project])
        ->call('startCreate', 'markdown')
        ->set('artifactTitle', 'Brief')
        ->set('body', '# One')
        ->call('save')
        ->assertHasNoErrors()
        ->tap(fn ($component) => expect($component->get('editingArtifactId'))
            ->toBe($this->project->artifacts()->firstOrFail()->id))
        ->set('body', '# Two')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->project->artifacts()->count())->toBe(1)
        ->and($this->project->artifacts()->firstOrFail()->revisions()->count())->toBe(2);
});

it('closes the artifact only when Close is used', function () {
    $artifact = Artifact::factory()->for($this->project)->markdown('# One')->create();

    Livewire::test(ArtifactsManager::class, ['project' => $this->project])
        ->call('startEdit', $artifact->id)
        ->set('body', '# Two')
        ->call('save')
        ->assertSet('showForm', true)
        ->call('resetForm')
        ->assertSet('showForm', false)
        ->assertSet('editingArtifactId', null);
});

it('opens the editor from the artifact name as well as the edit icon', function () {
    $artifact = Artifact::factory()->for($this->project)->markdown('# One')->create(['title' => 'Design Brief']);

    Livewire::test(ArtifactsManager::class, ['project' => $this->project])
        ->assertSeeHtml('data-test="open-artifact-'.$artifact->id.'"')
        ->call('startEdit', $artifact->id)
        ->assertSet('showForm', true)
        ->assertSet('artifactTitle', 'Design Brief');
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

/*
|--------------------------------------------------------------------------
| Markdown Revisions (#15)
|--------------------------------------------------------------------------
*/

it('creates a markdown artifact establishing Revision 1 attributed to the acting user', function () {
    Livewire::test(ArtifactsManager::class, ['project' => $this->project])
        ->call('startCreate', 'markdown')
        ->set('artifactTitle', 'Brief')
        ->set('body', '# One')
        ->call('save')
        ->assertHasNoErrors();

    $artifact = $this->project->artifacts()->firstOrFail();
    expect($artifact->revisions()->count())->toBe(1)
        ->and($artifact->body)->toBe('# One')
        ->and($artifact->currentRevision->user_id)->toBe($this->creator->id);
});

it('appends a Revision on edit and makes it the current content', function () {
    $artifact = Artifact::factory()->for($this->project)->markdown('# One')->create();

    Livewire::test(ArtifactsManager::class, ['project' => $this->project])
        ->call('startEdit', $artifact->id)
        ->assertSet('body', '# One')
        ->set('body', '# Two')
        ->call('save')
        ->assertHasNoErrors();

    $artifact->refresh();
    expect($artifact->revisions()->count())->toBe(2)
        ->and($artifact->body)->toBe('# Two')
        ->and($artifact->currentRevision->user_id)->toBe($this->creator->id);
});

it('treats a byte-identical save as a no-op', function () {
    $artifact = Artifact::factory()->for($this->project)->markdown('# Same')->create();

    Livewire::test(ArtifactsManager::class, ['project' => $this->project])
        ->call('startEdit', $artifact->id)
        ->set('body', '# Same')
        ->call('save')
        ->assertHasNoErrors();

    expect($artifact->refresh()->revisions()->count())->toBe(1);
});

it('renames a markdown artifact without appending a Revision', function () {
    $artifact = Artifact::factory()->for($this->project)->markdown('# Body')->create(['title' => 'Old']);

    Livewire::test(ArtifactsManager::class, ['project' => $this->project])
        ->call('startEdit', $artifact->id)
        ->set('artifactTitle', 'New Title')
        ->call('save')
        ->assertHasNoErrors();

    $artifact->refresh();
    expect($artifact->title)->toBe('New Title')
        ->and($artifact->revisions()->count())->toBe(1);
});

it('restores an older Revision by appending a copy-forward, deleting nothing', function () {
    $artifact = Artifact::factory()->for($this->project)->markdown('# One')->create();
    $firstRevisionId = $artifact->revisions()->firstOrFail()->id;

    $component = Livewire::test(ArtifactsManager::class, ['project' => $this->project])
        ->call('startEdit', $artifact->id)
        ->set('body', '# Two')->call('save')
        ->call('startEdit', $artifact->id)
        ->set('body', '# Three')->call('save');

    expect($artifact->refresh()->revisions()->count())->toBe(3);

    $component->call('startEdit', $artifact->id)
        ->call('restoreRevision', $firstRevisionId)
        ->assertHasNoErrors();

    $artifact->refresh();
    expect($artifact->revisions()->count())->toBe(4)
        ->and($artifact->body)->toBe('# One')
        // Nothing was deleted — the original three Revisions still exist.
        ->and($artifact->revisions()->whereKey($firstRevisionId)->exists())->toBeTrue();
});

/**
 * Edit a markdown artifact once per body given, so a test has a Revision history
 * to pick from. Returns the Revision ids, oldest first.
 *
 * @param  list<string>  $bodies
 * @return list<int>
 */
function revisionsFor(Artifact $artifact, array $bodies): array
{
    $component = Livewire::test(ArtifactsManager::class, ['project' => $artifact->project]);

    foreach ($bodies as $body) {
        $component->call('startEdit', $artifact->id)->set('body', $body)->call('save');
    }

    return $artifact->refresh()->revisions()->orderBy('id')->pluck('id')->all();
}

it('opens a past Revision to read its full content without changing the document', function () {
    $artifact = Artifact::factory()->for($this->project)->markdown('# One')->create();
    [$first] = revisionsFor($artifact, ['# Two']);

    $component = Livewire::test(ArtifactsManager::class, ['project' => $this->project])
        ->call('startEdit', $artifact->id)
        ->call('pickRevision', $first);

    expect($component->instance()->viewedRevision->body)->toBe('# One');
    $component->assertSee('# One');

    // Reading changed nothing: still two Revisions and the latest is current.
    expect($artifact->refresh()->revisions()->count())->toBe(2)
        ->and($artifact->body)->toBe('# Two');

    $component->call('clearRevisionSelection');
    expect($component->instance()->viewedRevision)->toBeNull();
});

it('compares two picked Revisions, showing what each line became', function () {
    $artifact = Artifact::factory()->for($this->project)->markdown("line a\nline b")->create();
    [$first, $second] = revisionsFor($artifact, ["line a\nline c"]);

    Livewire::test(ArtifactsManager::class, ['project' => $this->project])
        ->call('startEdit', $artifact->id)
        ->call('pickRevision', $first)
        ->call('pickRevision', $second)
        ->assertSee('line b')
        ->assertSee('line c')
        ->assertSet('compareFromId', $first)
        ->assertSet('compareToId', $second);
});

it('orders a picked pair oldest first however they were clicked', function () {
    $artifact = Artifact::factory()->for($this->project)->markdown('# One')->create();
    [$first, $second] = revisionsFor($artifact, ['# Two']);

    Livewire::test(ArtifactsManager::class, ['project' => $this->project])
        ->call('startEdit', $artifact->id)
        ->call('pickRevision', $second)
        ->call('pickRevision', $first)
        ->assertSet('compareFromId', $first)
        ->assertSet('compareToId', $second);
});

it('puts a Revision back down when the one being read is picked again', function () {
    $artifact = Artifact::factory()->for($this->project)->markdown('# One')->create();
    [$first] = revisionsFor($artifact, ['# Two']);

    Livewire::test(ArtifactsManager::class, ['project' => $this->project])
        ->call('startEdit', $artifact->id)
        ->call('pickRevision', $first)
        ->call('pickRevision', $first)
        ->assertSet('compareFromId', null)
        ->assertSet('compareToId', null);
});

it('starts a new selection when a third Revision is picked', function () {
    $artifact = Artifact::factory()->for($this->project)->markdown('# One')->create();
    [$first, $second, $third] = revisionsFor($artifact, ['# Two', '# Three']);

    Livewire::test(ArtifactsManager::class, ['project' => $this->project])
        ->call('startEdit', $artifact->id)
        ->call('pickRevision', $first)
        ->call('pickRevision', $second)
        ->call('pickRevision', $third)
        ->assertSet('compareFromId', $third)
        ->assertSet('compareToId', null);
});

it('counts the lines a comparison added and removed', function () {
    $artifact = Artifact::factory()->for($this->project)->markdown("keep\ndrop")->create();
    [$first, $second] = revisionsFor($artifact, ["keep\nadd\nmore"]);

    $component = Livewire::test(ArtifactsManager::class, ['project' => $this->project])
        ->call('startEdit', $artifact->id)
        ->call('pickRevision', $first)
        ->call('pickRevision', $second);

    expect($component->instance()->comparison)
        ->toMatchArray(['added' => 2, 'removed' => 1]);
});

it('hides the editor while the history panel is open and hands it back on close', function () {
    $artifact = Artifact::factory()->for($this->project)->markdown('# One')->create();
    [$first] = revisionsFor($artifact, ['# Two']);

    Livewire::test(ArtifactsManager::class, ['project' => $this->project])
        ->call('startEdit', $artifact->id)
        ->assertSeeHtml('wire:model="body"')
        ->call('pickRevision', $first)
        ->assertDontSeeHtml('wire:model="body"')
        ->call('clearRevisionSelection')
        ->assertSeeHtml('wire:model="body"');
});

it('keeps the unsaved draft body while a Revision is open', function () {
    $artifact = Artifact::factory()->for($this->project)->markdown('# One')->create();
    [$first] = revisionsFor($artifact, ['# Two']);

    Livewire::test(ArtifactsManager::class, ['project' => $this->project])
        ->call('startEdit', $artifact->id)
        ->set('body', '# Draft in progress')
        ->call('pickRevision', $first)
        ->call('clearRevisionSelection')
        ->assertSet('body', '# Draft in progress');
});

it('says when each Revision was saved and who saved it', function () {
    $agent = User::factory()->create(['name' => 'Atelier Agent']);
    $artifact = Artifact::factory()->for($this->project)->markdown('# One')->create();

    $revision = $artifact->revisions()->firstOrFail();
    $revision->forceFill(['user_id' => $agent->id, 'created_at' => now()->subDays(3)])->save();

    Livewire::test(ArtifactsManager::class, ['project' => $this->project])
        ->call('startEdit', $artifact->id)
        ->assertSee('Atelier Agent')
        ->assertSee($revision->refresh()->created_at->format('M j, H:i'));
});

it('numbers Revisions from one, oldest first', function () {
    $artifact = Artifact::factory()->for($this->project)->markdown('# One')->create();
    [$first, $second, $third] = revisionsFor($artifact, ['# Two', '# Three']);

    $component = Livewire::test(ArtifactsManager::class, ['project' => $this->project])
        ->call('startEdit', $artifact->id);

    expect($component->instance()->revisionOrdinals)
        ->toBe([$third => 3, $second => 2, $first => 1]);
});

it('closes the history panel after restoring, so the editor shows the restored body', function () {
    $artifact = Artifact::factory()->for($this->project)->markdown('# One')->create();
    [$first] = revisionsFor($artifact, ['# Two']);

    Livewire::test(ArtifactsManager::class, ['project' => $this->project])
        ->call('startEdit', $artifact->id)
        ->call('pickRevision', $first)
        ->call('restoreRevision', $first)
        ->assertSet('compareFromId', null)
        ->assertSet('compareToId', null)
        ->assertSet('body', '# One');
});
