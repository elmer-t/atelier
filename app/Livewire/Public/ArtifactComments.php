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
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
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

    /**
     * Honeypot. Rendered off-screen and out of the tab order, so a human never
     * fills it in and anything that does is filling fields it cannot see.
     */
    public string $website = '';

    /** The session key remembering the active commenter across this browsing session. */
    private const SESSION_KEY = 'atelier.commenter';

    /** When this visitor first opened the feedback rail — the baseline for the submit-timing floor. */
    private const OPENED_AT_KEY = 'atelier.comment_form_opened_at';

    /** Rate-limiter key prefixes, completed with the address or commenter they bound. */
    private const IDENTITY_LIMIT_KEY = 'comment-identity:';

    private const POST_IP_LIMIT_KEY = 'comment-post-ip:';

    private const POST_USER_LIMIT_KEY = 'comment-post-user:';

    /** The long-lived cookie that re-attributes a returning visitor (ADR-0003). */
    private const COOKIE_NAME = 'atelier_commenter';

    /** The long-lived cookie remembering this visitor's collapsed/expanded rail preference. */
    private const COLLAPSED_COOKIE = 'atelier_feedback_collapsed';

    public function mount(): void
    {
        $this->identified = $this->currentCommenter() !== null;
        $this->identityName = $this->currentCommenter()?->name ?? '';
        $this->collapsed = request()->cookie(self::COLLAPSED_COOKIE) === '1';

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

    /**
     * The acting commenter, re-resolved server-side on every action: a logged-in
     * User, else the session/cookie-remembered passwordless Client, else null.
     *
     * A deactivated User is nobody here — otherwise the session or the year-long
     * return-visit cookie would keep letting them post after being cut off (#30).
     */
    private function currentCommenter(): ?User
    {
        $user = Auth::user();

        if ($user instanceof User) {
            return $user->isDeactivated() ? null : $user;
        }

        $id = session(self::SESSION_KEY) ?? request()->cookie(self::COOKIE_NAME);

        if ($id === null) {
            return null;
        }

        $remembered = User::query()->whereKey((string) $id)->first();

        if ($remembered === null || $remembered->isDeactivated()) {
            return null;
        }

        if (session(self::SESSION_KEY) === null) {
            session([self::SESSION_KEY => $remembered->id]);
        }

        return $remembered;
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
