<?php

namespace App\Livewire\Admin\Projects;

use App\Enums\AssetPlacement;
use App\Enums\AssetType;
use App\Models\Asset;
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

class AssetsManager extends Component
{
    use WithFileUploads;

    public Project $project;

    public bool $showForm = false;

    public ?int $editingAssetId = null;

    public string $formType = 'markdown';

    public string $assetTitle = '';

    public string $body = '';

    public string $entryFile = '';

    public string $placement = 'stage';

    public ?TemporaryUploadedFile $mdFile = null;

    public ?TemporaryUploadedFile $zipFile = null;

    public ?TemporaryUploadedFile $image = null;

    public ?TemporaryUploadedFile $file = null;

    /**
     * @return Collection<int, Asset>
     */
    #[Computed]
    public function assets(): Collection
    {
        return $this->project->assets()->get();
    }

    public function startCreate(string $type): void
    {
        $this->resetForm();
        $this->formType = (AssetType::tryFrom($type) ?? AssetType::Markdown)->value;
        $this->showForm = true;
    }

    public function startEdit(int $assetId): void
    {
        $asset = $this->project->assets()->findOrFail($assetId);

        $this->resetForm();
        $this->editingAssetId = $asset->id;
        $this->formType = $asset->type->value;
        $this->assetTitle = $asset->title;
        $this->body = (string) $asset->body;
        $this->entryFile = (string) $asset->entry_file;
        $this->placement = ($asset->placement ?? AssetPlacement::Stage)->value;
        $this->showForm = true;
    }

    /**
     * Default a newly chosen file's placement from its MIME type. The operator
     * can still override the toggle afterwards.
     */
    public function updatedFile(): void
    {
        if ($this->file !== null) {
            $this->placement = AssetPlacement::defaultForMime($this->file->getMimeType())->value;
        }
    }

    public function save(BundleUnpacker $unpacker): void
    {
        match ($this->formType) {
            AssetType::Html->value => $this->saveHtml($unpacker),
            AssetType::File->value => $this->saveFile(),
            default => $this->saveMarkdown(),
        };
    }

    protected function saveMarkdown(): void
    {
        $this->validate([
            'assetTitle' => ['required', 'string', 'max:255'],
            'mdFile' => ['nullable', 'file', 'mimes:md,txt,markdown', 'max:2048'],
            'body' => ['nullable', 'string'],
        ]);

        // An uploaded .md file wins over the textarea when creating.
        $body = $this->mdFile
            ? file_get_contents($this->mdFile->getRealPath())
            : $this->body;

        $asset = $this->editingAssetId
            ? $this->project->assets()->findOrFail($this->editingAssetId)
            : $this->project->assets()->make(['type' => AssetType::Markdown, 'sort_order' => $this->nextSortOrder()]);

        $asset->fill([
            'title' => $this->assetTitle,
            'type' => AssetType::Markdown,
            'body' => $body,
        ])->save();

        $this->finish(__('Markdown page saved.'));
    }

    protected function saveHtml(BundleUnpacker $unpacker): void
    {
        $creating = ! $this->editingAssetId;

        $this->validate([
            'assetTitle' => ['required', 'string', 'max:255'],
            'zipFile' => [$creating ? 'required' : 'nullable', 'file', 'mimes:zip', 'max:51200'],
            'entryFile' => ['nullable', 'string', 'max:255'],
        ]);

        $asset = $this->editingAssetId
            ? $this->project->assets()->findOrFail($this->editingAssetId)
            : $this->project->assets()->make(['type' => AssetType::Html, 'sort_order' => $this->nextSortOrder()]);

        $asset->fill(['title' => $this->assetTitle, 'type' => AssetType::Html]);
        $asset->save();

        if ($this->zipFile) {
            try {
                $result = $unpacker->unpack(
                    $this->zipFile->getRealPath(),
                    $this->project,
                    $asset,
                    filled($this->entryFile) ? $this->entryFile : null,
                );
            } catch (\RuntimeException $e) {
                if ($creating) {
                    $asset->delete();
                }

                $this->addError('zipFile', $e->getMessage());

                return;
            }

            $asset->update($result);
        }

        $this->finish(__('HTML page saved.'));
    }

    protected function saveFile(): void
    {
        $creating = ! $this->editingAssetId;

        $this->validate([
            'assetTitle' => ['required', 'string', 'max:255'],
            'file' => [$creating ? 'required' : 'nullable', 'file', 'max:102400'],
            'placement' => ['required', 'in:stage,download'],
        ]);

        $asset = $this->editingAssetId
            ? $this->project->assets()->findOrFail($this->editingAssetId)
            : $this->project->assets()->make(['type' => AssetType::File, 'sort_order' => $this->nextSortOrder()]);

        $asset->fill([
            'title' => $this->assetTitle,
            'type' => AssetType::File,
            'placement' => AssetPlacement::from($this->placement),
        ]);

        if ($this->file) {
            // Read metadata before storing: storeAs() moves the temporary file
            // when the target and temp disks match, so getSize()/getMimeType()
            // would fail on the now-removed source afterwards.
            $original = $this->file->getClientOriginalName();
            $mimeType = $this->file->getMimeType();
            $size = $this->file->getSize();

            $stored = $this->file->storeAs(
                "assets/{$this->project->id}",
                Str::uuid().'-'.$original,
                'local',
            );

            if ($stored === false) {
                $this->addError('file', __('The file could not be stored.'));

                return;
            }

            // Replace the previous file only once the new one is safely stored.
            if (filled($asset->stored_path)) {
                $asset->type->handler()->purge($asset);
            }

            $asset->stored_path = $stored;
            $asset->original_filename = $original;
            $asset->mime_type = $mimeType;
            $asset->size_bytes = $size;
        }

        $asset->save();

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

    public function deleteAsset(int $assetId): void
    {
        $asset = $this->project->assets()->findOrFail($assetId);

        $asset->type->handler()->purge($asset);
        $asset->delete();

        unset($this->assets);
        Flux::toast(variant: 'success', text: __('Asset deleted.'));
    }

    public function moveUp(int $assetId): void
    {
        $this->swap($assetId, -1);
    }

    public function moveDown(int $assetId): void
    {
        $this->swap($assetId, 1);
    }

    protected function swap(int $assetId, int $direction): void
    {
        $assets = $this->project->assets()->get()->values();
        $index = $assets->search(fn (Asset $a) => $a->id === $assetId);

        if ($index === false) {
            return;
        }

        $target = $index + $direction;

        if ($target < 0 || $target >= $assets->count()) {
            return;
        }

        $current = $assets[$index];
        $neighbor = $assets[$target];

        [$current->sort_order, $neighbor->sort_order] = [$neighbor->sort_order, $current->sort_order];
        $current->save();
        $neighbor->save();

        unset($this->assets);
    }

    protected function nextSortOrder(): int
    {
        return (int) $this->project->assets()->max('sort_order') + 1;
    }

    protected function finish(string $message): void
    {
        $this->resetForm();
        unset($this->assets);
        Flux::toast(variant: 'success', text: $message);
    }

    public function resetForm(): void
    {
        $this->reset(['showForm', 'editingAssetId', 'formType', 'assetTitle', 'body', 'entryFile', 'placement', 'mdFile', 'zipFile', 'image', 'file']);
        $this->resetErrorBag();
    }

    public function render(): View
    {
        return view('livewire.admin.projects.assets-manager');
    }
}
