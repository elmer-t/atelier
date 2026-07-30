<?php

namespace App\Livewire\Admin\Projects;

use App\Enums\ProjectStatus;
use App\Enums\ProjectVisibility;
use App\Livewire\Concerns\WithSortableColumns;
use App\Models\Project;
use App\Services\ProjectDeleter;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Projects')]
class Index extends Component
{
    use WithPagination;
    use WithSortableColumns;

    /**
     * @var array<string, string>
     */
    private const SORTABLE_COLUMNS = [
        'title' => 'asc',
        'visibility' => 'asc',
        'status' => 'asc',
        'artifacts_count' => 'desc',
        'last_viewed_at' => 'desc',
        'created_at' => 'desc',
    ];

    private const PER_PAGE = 15;

    #[Validate('required|string|max:255')]
    public string $newTitle = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $visibility = '';

    /**
     * @return array<string, string>
     */
    protected function sortableColumns(): array
    {
        return self::SORTABLE_COLUMNS;
    }

    protected function sortableResultProperty(): string
    {
        return 'projects';
    }

    /**
     * @return LengthAwarePaginator<int, Project>
     */
    #[Computed]
    public function projects(): LengthAwarePaginator
    {
        return Project::query()
            ->withCount('artifacts')
            ->when($this->search !== '', fn (Builder $query) => $query->whereLike('title', "%{$this->search}%"))
            ->when(
                ProjectStatus::tryFrom($this->status),
                fn (Builder $query, ProjectStatus $status) => $query->where('status', $status),
            )
            ->when(
                ProjectVisibility::tryFrom($this->visibility),
                fn (Builder $query, ProjectVisibility $visibility) => $query->where('visibility', $visibility),
            )
            ->orderBy($this->sortedColumn(), $this->sortedDirection())
            ->paginate(self::PER_PAGE);
    }

    /**
     * Whether any filter narrows the list, which distinguishes "no projects yet"
     * from "nothing matches".
     */
    #[Computed]
    public function isFiltered(): bool
    {
        return $this->search !== '' || $this->status !== '' || $this->visibility !== '';
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status', 'visibility');

        $this->resetPage();
        unset($this->projects);
    }

    /**
     * A narrower result set invalidates whichever page the Creator was on.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'visibility'], true)) {
            $this->resetPage();
        }
    }

    public function create(): void
    {
        $validated = $this->validate();

        // Owned by whoever made it, so feedback on it routes back to them rather
        // than to every Creator on the instance.
        $project = Auth::user()->projects()->create(['title' => $validated['newTitle']]);

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
