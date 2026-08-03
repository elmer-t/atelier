<?php

namespace App\Livewire\Admin\Projects;

use App\Enums\ArtifactPlacement;
use App\Enums\ArtifactType;
use App\Models\Artifact;
use App\Models\ArtifactRevision;
use App\Models\Project;
use App\Services\BundleUnpacker;
use App\Support\Artifacts\MarkdownRevisionWriter;
use App\Support\RevisionDiff;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * @phpstan-import-type DiffSection from RevisionDiff
 *
 * @property-read Collection<int, ArtifactRevision> $revisions
 * @property-read ArtifactRevision|null $compareFrom
 * @property-read ArtifactRevision|null $compareTo
 * @property-read ArtifactRevision|null $viewedRevision
 */
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
     * The Revisions picked in the history rail, oldest first. One on its own is
     * opened for reading; a second turns the panel into a comparison of the two.
     */
    public ?int $compareFromId = null;

    public ?int $compareToId = null;

    /**
     * @return Collection<int, Artifact>
     */
    #[Computed]
    public function artifacts(): Collection
    {
        return $this->project->artifacts()->with('currentRevision')->get();
    }

    /**
     * The Revision history of the markdown Artifact being edited, newest first.
     *
     * @return Collection<int, ArtifactRevision>
     */
    #[Computed]
    public function revisions(): Collection
    {
        if ($this->editingArtifactId === null) {
            return collect();
        }

        return $this->project->artifacts()
            ->findOrFail($this->editingArtifactId)
            ->revisions()
            ->with('author')
            ->get()
            ->sortByDesc('id')
            ->values();
    }

    /**
     * Revision number as a Creator counts them — 1 is the oldest — keyed by id, so
     * the rail can name a Revision without knowing its position in the collection.
     *
     * @return array<int, int>
     */
    #[Computed]
    public function revisionOrdinals(): array
    {
        $total = $this->revisions->count();

        return $this->revisions
            ->values()
            ->mapWithKeys(fn (ArtifactRevision $revision, int $index) => [
                $revision->id => $total - $index,
            ])
            ->all();
    }

    /**
     * How the rail should draw each Revision, keyed by id: whether it is one of the
     * picked ends, whether it lies between them, and the word for the part it plays.
     * Derived here rather than in the view because it rests on the invariant that
     * Revision ids ascend with time (ADR-0005 — the log is only ever appended to).
     *
     * @return array<int, array{picked: bool, between: bool, part: string|null}>
     */
    #[Computed]
    public function railStates(): array
    {
        $from = $this->compareFromId;
        $to = $this->compareToId;

        return $this->revisions
            ->mapWithKeys(function (ArtifactRevision $revision) use ($from, $to): array {
                $picked = $revision->id === $from || $revision->id === $to;

                return [$revision->id => [
                    'picked' => $picked,
                    'between' => $from !== null && $to !== null
                        && $revision->id > $from && $revision->id < $to,
                    'part' => match (true) {
                        ! $picked => null,
                        $to === null => 'reading',
                        $revision->id === $from => 'from',
                        default => 'to',
                    },
                ]];
            })
            ->all();
    }

    #[Computed]
    public function compareFrom(): ?ArtifactRevision
    {
        return $this->revisions->firstWhere('id', $this->compareFromId);
    }

    #[Computed]
    public function compareTo(): ?ArtifactRevision
    {
        return $this->revisions->firstWhere('id', $this->compareToId);
    }

    /**
     * The past Revision the Creator has opened to read in full, which is the single
     * picked one before a second is chosen. Read-only — opening a Revision never
     * changes the document (User Story 4).
     */
    #[Computed]
    public function viewedRevision(): ?ArtifactRevision
    {
        return $this->compareTo === null ? $this->compareFrom : null;
    }

    /**
     * The diff between the two picked Revisions as collapsible sections, plus the
     * added/removed counts (User Story 5). Null until two are picked.
     *
     * @return array{sections: list<DiffSection>, added: int, removed: int}|null
     */
    #[Computed]
    public function comparison(): ?array
    {
        $from = $this->compareFrom;
        $to = $this->compareTo;

        if ($from === null || $to === null) {
            return null;
        }

        return app(RevisionDiff::class)->compare((string) $from->body, (string) $to->body);
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

    public function save(BundleUnpacker $unpacker, MarkdownRevisionWriter $writer): void
    {
        match ($this->formType) {
            ArtifactType::Html->value => $this->saveHtml($unpacker),
            ArtifactType::File->value => $this->saveFile(),
            default => $this->saveMarkdown($writer),
        };
    }

    protected function saveMarkdown(MarkdownRevisionWriter $writer): void
    {
        $this->validate([
            'artifactTitle' => ['required', 'string', 'max:255'],
            'mdFile' => ['nullable', 'file', 'mimes:md,txt,markdown', 'max:2048'],
            'body' => ['nullable', 'string'],
        ]);

        // An uploaded .md file wins over the textarea when creating.
        $body = (string) ($this->mdFile
            ? file_get_contents($this->mdFile->getRealPath())
            : $this->body);

        if ($this->editingArtifactId) {
            $artifact = $this->project->artifacts()->findOrFail($this->editingArtifactId);

            // Renaming is metadata churn, not a content change — no Revision.
            if ($artifact->title !== $this->artifactTitle) {
                $artifact->update(['title' => $this->artifactTitle]);
            }

            $writer->update($artifact, $body, auth()->user());
        } else {
            $writer->create($this->project, $this->artifactTitle, $body, auth()->user(), $this->nextSortOrder());
        }

        $this->finish(__('Markdown page saved.'));
    }

    /**
     * Pick a Revision in the history rail. The first pick opens that Revision to
     * read (User Story 4); a second picks the other end of a comparison (User
     * Story 5), ordered oldest first however they were clicked; a third starts a
     * new selection from the Revision just clicked. Clicking the one Revision
     * being read puts it back down.
     */
    public function pickRevision(int $revisionId): void
    {
        if ($this->compareFromId === null || $this->compareToId !== null) {
            $this->compareFromId = $revisionId;
            $this->compareToId = null;
        } elseif ($revisionId === $this->compareFromId) {
            $this->compareFromId = null;
        } else {
            [$this->compareFromId, $this->compareToId] = $revisionId < $this->compareFromId
                ? [$revisionId, $this->compareFromId]
                : [$this->compareFromId, $revisionId];
        }

        $this->forgetComparison();
    }

    /**
     * Close the history panel and hand the editor its room back.
     */
    public function clearRevisionSelection(): void
    {
        $this->compareFromId = null;
        $this->compareToId = null;

        $this->forgetComparison();
    }

    /**
     * Roll a markdown Artifact back to an older Revision by appending a copy of it
     * as the new current Revision — history is never rewritten (ADR-0005).
     */
    public function restoreRevision(int $revisionId, MarkdownRevisionWriter $writer): void
    {
        $artifact = $this->project->artifacts()->findOrFail($this->editingArtifactId);
        $revision = $artifact->revisions()->findOrFail($revisionId);

        $writer->restore($artifact, $revision, auth()->user());

        $this->body = (string) $artifact->body;
        $this->clearRevisionSelection();
        unset($this->revisions, $this->artifacts);

        Flux::toast(variant: 'success', text: __('Revision restored.'));
    }

    protected function forgetComparison(): void
    {
        unset($this->compareFrom, $this->compareTo, $this->viewedRevision, $this->comparison, $this->railStates);
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
            'file' => [
                $creating ? 'required' : 'nullable',
                'file',
                'max:102400',
                // Allowlist the extension and the content-sniffed type so a file that
                // the browser would run as HTML/SVG on the app origin never lands here
                // (ADR-0002 / specs §10). Executable types are deliberately excluded.
                'extensions:'.implode(',', config('atelier.uploads.file_extensions')),
                'mimetypes:'.implode(',', config('atelier.uploads.file_mimetypes')),
            ],
            'placement' => ['required', 'in:stage,download'],
        ], [
            'file.extensions' => __('You cannot upload this type of file. Upload a document or an image, for example a PDF file or an Office file.'),
            'file.mimetypes' => __('You cannot upload this type of file. Upload a document or an image, for example a PDF file or an Office file.'),
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
                $this->addError('file', __('The system cannot store the file. Try again.'));

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
        $this->reset(['showForm', 'editingArtifactId', 'formType', 'artifactTitle', 'body', 'entryFile', 'placement', 'mdFile', 'zipFile', 'image', 'file', 'compareFromId', 'compareToId']);
        $this->resetErrorBag();
        $this->forgetComparison();
    }

    public function render(): View
    {
        return view('livewire.admin.projects.artifacts-manager');
    }
}
