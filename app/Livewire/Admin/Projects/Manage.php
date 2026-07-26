<?php

namespace App\Livewire\Admin\Projects;

use App\Enums\ArtifactType;
use App\Enums\ProjectStatus;
use App\Enums\ProjectVisibility;
use App\Models\Artifact;
use App\Models\Project;
use App\Services\LinkReissuer;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Manage project')]
class Manage extends Component
{
    public Project $project;

    public string $title = '';

    public string $visibility = 'private';

    public string $status = 'active';

    public string $newPassword = '';

    /**
     * Optional link expiry, as a `datetime-local` string (empty = never expires).
     */
    public string $expiresAt = '';

    /**
     * The id of the image artifact used as the public cover (empty = gradient fallback).
     */
    public string $headerArtifactId = '';

    /**
     * Toggle-friendly views of $visibility and $status, which stay canonical.
     */
    public bool $isPublic = false;

    public bool $isArchived = false;

    public function mount(Project $project): void
    {
        $this->project = $project;
        $this->title = $project->title;
        $this->visibility = $project->visibility->value;
        $this->status = $project->status->value;
        $this->expiresAt = $project->expires_at?->format('Y-m-d\TH:i') ?? '';
        $this->headerArtifactId = (string) ($project->header_artifact_id ?? '');
        $this->isPublic = $project->isPublic();
        $this->isArchived = $project->isArchived();
    }

    /**
     * The project's image artifacts, the only ones eligible as a cover.
     *
     * @return Collection<int, Artifact>
     */
    #[Computed]
    public function imageArtifacts(): Collection
    {
        return $this->project->artifacts()
            ->where('type', ArtifactType::File->value)
            ->where('mime_type', 'like', 'image/%')
            ->get();
    }

    /**
     * The image artifact currently picked as the cover, resolved from the form
     * value rather than the model so the header thumbnail tracks the select
     * before the change is saved.
     */
    #[Computed]
    public function coverArtifact(): ?Artifact
    {
        return filled($this->headerArtifactId)
            ? $this->imageArtifacts->firstWhere('id', (int) $this->headerArtifactId)
            : null;
    }

    public function updatedIsPublic(bool $value): void
    {
        $this->visibility = $value ? ProjectVisibility::Public->value : ProjectVisibility::Private->value;
    }

    public function updatedIsArchived(bool $value): void
    {
        $this->status = $value ? ProjectStatus::Archived->value : ProjectStatus::Active->value;
    }

    /**
     * The segmented controls bind to the canonical strings; mirror them back
     * onto the booleans the header badges and password field read.
     */
    public function updatedVisibility(string $value): void
    {
        $this->isPublic = $value === ProjectVisibility::Public->value;
    }

    public function updatedStatus(string $value): void
    {
        $this->isArchived = $value === ProjectStatus::Archived->value;
    }

    public function saveSettings(): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'visibility' => ['required', 'in:private,public'],
            'status' => ['required', 'in:active,archived'],
            'newPassword' => ['nullable', 'string', 'min:4', 'max:255'],
            'expiresAt' => ['nullable', 'date'],
            // Must be one of this project's own image artifacts.
            'headerArtifactId' => ['nullable', Rule::exists((new Artifact)->getTable(), 'id')->where(function ($query) {
                $query->where('project_id', $this->project->id)
                    ->where('type', ArtifactType::File->value)
                    ->where('mime_type', 'like', 'image/%');
            })],
        ]);

        $becomingPrivate = $validated['visibility'] === ProjectVisibility::Private->value;

        // A private project must have a password. Require one if none is set yet.
        if ($becomingPrivate && blank($this->project->password_hash) && blank($validated['newPassword'])) {
            throw ValidationException::withMessages([
                'newPassword' => __('Set a password to make this project private.'),
            ]);
        }

        $this->project->title = $validated['title'];
        $this->project->status = ProjectStatus::from($validated['status']);
        $this->project->visibility = ProjectVisibility::from($validated['visibility']);
        $this->project->expires_at = filled($validated['expiresAt']) ? Carbon::parse($validated['expiresAt']) : null;
        $this->project->header_artifact_id = filled($validated['headerArtifactId']) ? (int) $validated['headerArtifactId'] : null;
        $this->project->save();

        if ($becomingPrivate) {
            if (filled($validated['newPassword'])) {
                $this->project->setPassword($validated['newPassword']);
            }
        } else {
            // Public projects carry no password; clear it and invalidate sessions.
            if (filled($this->project->password_hash)) {
                $this->project->setPassword(null);
            }
        }

        $this->reset('newPassword');
        $this->project->refresh();

        $this->modal('project-settings')->close();

        Flux::toast(variant: 'success', text: __('Settings saved.'));
    }

    /**
     * Revoke the current link and issue a fresh one (slug + sandbox token),
     * keeping the project active. The previous link stops resolving everywhere.
     */
    public function reissueLink(LinkReissuer $reissuer): void
    {
        $reissuer->reissue($this->project);
        $this->project->refresh();

        Flux::toast(variant: 'success', text: __('A new shareable link was generated. The old link no longer works.'));
    }

    public function render(): View
    {
        return view('livewire.admin.projects.manage');
    }
}
