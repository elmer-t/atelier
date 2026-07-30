<?php

namespace App\Livewire\Public;

use App\Enums\UserRole;
use App\Livewire\Concerns\ResolvesCommenter;
use App\Models\Artifact;
use App\Models\Comment;
use App\Models\CommentRead;
use App\Models\User;
use App\Notifications\ArtifactCommentPosted;
use App\Notifications\ReplyOnYourThread;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * The feedback surface for one Artifact on the shared project page. Viewing stays
 * anonymous; identity (name + email) is captured only at comment time and a
 * passwordless Client User is found-or-created then (ADR-0003). Attribution and
 * authorization always re-resolve the acting User server-side — never from a public
 * property — so a tampered client cannot impersonate another User.
 *
 * Because the surface is open to anyone holding a project link, the three paths a
 * stranger can reach — identity capture, posting, replying — are rate limited and
 * screened for automation before they mint a durable row. Resolving and deleting
 * are not: those are gated on authorization instead, and no anonymous visitor can
 * reach them. See config/atelier.php ('comments') for the limits.
 */
class ArtifactComments extends Component
{
    use ResolvesCommenter;

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

    /**
     * Whether to offer the browser-push opt-in. Raised the moment a Client posts, so the
     * away-from-page channel is offered right where a reply signal would matter — the one
     * contextual moment a first-time commenter (with nothing unread yet, so no banner) can
     * be reached. The prompt itself only shows if the device is not already subscribed;
     * the permission request is still gated on the explicit click (#35).
     */
    public bool $offerPush = false;

    /**
     * Honeypot. Rendered off-screen and out of the tab order, so a human never
     * fills it in and anything that does is filling fields it cannot see.
     */
    public string $website = '';

    /** When this visitor first opened the feedback rail — the baseline for the submit-timing floor. */
    private const OPENED_AT_KEY = 'atelier.comment_form_opened_at';

    /** Rate-limiter key prefixes, completed with the address or commenter they bound. */
    private const IDENTITY_LIMIT_KEY = 'comment-identity:';

    private const POST_IP_LIMIT_KEY = 'comment-post-ip:';

    private const POST_USER_LIMIT_KEY = 'comment-post-user:';

    public function mount(): void
    {
        $this->identified = $this->currentCommenter() !== null;
        $this->identityName = $this->currentCommenter()?->name ?? '';

        if (! session()->has(self::OPENED_AT_KEY)) {
            session([self::OPENED_AT_KEY => now()->getTimestamp()]);
        }
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
        if (! $this->withinLimit(self::IDENTITY_LIMIT_KEY.request()->ip(), $this->limit('identity_per_minute'), 'captureEmail')) {
            return;
        }

        if ($this->trippedHoneypot() || $this->submittedTooFast()) {
            return;
        }

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

        if ($existing?->isDeactivated()) {
            // The find-or-create would otherwise resurrect a Creator's deactivation
            // on the next comment (#30). Say no more than that the address is
            // unusable — whether someone blocked them is not theirs to learn.
            $this->addError('captureEmail', __('That email cannot be used to comment here.'));

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

        if (! $this->withinPostingLimits($commenter, 'draft')) {
            return;
        }

        if ($this->trippedHoneypot()) {
            return;
        }

        $this->validate([
            'draft' => ['required', 'string', 'max:'.$this->maxBodyLength()],
            'draftAnchor.type' => ['required', 'in:text_range,image_region,html_point'],
        ]);

        $comment = $this->artifact->comments()->create([
            'user_id' => $commenter->id,
            'body' => $this->draft,
            'anchor' => $this->draftAnchor,
        ]);

        $this->notifyProjectOwner($comment);
        $this->offerPushTo($commenter);

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

        if (! $this->withinPostingLimits($commenter, 'replyDraft')) {
            return;
        }

        if ($this->trippedHoneypot()) {
            return;
        }

        $this->validate(['replyDraft' => ['required', 'string', 'max:'.$this->maxBodyLength()]]);

        $comment = $this->artifact->comments()->create([
            'user_id' => $commenter->id,
            'parent_id' => $root->id,
            'body' => $this->replyDraft,
        ]);

        $this->notifyProjectOwner($comment);
        $this->notifyThreadAuthor($root, $comment);
        $this->offerPushTo($commenter);

        $this->reset('replyDraft', 'replyingToId');
        $this->refreshThreads();
    }

    /**
     * Record that the viewer has read this Thread, which the rail calls the moment it
     * is opened. Opening is what counts: a Thread's Replies are not rendered until then
     * (see `.rail-open-only`), so it is the only act that puts them on screen.
     *
     * Renderless — the rail already reflects the click on its own, and this must not
     * cost it a morph. It announces the write instead, so the pages panel can re-draw
     * its status marks; a re-open with nothing new writes nothing and says nothing.
     *
     * Unlike posting, this is not rate limited: it mints a row only when a Comment has
     * genuinely landed since the last one, so repetition cannot inflate the table.
     */
    #[Renderless]
    public function markThreadSeen(int $rootId): void
    {
        $reader = $this->currentCommenter();
        $thread = $this->artifact->comments()->roots()->find($rootId);

        if ($reader === null || $thread === null) {
            return;
        }

        if (CommentRead::record($reader, $thread) !== null) {
            $this->dispatch('thread-seen');
        }
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
     * Whether this submission carries a bot's fingerprint: the honeypot came back
     * filled, which only happens when something is completing fields it cannot see.
     *
     * Rejection is silent by design — an error message would tell an author of
     * automated submissions exactly which field to leave alone next time.
     */
    private function trippedHoneypot(): bool
    {
        return $this->website !== '';
    }

    /**
     * Whether identity arrived faster than a human could plausibly have typed it.
     *
     * Applied at identity capture only. That is the step that mints a durable User
     * row from unverified input, and it is the one moment where a stopwatch is fair:
     * putting one on every later comment would punish a fast typist mid-conversation,
     * and the rate limiter already bounds how much any one visitor can post.
     */
    private function submittedTooFast(): bool
    {
        $floor = (int) config('atelier.comments.min_seconds_before_submit');

        if ($floor <= 0) {
            return false;
        }

        $openedAt = session(self::OPENED_AT_KEY);

        if (! is_int($openedAt)) {
            // No baseline, so this submission has no provenance: mount() writes the
            // key on first render, and a caller that replays the Livewire snapshot
            // without the session cookie never gets one. Fail closed, but stamp a
            // baseline first, so a human whose session merely expired succeeds on
            // their next attempt while a cookie-less replay never does.
            session([self::OPENED_AT_KEY => now()->getTimestamp()]);

            return true;
        }

        return (now()->getTimestamp() - $openedAt) < $floor;
    }

    /**
     * Both posting ceilings, outermost first: the per-address limit still bites when
     * an attacker rotates through fresh identities, which the per-commenter limit
     * alone would not catch. Short-circuits so one rejection consumes one budget.
     */
    private function withinPostingLimits(User $commenter, string $errorField): bool
    {
        return $this->withinLimit(
            self::POST_IP_LIMIT_KEY.request()->ip(),
            $this->limit('posts_per_minute_per_ip'),
            $errorField,
        ) && $this->withinLimit(
            self::POST_USER_LIMIT_KEY.$commenter->id,
            $this->limit('posts_per_minute_per_commenter'),
            $errorField,
        );
    }

    /**
     * Consume one unit of a limiter, surfacing a friendly, non-fatal error on the
     * given field once the visitor has run out. Attempts are counted before the
     * input is validated and before the bot screens run, so neither malformed
     * submissions nor tripped honeypots are a free way to keep making requests.
     *
     * Driven directly rather than through a named limiter (as the auth routes use,
     * FortifyServiceProvider) because those are resolved by route middleware, and
     * these are Livewire actions on a page that has already passed through it.
     */
    private function withinLimit(string $key, int $perMinute, string $errorField): bool
    {
        if (RateLimiter::tooManyAttempts($key, $perMinute)) {
            $this->addError($errorField, __('Too many attempts. Please wait :seconds seconds and try again.', [
                'seconds' => RateLimiter::availableIn($key),
            ]));

            return false;
        }

        RateLimiter::hit($key);

        return true;
    }

    private function limit(string $name): int
    {
        return (int) config('atelier.comments.rate_limits.'.$name);
    }

    private function maxBodyLength(): int
    {
        return (int) config('atelier.comments.max_body_length');
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

    private function requireCommenter(): ?User
    {
        $commenter = $this->currentCommenter();

        if ($commenter === null) {
            $this->identified = false;
            $this->addError('draft', __('Add your name and email first.'));
        }

        return $commenter;
    }

    /**
     * Tell the Creator who owns the commented-on project. An unowned project —
     * one predating ownership, or whose owner was deleted — has nobody to route
     * to, so it falls back to every Creator rather than dropping the feedback on
     * the floor. Either way the comment's own author is never told about it.
     */
    private function notifyProjectOwner(Comment $comment): void
    {
        $owner = $comment->artifact->project->owner;

        $recipients = $owner !== null
            ? collect([$owner])
            : User::where('role', UserRole::Creator)->get();

        $recipients = $recipients->reject(fn (User $user): bool => $user->id === $comment->user_id);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new ArtifactCommentPosted($comment));
        }
    }

    /**
     * Give the Thread's original author the away-from-page reply signal they never
     * had (#35). Push-only, and only when someone *else* replies — a Thread author
     * answering on their own Thread is not told about their own words. A deactivated
     * author is nobody to notify, same as everywhere else identity is resolved.
     */
    private function notifyThreadAuthor(Comment $root, Comment $reply): void
    {
        $author = $root->author;

        if ($author->isDeactivated() || $author->id === $reply->user_id) {
            return;
        }

        $author->notify(new ReplyOnYourThread($reply));
    }

    /**
     * Offer the browser-push opt-in to a Client who has just posted. Creators have the
     * bell and Settings for this, so the contextual post-comment prompt is the Client's
     * alone — and it is only a prompt: the permission request stays gated on their click.
     */
    private function offerPushTo(User $commenter): void
    {
        if ($commenter->isClient()) {
            $this->offerPush = true;
        }
    }

    public function render(): View
    {
        return view('livewire.public.artifact-comments');
    }
}
