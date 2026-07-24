<?php

namespace App\Livewire\Public;

use App\Enums\UserRole;
use App\Models\Artifact;
use App\Models\Comment;
use App\Models\User;
use App\Notifications\ArtifactCommentPosted;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The feedback surface for one Artifact on the shared project page. Viewing stays
 * anonymous; identity (name + email) is captured only at comment time and a
 * passwordless Client User is found-or-created then (ADR-0003). Attribution and
 * authorization always re-resolve the acting User server-side — never from a public
 * property — so a tampered client cannot impersonate another User.
 */
class ArtifactComments extends Component
{
    public Artifact $artifact;

    /** Whether the current visitor has an established commenting identity. */
    public bool $identified = false;

    /** Display-only name of the established identity (never used for authorization). */
    public string $identityName = '';

    public string $captureName = '';

    public string $captureEmail = '';

    public string $draft = '';

    /**
     * The anchor for the root comment being composed, discriminated by artifact
     * origin (ADR-0004): text_range | image_region | html_point.
     *
     * @var array<string, mixed>
     */
    public array $draftAnchor = [];

    public ?int $replyingToId = null;

    public string $replyDraft = '';

    /** Whether this visitor has collapsed the feedback rail (persisted across visits). */
    public bool $collapsed = false;

    /** The session key remembering the active commenter across this browsing session. */
    private const SESSION_KEY = 'atelier.commenter';

    /** The long-lived cookie that re-attributes a returning visitor (ADR-0003). */
    private const COOKIE_NAME = 'atelier_commenter';

    /** The long-lived cookie remembering this visitor's collapsed/expanded rail preference. */
    private const COLLAPSED_COOKIE = 'atelier_feedback_collapsed';

    public function mount(): void
    {
        $this->identified = $this->currentCommenter() !== null;
        $this->identityName = $this->currentCommenter()?->name ?? '';
        $this->collapsed = request()->cookie(self::COLLAPSED_COOKIE) === '1';
    }

    /**
     * The Threads on this Artifact — anchored roots, each with its flat replies.
     *
     * @return Collection<int, Comment>
     */
    #[Computed]
    public function threads(): Collection
    {
        return $this->artifact->comments()
            ->roots()
            ->with(['author', 'replies.author', 'resolver'])
            ->latest()
            ->latest('id')
            ->get();
    }

    /**
     * Remember whether this visitor keeps the feedback rail collapsed. Persisted for a
     * year so the preference survives reloads and return visits — same cookie mechanism
     * as the commenter identity (encrypted in transit, read back transparently).
     */
    public function setCollapsed(bool $collapsed): void
    {
        $this->collapsed = $collapsed;

        Cookie::queue(self::COLLAPSED_COOKIE, $collapsed ? '1' : '0', 60 * 24 * 365);
    }

    /**
     * Whether the current viewer may resolve Threads (a logged-in Creator only).
     */
    #[Computed]
    public function canResolve(): bool
    {
        return Auth::check() && Auth::user()->isCreator();
    }

    /**
     * Capture name + email and bind the visitor to a passwordless Client User,
     * remembered in the session and a return-visit cookie. Posting is immediate —
     * no verification round-trip.
     */
    public function saveIdentity(): void
    {
        $validated = $this->validate([
            'captureName' => ['required', 'string', 'max:255'],
            'captureEmail' => ['required', 'email', 'max:255'],
        ]);

        $existing = User::where('email', $validated['captureEmail'])->first();

        if ($existing && ! $existing->isClient()) {
            // A credentialed account owns this address; don't let a passwordless
            // commenter act as them (email squatting guard, ADR-0003).
            $this->addError('captureEmail', __('That email belongs to an account. Please log in to comment.'));

            return;
        }

        $user = $existing ?? User::create([
            'name' => $validated['captureName'],
            'email' => $validated['captureEmail'],
            'role' => UserRole::Client,
            'password' => null,
        ]);

        $this->rememberCommenter($user);

        $this->identified = true;
        $this->identityName = $user->name;
        $this->captureName = '';
        $this->captureEmail = '';
    }

    /**
     * Post an anchored root Comment, starting a new Thread.
     */
    public function postComment(): void
    {
        $commenter = $this->requireCommenter();

        if ($commenter === null) {
            return;
        }

        $this->validate([
            'draft' => ['required', 'string', 'max:5000'],
            'draftAnchor.type' => ['required', 'in:text_range,image_region,html_point'],
        ]);

        $comment = $this->artifact->comments()->create([
            'user_id' => $commenter->id,
            'body' => $this->draft,
            'anchor' => $this->draftAnchor,
        ]);

        $this->notifyCreators($comment);

        $this->reset('draft', 'draftAnchor');
        $this->refreshThreads();
    }

    /**
     * Reply within a Thread. The reply shares the root's Anchor and is one level deep.
     */
    public function reply(int $rootId): void
    {
        $commenter = $this->requireCommenter();

        if ($commenter === null) {
            return;
        }

        $root = $this->artifact->comments()->roots()->findOrFail($rootId);

        $this->validate(['replyDraft' => ['required', 'string', 'max:5000']]);

        $comment = $this->artifact->comments()->create([
            'user_id' => $commenter->id,
            'parent_id' => $root->id,
            'body' => $this->replyDraft,
        ]);

        $this->notifyCreators($comment);

        $this->reset('replyDraft', 'replyingToId');
        $this->refreshThreads();
    }

    public function startReply(int $rootId): void
    {
        $this->replyingToId = $rootId;
        $this->replyDraft = '';
    }

    public function resolve(int $rootId): void
    {
        $this->toggleResolved($rootId, true);
    }

    public function unresolve(int $rootId): void
    {
        $this->toggleResolved($rootId, false);
    }

    public function deleteComment(int $commentId): void
    {
        $commenter = $this->currentCommenter();
        $comment = $this->artifact->comments()->findOrFail($commentId);

        if ($commenter === null || Gate::forUser($commenter)->denies('delete', $comment)) {
            abort(403);
        }

        $comment->delete();
        $this->refreshThreads();
    }

    private function toggleResolved(int $rootId, bool $resolved): void
    {
        $commenter = $this->currentCommenter();
        $comment = $this->artifact->comments()->roots()->findOrFail($rootId);

        if ($commenter === null || Gate::forUser($commenter)->denies('resolve', $comment)) {
            abort(403);
        }

        $comment->forceFill([
            'resolved_at' => $resolved ? now() : null,
            'resolved_by' => $resolved ? $commenter->id : null,
        ])->save();

        $this->refreshThreads();
    }

    /**
     * Drop the cached Threads and ask the client to re-draw its anchor highlights, which
     * live in the stage DOM outside this component and so survive the morph untouched.
     */
    private function refreshThreads(): void
    {
        unset($this->threads);

        $this->dispatch('threads-updated');
    }

    /**
     * The acting commenter, re-resolved server-side on every action: a logged-in
     * User, else the session/cookie-remembered passwordless Client, else null.
     */
    private function currentCommenter(): ?User
    {
        if (Auth::check()) {
            return Auth::user();
        }

        $id = session(self::SESSION_KEY) ?? request()->cookie(self::COOKIE_NAME);

        if ($id !== null && ($user = User::find($id)) !== null) {
            if (session(self::SESSION_KEY) === null) {
                session([self::SESSION_KEY => $user->id]);
            }

            return $user;
        }

        return null;
    }

    private function requireCommenter(): ?User
    {
        $commenter = $this->currentCommenter();

        if ($commenter === null) {
            $this->identified = false;
            $this->addError('draft', __('Add your name and email first.'));
        }

        return $commenter;
    }

    private function rememberCommenter(User $user): void
    {
        session([self::SESSION_KEY => $user->id]);
        Cookie::queue(self::COOKIE_NAME, (string) $user->id, 60 * 24 * 365);
    }

    private function notifyCreators(Comment $comment): void
    {
        $creators = User::where('role', UserRole::Creator)
            ->where('id', '!=', $comment->user_id)
            ->get();

        if ($creators->isNotEmpty()) {
            Notification::send($creators, new ArtifactCommentPosted($comment));
        }
    }

    public function render(): View
    {
        return view('livewire.public.artifact-comments');
    }
}
