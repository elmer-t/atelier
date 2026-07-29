<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\CommentRead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommentRead>
 */
class CommentReadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->client(),
            'comment_id' => Comment::factory(),
            'seen_through_comment_id' => 1,
        ];
    }

    /**
     * A read of the whole Thread as it currently stands — the state
     * {@see CommentRead::record()} writes.
     */
    public function caughtUpWith(Comment $thread): static
    {
        return $this->state(fn (): array => [
            'comment_id' => $thread->id,
            'seen_through_comment_id' => max($thread->id, (int) $thread->replies()->max('id')),
        ]);
    }
}
