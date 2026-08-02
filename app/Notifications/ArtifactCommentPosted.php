<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Sent to the Creator who owns the project when a Comment or Reply lands on one
 * of its artifacts (ADR-0003). Client reply-notifications are deferred until a
 * Client verifies their address via the upgrade path, so only Creators are
 * notified today.
 *
 * Queued so the outbound web-push and mail calls happen out of band: a slow or
 * failing push endpoint must never add latency to — or fail — the comment POST
 * that triggered it. Needs a running queue worker (see the deployment README).
 */
class ArtifactCommentPosted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Comment $comment) {}

    /**
     * Mail and the in-app bell always; a browser push too when this Creator has
     * subscribed a device. Web push bypasses ADR-0003's email-verification gate —
     * consent here is the browser's permission grant, not a verified address.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['mail', 'database'];

        if ($notifiable instanceof User && $notifiable->hasPushSubscriptions()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $artifact = $this->comment->artifact;
        $project = $artifact->project;
        $verb = $this->comment->isReply() ? 'replied on' : 'commented on';

        return (new MailMessage)
            ->subject(__(':name :verb :title', [
                'name' => $this->comment->author->name,
                'verb' => $verb,
                'title' => $artifact->title,
            ]))
            ->line(__(':name left feedback on ":artifact" in :project.', [
                'name' => $this->comment->author->name,
                'artifact' => $artifact->title,
                'project' => $project->title,
            ]))
            ->line($this->comment->body)
            ->action(__('View project'), route('project.artifact', [$project, $artifact]));
    }

    /**
     * The browser push a subscribed Creator gets. Clicking it opens the artifact,
     * which the service worker reads off `data.url`.
     */
    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        $artifact = $this->comment->artifact;
        $project = $artifact->project;
        $verb = $this->comment->isReply() ? __('replied on') : __('commented on');

        return (new WebPushMessage)
            ->title(__(':name :verb :title', [
                'name' => $this->comment->author->name,
                'verb' => $verb,
                'title' => $artifact->title,
            ]))
            ->body($this->comment->body)
            ->data(['url' => route('project.artifact', [$project, $artifact])]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $artifact = $this->comment->artifact;

        return [
            'comment_id' => $this->comment->id,
            'artifact_id' => $artifact->id,
            'artifact_title' => $artifact->title,
            'project_id' => $artifact->project_id,
            'project_title' => $artifact->project->title,
            'author' => $this->comment->author->name,
            'is_reply' => $this->comment->isReply(),
            'body' => $this->comment->body,
        ];
    }

    /**
     * The project the commented-on artifact belongs to.
     */
    public function project(): Project
    {
        return $this->comment->artifact->project;
    }
}
