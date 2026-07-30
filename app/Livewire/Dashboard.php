<?php

namespace App\Livewire;

use App\Enums\ProjectStatus;
use App\Models\Artifact;
use App\Models\Comment;
use App\Models\Project;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The Creator's home: a single prioritized list of the things waiting on them —
 * feedback to review, projects that still need content, and projects ready to
 * share. An action hub, deliberately not a statistics view.
 *
 * Once the list grows past a screenful the prioritized order stops being enough,
 * so a search term and a sort key narrow and re-order every queue at once.
 */
#[Title('Dashboard')]
class Dashboard extends Component
{
    /**
     * How every queue is ordered. `priority` is each queue's own sense of urgency
     * — most feedback first, newest projects first — and the rest re-order all
     * three queues by one shared key.
     *
     * @var list<string>
     */
    private const SORTS = ['priority', 'date', 'project', 'artifact'];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 'priority')]
    public string $sort = 'priority';

    /**
     * The active sort, falling back to the default when the query string names
     * something unknown. Everything reads this rather than the raw property so
     * a hand-edited URL cannot desync the select from the ordering.
     */
    public function sortedBy(): string
    {
        return in_array($this->sort, self::SORTS, true) ? $this->sort : 'priority';
    }

    /**
     * Unresolved feedback threads grouped by artifact, most threads first. Each
     * row is a piece of content the Creator owes a reply on.
     *
     * @return list<array{artifact: Artifact, project: Project, count: int, latest: CarbonInterface|null}>
     */
    #[Computed]
    public function feedback(): array
    {
        $comments = Comment::query()
            ->roots()
            ->whereNull('resolved_at')
            ->when($this->search !== '', fn (Builder $query) => $query->whereHas(
                'artifact',
                fn (Builder $artifact) => $artifact
                    ->whereLike('title', "%{$this->search}%")
                    ->orWhereHas('project', fn (Builder $project) => $project->whereLike('title', "%{$this->search}%")),
            ))
            ->with('artifact.project')
            ->latest()
            ->get();

        $rows = [];

        foreach ($comments as $comment) {
            $artifact = $comment->artifact;

            if (! isset($rows[$artifact->id])) {
                // Comments are newest-first, so the first one seen is the latest.
                $rows[$artifact->id] = [
                    'artifact' => $artifact,
                    'project' => $artifact->project,
                    'count' => 0,
                    'latest' => $comment->created_at,
                ];
            }

            $rows[$artifact->id]['count']++;
        }

        $rows = array_values($rows);

        usort($rows, $this->compareFeedback(...));

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
        return $this->sortProjects(
            $this->filterProjects(Project::query()->where('status', ProjectStatus::Active)->doesntHave('artifacts')),
        )->get();
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
        return $this->sortProjects(
            $this->filterProjects(
                Project::query()
                    ->where('status', ProjectStatus::Active)
                    ->has('artifacts')
                    ->whereNull('last_viewed_at')
                    ->withCount('artifacts'),
            ),
        )->get();
    }

    /**
     * Whether a search term narrows the queues, which distinguishes "nothing is
     * waiting on you" from "nothing matches what you typed".
     */
    #[Computed]
    public function isFiltered(): bool
    {
        return $this->search !== '';
    }

    public function clearFilters(): void
    {
        $this->reset('search');

        unset($this->feedback, $this->needsContent, $this->readyToShare);
    }

    public function render(): View
    {
        return view('livewire.dashboard');
    }

    /**
     * Match a project against the search term by its own title or by any of the
     * artifacts inside it, so searching for content finds the project holding it.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    private function filterProjects(Builder $query): Builder
    {
        return $query->when($this->search !== '', fn (Builder $query) => $query->where(
            fn (Builder $query) => $query
                ->whereLike('title', "%{$this->search}%")
                ->orWhereHas('artifacts', fn (Builder $artifact) => $artifact->whereLike('title', "%{$this->search}%")),
        ));
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    private function sortProjects(Builder $query): Builder
    {
        return match ($this->sortedBy()) {
            // Neither project queue is artifact-shaped, so an artifact sort is
            // the closest thing it can be here: alphabetical by project.
            'project', 'artifact' => $query->orderBy('title'),
            default => $query->latest(),
        };
    }

    /**
     * Order two feedback rows under the active sort. Names compare
     * case-insensitively and fall through to the other name on a tie; the
     * default keeps the queue's own priority — most threads first, newest first.
     *
     * @param  array{artifact: Artifact, project: Project, count: int, latest: CarbonInterface|null}  $a
     * @param  array{artifact: Artifact, project: Project, count: int, latest: CarbonInterface|null}  $b
     */
    private function compareFeedback(array $a, array $b): int
    {
        return match ($this->sortedBy()) {
            'date' => $this->timestamp($b['latest']) <=> $this->timestamp($a['latest']),
            'project' => strcasecmp($a['project']->title, $b['project']->title)
                ?: strcasecmp($a['artifact']->title, $b['artifact']->title),
            'artifact' => strcasecmp($a['artifact']->title, $b['artifact']->title)
                ?: strcasecmp($a['project']->title, $b['project']->title),
            default => $b['count'] <=> $a['count']
                ?: $this->timestamp($b['latest']) <=> $this->timestamp($a['latest']),
        };
    }

    private function timestamp(?CarbonInterface $at): int
    {
        return $at?->getTimestamp() ?? 0;
    }
}
