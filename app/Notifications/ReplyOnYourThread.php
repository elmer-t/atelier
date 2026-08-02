<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Sent to the author of a root Thread when someone else replies to it — the reply
 * signal a Client used to have no way to receive once they closed the tab (#35).
 *
 * Push-only for now: it reaches whoever started the Thread, Client or Creator, on
 * whatever device they granted permission. That consent is the browser's permission
 * grant, so this sidesteps ADR-0003's email-verification gate rather than waiting on
 * it. A `mail` channel can join under #21 once the email reply-loop is built; the
 * replier themselves is never told, and a Thread author who has subscribed no device
 * is sent nothing at all.
 *
 * Queued so the outbound web-push call never blocks or fails the reply POST that
 * triggered it. Needs a running queue worker (see the deployment README).
 */
class ReplyOnYourThread extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Comment $reply) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof User && $notifiable->hasPushSubscriptions()) {
            return [WebPushChannel::class];
        }

        return [];
    }

    /**
     * The browser push the Thread author gets. Clicking it opens the artifact the
     * Thread lives on, which the service worker reads off `data.url`.
     */
    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        $artifact = $this->reply->artifact;
        $project = $artifact->project;

        return (new WebPushMessage)
            ->title(__(':name replied to your feedback', [
                'name' => $this->reply->author->name,
            ]))
            ->body($this->reply->body)
            ->data(['url' => route('project.artifact', [$project, $artifact])]);
    }
}
