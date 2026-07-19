<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

/**
 * Feedback permissions key off the User's role, not identity heuristics (ADR-0003):
 * an author manages their own Comment, and the Creator alone resolves Threads and
 * deletes anyone's Comment — the human gate that closes the loop.
 */
class CommentPolicy
{
    /**
     * Only the author may edit their own Comment.
     */
    public function update(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id;
    }

    /**
     * The author deletes their own Comment; the Creator deletes any.
     */
    public function delete(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id || $user->isCreator();
    }

    /**
     * Resolving a Thread is Creator-only and only meaningful on a root Comment.
     * An Agent-role User (#17) is excluded here for free.
     */
    public function resolve(User $user, Comment $comment): bool
    {
        return $user->isCreator() && $comment->isRoot();
    }
}
