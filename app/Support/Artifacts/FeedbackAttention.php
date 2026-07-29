<?php

namespace App\Support\Artifacts;

use App\Models\Comment;
use App\Models\CommentRead;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Where a Project's feedback still wants one viewer — the projection behind the pages
 * panel's status marks. The Client-facing sibling of {@see FeedbackDigest}, which
 * answers the same question for an Agent.
 *
 * A Thread is waiting on the viewer when it is unresolved and its most recent Comment
 * is someone else's — read as "the ball is in your court". Which Threads that applies
 * to depends on who is looking: a Client is only answerable for Threads they are in,
 * while the Creator is answerable for all of them, since gathering feedback is the
 * point of sharing the link.
 *
 * Waiting Threads then split on whether they have been seen (CommentRead), so the
 * panel can tell "new to you" from "still on you" without asking the viewer to mark
 * anything by hand.
 *
 * A viewer with no established identity — nobody has commented from this browser yet —
 * is answerable for nothing, so every Thread reads as Open to them.
 */
class FeedbackAttention
{
    /**
     * Attention for every Artifact in the Project, keyed by artifact id. Artifacts with
     * no Threads are present too, so a caller can read a level for any of them.
     *
     * @return array<int, ArtifactAttention>
     */
    public function for(Project $project, ?User $viewer): array
    {
        $artifactIds = $project->artifacts->pluck('id');

        /** @var Collection<int, Collection<int, Comment>> $threads */
        $threads = Comment::query()
            ->whereIn('artifact_id', $artifactIds)
            ->roots()
            ->with('replies:id,parent_id,user_id')
            ->get(['id', 'artifact_id', 'user_id', 'resolved_at'])
            ->groupBy('artifact_id');

        $watermarks = $this->watermarks($viewer, $threads->flatten()->pluck('id'));

        $attention = [];

        foreach ($artifactIds as $artifactId) {
            $attention[$artifactId] = $this->count(
                $threads[$artifactId] ?? collect(),
                $viewer,
                $watermarks,
            );
        }

        return $attention;
    }

    /**
     * @param  Collection<int, Comment>  $threads
     * @param  array<int, int>  $watermarks
     */
    private function count(Collection $threads, ?User $viewer, array $watermarks): ArtifactAttention
    {
        $unread = 0;
        $awaiting = 0;
        $open = 0;
        $resolved = 0;

        foreach ($threads as $thread) {
            if ($thread->isResolved()) {
                $resolved++;

                continue;
            }

            if (! $this->waitsOn($thread, $viewer)) {
                $open++;

                continue;
            }

            $latest = $this->latestCommentId($thread);

            if (($watermarks[$thread->id] ?? 0) >= $latest) {
                $awaiting++;
            } else {
                $unread++;
            }
        }

        return new ArtifactAttention(
            unread: $unread,
            awaiting: $awaiting,
            open: $open,
            resolved: $resolved,
            threads: $threads->count(),
        );
    }

    /**
     * Whether this unresolved Thread is the viewer's to answer: someone else spoke last,
     * and the viewer is either in the Thread or the Creator, who owns all of them.
     */
    private function waitsOn(Comment $thread, ?User $viewer): bool
    {
        if ($viewer === null) {
            return false;
        }

        $lastAuthorId = $thread->replies->isEmpty()
            ? $thread->user_id
            : $thread->replies->last()->user_id;

        if ($lastAuthorId === $viewer->id) {
            return false;
        }

        return $viewer->isCreator()
            || $thread->user_id === $viewer->id
            || $thread->replies->contains('user_id', $viewer->id);
    }

    private function latestCommentId(Comment $thread): int
    {
        return max($thread->id, (int) $thread->replies->max('id'));
    }

    /**
     * The viewer's high-water mark per Thread, in one query. Reads are appended rather
     * than updated, so the live answer is the highest mark recorded.
     *
     * @param  Collection<int, int>  $threadIds
     * @return array<int, int>
     */
    private function watermarks(?User $viewer, Collection $threadIds): array
    {
        if ($viewer === null || $threadIds->isEmpty()) {
            return [];
        }

        return CommentRead::query()
            ->where('user_id', $viewer->id)
            ->whereIn('comment_id', $threadIds)
            ->groupBy('comment_id')
            ->selectRaw('comment_id, MAX(seen_through_comment_id) as seen_through')
            ->pluck('seen_through', 'comment_id')
            ->all();
    }
}
