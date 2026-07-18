<?php

namespace App\Livewire\Admin\Projects;

use App\Enums\ProjectStatus;
use App\Enums\ProjectVisibility;
use App\Models\Project;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
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

    public function mount(Project $project): void
    {
        $this->project = $project;
        $this->title = $project->title;
        $this->visibility = $project->visibility->value;
        $this->status = $project->status->value;
    }

    public function saveSettings(): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'visibility' => ['required', 'in:private,public'],
            'status' => ['required', 'in:active,archived'],
            'newPassword' => ['nullable', 'string', 'min:4', 'max:255'],
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

        Flux::toast(variant: 'success', text: __('Settings saved.'));
    }

    public function regenerateSlug(): void
    {
        $this->project->regenerateSlug();
        $this->project->refresh();

        Flux::toast(variant: 'success', text: __('A new shareable link was generated. The old link no longer works.'));
    }

    public function render(): View
    {
        return view('livewire.admin.projects.manage');
    }
}
