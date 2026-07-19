<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the Creator when a Comment or Reply lands on one of their projects
 * (ADR-0003). Client reply-notifications are deferred until a Client verifies
 * their address via the upgrade path, so only Creators are notified today.
 */
class ArtifactCommentPosted extends Notification
{
    use Queueable;

    public function __construct(public Comment $comment) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
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
