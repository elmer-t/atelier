<?php

namespace App\Models;

use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A piece of feedback on an Artifact. A root Comment (parent_id null) carries an Anchor
 * and resolution state and heads a Thread; a Reply (parent_id set) carries neither and is
 * never itself replied to — Threads are one level deep.
 *
 * @property int $id
 * @property int $artifact_id
 * @property int $user_id
 * @property int|null $parent_id
 * @property string $body
 * @property array<string, mixed>|null $anchor
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Artifact $artifact
 * @property-read User $author
 * @property-read Comment|null $parent
 * @property-read User|null $resolver
 * @property-read Collection<int, Comment> $replies
 */
#[Fillable(['artifact_id', 'user_id', 'parent_id', 'body', 'anchor'])]
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anchor' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Artifact, $this>
     */
    public function artifact(): BelongsTo
    {
        return $this->belongsTo(Artifact::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Comment, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * @param  Builder<Comment>  $query
     */
    public function scopeRoots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }
}
