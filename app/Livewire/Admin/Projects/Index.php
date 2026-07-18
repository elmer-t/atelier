<?php

namespace App\Livewire\Admin\Projects;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Services\ProjectDeleter;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('Projects')]
class Index extends Component
{
    #[Validate('required|string|max:255')]
    public string $newTitle = '';

    /**
     * @return Collection<int, Project>
     */
    #[Computed]
    public function projects(): Collection
    {
        return Project::query()
            ->withCount('assets')
            ->latest()
            ->get();
    }

    public function create(): void
    {
        $validated = $this->validate();

        $project = Project::create(['title' => $validated['newTitle']]);

        $this->reset('newTitle');
        unset($this->projects);

        Flux::modal('create-project')->close();
        Flux::toast(variant: 'success', text: __('Project created.'));

        $this->redirectRoute('admin.projects.manage', $project, navigate: true);
    }

    public function toggleStatus(Project $project): void
    {
        $project->status = $project->isActive() ? ProjectStatus::Archived : ProjectStatus::Active;
        $project->save();

        unset($this->projects);
    }

    public function delete(Project $project, ProjectDeleter $deleter): void
    {
        $deleter->delete($project);

        unset($this->projects);

        Flux::toast(variant: 'success', text: __('Project deleted.'));
    }

    public function render(): View
    {
        return view('livewire.admin.projects.index');
    }
}
