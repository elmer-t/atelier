<?php

namespace App\Livewire;

use App\Models\Artifact;
use App\Models\Project;
use App\Notifications\ArtifactCommentPosted;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The Creator's in-app bell: an unread-count badge over a dropdown of the per-event
 * comment/reply notifications the app already writes to the `notifications` table
 * (#35). No new table and no new event source — the bell only surfaces what
 * {@see ArtifactCommentPosted} has been recording all along, and
 * renders each row generically so a future event type can slot in beside them.
 *
 * The badge is the unread count and nothing clears it on open; reading is an explicit
 * act. Clicking a row marks that one read and lands on the artifact, and "mark all as
 * read" clears the rest. The badge stays fresh on a light poll of the count alone —
 * the full list is only queried once the panel is actually open.
 */
class NotificationBell extends Component
{
    /** How many notifications the panel shows; older ones simply fall off the list. */
    private const LIST_LIMIT = 15;

    /**
     * Whether the dropdown is open. The list query is gated on this, so while the bell
     * sits closed — the common case — the background poll that re-renders the component
     * costs only the cheap count. The list loads when the panel opens and refreshes with
     * it while open.
     */
    public bool $panelOpen = false;

    /**
     * The unread badge count. Polled on its own — the one query that runs every tick.
     */
    #[Computed]
    public function unreadCount(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    /**
     * The rows the open panel draws, newest first. Empty until the panel is opened, so
     * a closed bell never pays for the list.
     *
     * @return Collection<int, DatabaseNotification>
     */
    #[Computed]
    public function notifications(): Collection
    {
        if (! $this->panelOpen) {
            return collect();
        }

        return Auth::user()->notifications()
            ->latest()
            ->limit(self::LIST_LIMIT)
            ->get();
    }

    public function open(): void
    {
        $this->panelOpen = true;
    }

    public function close(): void
    {
        $this->panelOpen = false;
    }

    /**
     * Mark one notification read and go to the artifact it points at. A signed-in
     * Creator skips the password gate (EnsureProjectAccessible), so this lands even on
     * a private project. A notification whose artifact or project has since been
     * deleted still clears — it just refreshes in place instead of navigating.
     */
    public function markRead(string $id): mixed
    {
        $notification = Auth::user()->notifications()->find($id);

        if ($notification === null) {
            return null;
        }

        $notification->markAsRead();
        unset($this->unreadCount, $this->notifications);

        $project = Project::find($notification->data['project_id'] ?? null);
        $artifact = Artifact::find($notification->data['artifact_id'] ?? null);

        if ($project === null || $artifact === null) {
            return null;
        }

        return $this->redirect(route('project.artifact', [$project, $artifact]));
    }

    /**
     * Clear the badge in one go, leaving the rows in place so the panel still reads.
     */
    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();

        unset($this->unreadCount, $this->notifications);
    }

    public function render(): View
    {
        return view('livewire.notification-bell');
    }
}
