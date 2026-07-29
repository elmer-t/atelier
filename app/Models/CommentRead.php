<?php

namespace App\Models;

use Database\Factories\CommentReadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One record of a User having seen a Thread as it stood at that moment.
 *
 * The mark is a watermark — the newest Comment in the Thread when it was opened —
 * rather than a boolean, so a Reply arriving afterwards raises the Thread as unseen
 * again without anything having to be reset. Rows are appended, never updated, so the
 * table keeps when each batch of activity was caught up with; a reader re-opening a
 * Thread that has not moved writes nothing.
 *
 * @property int $id
 * @property int $user_id
 * @property int $comment_id
 * @property int $seen_through_comment_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $reader
 * @property-read Comment $thread
 */
#[Fillable(['user_id', 'comment_id', 'seen_through_comment_id'])]
class CommentRead extends Model
{
    /** @use HasFactory<CommentReadFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function reader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Comment, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'comment_id');
    }

    /**
     * Record that this reader has now seen the Thread as it currently stands, returning
     * the appended row — or null when the watermark did not move, which is every re-open
     * of a Thread nothing has been added to. That is also what bounds the table: a row
     * can only be written once a Comment has actually landed since the last one.
     */
    public static function record(User $reader, Comment $thread): ?self
    {
        $seenThrough = max($thread->id, (int) $thread->replies()->max('id'));

        $current = (int) static::query()
            ->where('user_id', $reader->id)
            ->where('comment_id', $thread->id)
            ->max('seen_through_comment_id');

        if ($current >= $seenThrough) {
            return null;
        }

        return static::create([
            'user_id' => $reader->id,
            'comment_id' => $thread->id,
            'seen_through_comment_id' => $seenThrough,
        ]);
    }
}
