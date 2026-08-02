<?php

namespace Database\Factories;

use App\Models\Artifact;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'artifact_id' => Artifact::factory(),
            'user_id' => User::factory()->client(),
            'parent_id' => null,
            'body' => fake()->sentence(),
            'anchor' => ['type' => 'text_range', 'quote' => fake()->words(3, true)],
            'resolved_at' => null,
            'resolved_by' => null,
        ];
    }

    /**
     * A reply beneath the given root Comment: no anchor of its own.
     */
    public function replyTo(Comment $root): static
    {
        return $this->state(fn () => [
            'artifact_id' => $root->artifact_id,
            'parent_id' => $root->id,
            'anchor' => null,
        ]);
    }

    public function resolved(?User $by = null): static
    {
        return $this->state(fn () => [
            'resolved_at' => now(),
            'resolved_by' => $by->id ?? User::factory(),
        ]);
    }
}
