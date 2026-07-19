<?php

namespace App\Livewire\Admin\Projects;

use App\Enums\ArtifactPlacement;
use App\Enums\ArtifactType;
use App\Models\Artifact;
use App\Models\Project;
use App\Services\BundleUnpacker;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class ArtifactsManager extends Component
{
    use WithFileUploads;

    public Project $project;

    public bool $showForm = false;

    public ?int $editingArtifactId = null;

    public string $formType = 'markdown';

    public string $artifactTitle = '';

    public string $body = '';

    public string $entryFile = '';

    public string $placement = 'stage';

    public ?TemporaryUploadedFile $mdFile = null;

    public ?TemporaryUploadedFile $zipFile = null;

    public ?TemporaryUploadedFile $image = null;

    public ?TemporaryUploadedFile $file = null;

    /**
     * @return Collection<int, Artifact>
     */
    #[Computed]
    public function artifacts(): Collection
    {
        return $this->project->artifacts()->get();
    }

    public function startCreate(string $type): void
    {
        $this->resetForm();
        $this->formType = (ArtifactType::tryFrom($type) ?? ArtifactType::Markdown)->value;
        $this->showForm = true;
    }

    public function startEdit(int $artifactId): void
    {
        $artifact = $this->project->artifacts()->findOrFail($artifactId);

        $this->resetForm();
        $this->editingArtifactId = $artifact->id;
        $this->formType = $artifact->type->value;
        $this->artifactTitle = $artifact->title;
        $this->body = (string) $artifact->body;
        $this->entryFile = (string) $artifact->entry_file;
        $this->placement = ($artifact->placement ?? ArtifactPlacement::Stage)->value;
        $this->showForm = true;
    }

    /**
     * Default a newly chosen file's placement from its MIME type. The operator
     * can still override the toggle afterwards.
     */
    public function updatedFile(): void
    {
        if ($this->file !== null) {
            $this->placement = ArtifactPlacement::defaultForMime($this->file->getMimeType())->value;
        }
    }

    public function save(BundleUnpacker $unpacker): void
    {
        match ($this->formType) {
            ArtifactType::Html->value => $this->saveHtml($unpacker),
            ArtifactType::File->value => $this->saveFile(),
            default => $this->saveMarkdown(),
        };
    }

    protected function saveMarkdown(): void
    {
        $this->validate([
            'artifactTitle' => ['required', 'string', 'max:255'],
            'mdFile' => ['nullable', 'file', 'mimes:md,txt,markdown', 'max:2048'],
            'body' => ['nullable', 'string'],
        ]);

        // An uploaded .md file wins over the textarea when creating.
        $body = $this->mdFile
            ? file_get_contents($this->mdFile->getRealPath())
            : $this->body;

        $artifact = $this->editingArtifactId
            ? $this->project->artifacts()->findOrFail($this->editingArtifactId)
            : $this->project->artifacts()->make(['type' => ArtifactType::Markdown, 'sort_order' => $this->nextSortOrder()]);

        $artifact->fill([
            'title' => $this->artifactTitle,
            'type' => ArtifactType::Markdown,
            'body' => $body,
        ])->save();

        $this->finish(__('Markdown page saved.'));
    }

    protected function saveHtml(BundleUnpacker $unpacker): void
    {
        $creating = ! $this->editingArtifactId;

        $this->validate([
            'artifactTitle' => ['required', 'string', 'max:255'],
            'zipFile' => [$creating ? 'required' : 'nullable', 'file', 'mimes:zip', 'max:51200'],
            'entryFile' => ['nullable', 'string', 'max:255'],
        ]);

        $artifact = $this->editingArtifactId
            ? $this->project->artifacts()->findOrFail($this->editingArtifactId)
            : $this->project->artifacts()->make(['type' => ArtifactType::Html, 'sort_order' => $this->nextSortOrder()]);

        $artifact->fill(['title' => $this->artifactTitle, 'type' => ArtifactType::Html]);
        $artifact->save();

        if ($this->zipFile) {
            try {
                $result = $unpacker->unpack(
                    $this->zipFile->getRealPath(),
                    $this->project,
                    $artifact,
                    filled($this->entryFile) ? $this->entryFile : null,
                );
            } catch (\RuntimeException $e) {
                if ($creating) {
                    $artifact->delete();
                }

                $this->addError('zipFile', $e->getMessage());

                return;
            }

            $artifact->update($result);
        }

        $this->finish(__('HTML page saved.'));
    }

    protected function saveFile(): void
    {
        $creating = ! $this->editingArtifactId;

        $this->validate([
            'artifactTitle' => ['required', 'string', 'max:255'],
            'file' => [$creating ? 'required' : 'nullable', 'file', 'max:102400'],
            'placement' => ['required', 'in:stage,download'],
        ]);

        $artifact = $this->editingArtifactId
            ? $this->project->artifacts()->findOrFail($this->editingArtifactId)
            : $this->project->artifacts()->make(['type' => ArtifactType::File, 'sort_order' => $this->nextSortOrder()]);

        $artifact->fill([
            'title' => $this->artifactTitle,
            'type' => ArtifactType::File,
            'placement' => ArtifactPlacement::from($this->placement),
        ]);

        if ($this->file) {
            // Read metadata before storing: storeAs() moves the temporary file
            // when the target and temp disks match, so getSize()/getMimeType()
            // would fail on the now-removed source afterwards.
            $original = $this->file->getClientOriginalName();
            $mimeType = $this->file->getMimeType();
            $size = $this->file->getSize();

            $stored = $this->file->storeAs(
                "artifacts/{$this->project->id}",
                Str::uuid().'-'.$original,
                'local',
            );

            if ($stored === false) {
                $this->addError('file', __('The file could not be stored.'));

                return;
            }

            // Replace the previous file only once the new one is safely stored.
            if (filled($artifact->stored_path)) {
                $artifact->type->handler()->purge($artifact);
            }

            $artifact->stored_path = $stored;
            $artifact->original_filename = $original;
            $artifact->mime_type = $mimeType;
            $artifact->size_bytes = $size;
        }

        $artifact->save();

        $this->finish(__('File saved.'));
    }

    public function uploadImage(): void
    {
        $this->validate([
            'image' => ['required', 'image', 'max:8192'],
        ]);

        $extension = $this->image->getClientOriginalExtension() ?: 'png';
        $filename = Str::uuid().'.'.$extension;

        $this->image->storeAs("markdown-images/{$this->project->id}", $filename, 'local');

        $url = route('project.image', [$this->project, $filename]);
        $this->body = trim($this->body."\n\n![]({$url})")."\n";

        $this->reset('image');

        Flux::toast(text: __('Image uploaded and inserted.'));
    }

    public function deleteArtifact(int $artifactId): void
    {
        $artifact = $this->project->artifacts()->findOrFail($artifactId);

        $artifact->type->handler()->purge($artifact);
        $artifact->delete();

        unset($this->artifacts);
        Flux::toast(variant: 'success', text: __('Artifact deleted.'));
    }

    public function moveUp(int $artifactId): void
    {
        $this->swap($artifactId, -1);
    }

    public function moveDown(int $artifactId): void
    {
        $this->swap($artifactId, 1);
    }

    protected function swap(int $artifactId, int $direction): void
    {
        $artifacts = $this->project->artifacts()->get()->values();
        $index = $artifacts->search(fn (Artifact $a) => $a->id === $artifactId);

        if ($index === false) {
            return;
        }

        $target = $index + $direction;

        if ($target < 0 || $target >= $artifacts->count()) {
            return;
        }

        $current = $artifacts[$index];
        $neighbor = $artifacts[$target];

        [$current->sort_order, $neighbor->sort_order] = [$neighbor->sort_order, $current->sort_order];
        $current->save();
        $neighbor->save();

        unset($this->artifacts);
    }

    protected function nextSortOrder(): int
    {
        return (int) $this->project->artifacts()->max('sort_order') + 1;
    }

    protected function finish(string $message): void
    {
        $this->resetForm();
        unset($this->artifacts);
        Flux::toast(variant: 'success', text: $message);
    }

    public function resetForm(): void
    {
        $this->reset(['showForm', 'editingArtifactId', 'formType', 'artifactTitle', 'body', 'entryFile', 'placement', 'mdFile', 'zipFile', 'image', 'file']);
        $this->resetErrorBag();
    }

    public function render(): View
    {
        return view('livewire.admin.projects.artifacts-manager');
    }
}
