<?php

namespace App\Models;

use Database\Factories\ArtifactRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One immutable saved state of a markdown Artifact's body (ADR-0005). Rows are
 * appended, never updated or deleted in normal operation; the newest is the live
 * content and is pointed at by Artifact::current_revision_id. Attribution is
 * per-Revision via user_id, which is how human edits are told from agent edits.
 *
 * @property int $id
 * @property int $artifact_id
 * @property string $body
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property-read Artifact $artifact
 * @property-read User|null $author
 */
#[Fillable(['body', 'user_id'])]
class ArtifactRevision extends Model
{
    /** @use HasFactory<ArtifactRevisionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

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
}
