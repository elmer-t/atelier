<?php

namespace App\Livewire;

use App\Enums\ProjectStatus;
use App\Models\Comment;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The Creator's home: a single prioritized list of the things waiting on them —
 * feedback to review, projects that still need content, and projects ready to
 * share. An action hub, deliberately not a statistics view.
 */
#[Title('Dashboard')]
class Dashboard extends Component
{
    /**
     * Unresolved feedback threads grouped by project, most threads first. Each
     * row is a project the Creator owes a reply on.
     *
     * @return list<array{project: Project, count: int, latest: Carbon|null}>
     */
    #[Computed]
    public function feedback(): array
    {
        $comments = Comment::query()
            ->roots()
            ->whereNull('resolved_at')
            ->with('artifact.project')
            ->latest()
            ->get();

        $rows = [];

        foreach ($comments as $comment) {
            $project = $comment->artifact->project;

            if (! isset($rows[$project->id])) {
                // Comments are newest-first, so the first one seen is the latest.
                $rows[$project->id] = [
                    'project' => $project,
                    'count' => 0,
                    'latest' => $comment->created_at,
                ];
            }

            $rows[$project->id]['count']++;
        }

        usort($rows, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $rows;
    }

    /**
     * Active projects with no artifacts yet — they need content before they can
     * be shared with a Client.
     *
     * @return EloquentCollection<int, Project>
     */
    #[Computed]
    public function needsContent(): EloquentCollection
    {
        return Project::query()
            ->where('status', ProjectStatus::Active)
            ->doesntHave('artifacts')
            ->latest()
            ->get();
    }

    /**
     * Active projects that have content but have never been opened — built and
     * waiting for the Creator to send the link.
     *
     * @return EloquentCollection<int, Project>
     */
    #[Computed]
    public function readyToShare(): EloquentCollection
    {
        return Project::query()
            ->where('status', ProjectStatus::Active)
            ->has('artifacts')
            ->whereNull('last_viewed_at')
            ->withCount('artifacts')
            ->latest()
            ->get();
    }

    public function render(): View
    {
        return view('livewire.dashboard');
    }
}
