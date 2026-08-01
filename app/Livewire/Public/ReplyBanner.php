<?php

namespace App\Livewire\Public;

use App\Livewire\Concerns\ResolvesCommenter;
use App\Models\Artifact;
use App\Models\Project;
use App\Support\Artifacts\ArtifactAttention;
use App\Support\Artifacts\FeedbackAttention;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The Client's away-from-page reply signal, brought back onto the page (#35). A
 * dismissible strip at the top of the public stage — "You have N new replies" —
 * shown only to a viewer the app recognises (ADR-0003) who actually has unread
 * replies waiting. Anonymous viewers and identified viewers with nothing unread see
 * nothing at all.
 *
 * It is purely presentational: it reads the same {@see FeedbackAttention} totals the
 * pages panel already draws, mints no data, and links to the first artifact carrying
 * an unread reply. Dismiss hides it for the rest of the visit — the true unread still
 * clears the normal way, through the CommentRead watermark when a thread is opened —
 * so a re-render (say, on `thread-seen`) recomputes rather than resurrects it.
 */
class ReplyBanner extends Component
{
    use ResolvesCommenter;

    public Project $project;

    /** Hidden for the visit once dismissed; remembered in the session, not the client. */
    public bool $dismissed = false;

    public function mount(): void
    {
        $this->dismissed = (bool) session($this->dismissKey(), false);
    }

    /**
     * Per-artifact attention for this viewer, resolved once per request and memoised so
     * the count and the link target share the single pass. Empty for a viewer the app
     * does not recognise, which reads as nothing unread — an anonymous visitor is
     * answerable for no thread (ADR-0003).
     *
     * @var array<int, ArtifactAttention>|null
     */
    private ?array $attentionCache = null;

    /**
     * Total unread replies waiting on this viewer across the whole project.
     */
    #[Computed]
    public function unreadCount(): int
    {
        return array_sum(array_map(fn (ArtifactAttention $artifact): int => $artifact->unread, $this->attention()));
    }

    /**
     * The first stage artifact with an unread reply, in stage order — where the banner
     * link lands. Null when nothing is unread (first() finds nothing).
     */
    #[Computed]
    public function firstUnreadArtifact(): ?Artifact
    {
        $attention = $this->attention();

        return $this->project->artifacts
            ->filter->showsInStage()
            ->first(fn (Artifact $artifact): bool => ($attention[$artifact->id]->unread ?? 0) > 0);
    }

    public function dismiss(): void
    {
        $this->dismissed = true;

        session([$this->dismissKey() => true]);
    }

    /**
     * A thread elsewhere on the page was read, so the count may have moved. Dropping both
     * the memo and the computed caches is the whole handler: the re-render recomputes.
     */
    #[On('thread-seen')]
    public function refresh(): void
    {
        $this->attentionCache = null;

        unset($this->unreadCount, $this->firstUnreadArtifact);
    }

    /**
     * @return array<int, ArtifactAttention>
     */
    private function attention(): array
    {
        if ($this->attentionCache !== null) {
            return $this->attentionCache;
        }

        $commenter = $this->currentCommenter();

        return $this->attentionCache = $commenter === null
            ? []
            : app(FeedbackAttention::class)->for($this->project, $commenter);
    }

    private function dismissKey(): string
    {
        return 'atelier.reply_banner_dismissed.'.$this->project->id;
    }

    public function render(): View
    {
        return view('livewire.public.reply-banner');
    }
}
