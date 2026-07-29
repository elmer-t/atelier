<?php

namespace App\Livewire\Public;

use App\Livewire\Concerns\ResolvesCommenter;
use App\Models\Artifact;
use App\Models\Project;
use App\Support\Artifacts\ArtifactAttention;
use App\Support\Artifacts\FeedbackAttention;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The pages panel: the Project's stage Artifacts, its Downloads, and — for a viewer the
 * app recognises — a mark per page saying what its feedback still wants from them.
 *
 * It is a Livewire component rather than plain markup only because those marks go stale:
 * opening a Thread in the rail changes what this panel should say, and the panel says it
 * in words as well as in a mark. Re-drawing server-side on `thread-seen` keeps the two
 * in step for free; patching them from the client would not.
 */
class ProjectPages extends Component
{
    use ResolvesCommenter;

    public Project $project;

    public ?Artifact $current = null;

    /**
     * Attention per artifact id, computed once per render for the whole panel.
     *
     * @return array<int, ArtifactAttention>
     */
    #[Computed]
    public function attention(): array
    {
        return app(FeedbackAttention::class)->for($this->project, $this->currentCommenter());
    }

    /**
     * @return Collection<int, Artifact>
     */
    #[Computed]
    public function stageArtifacts(): Collection
    {
        return $this->project->artifacts->filter->showsInStage();
    }

    /**
     * @return Collection<int, Artifact>
     */
    #[Computed]
    public function downloads(): Collection
    {
        return $this->project->artifacts->filter->isDownload();
    }

    /**
     * A Thread elsewhere on the page was read, so at least one page's mark has moved.
     * Dropping the cache is the whole handler: the re-render recomputes it.
     */
    #[On('thread-seen')]
    public function refreshAttention(): void
    {
        unset($this->attention);
    }

    public function render(): View
    {
        return view('livewire.public.project-pages');
    }
}
