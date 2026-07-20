<?php

namespace App\Models;

use App\Enums\ArtifactOrigin;
use App\Enums\ArtifactPlacement;
use App\Enums\ArtifactType;
use Database\Factories\ArtifactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $title
 * @property ArtifactType $type
 * @property ArtifactPlacement|null $placement
 * @property int $sort_order
 * @property int|null $current_revision_id
 * @property string|null $bundle_path
 * @property string|null $entry_file
 * @property string|null $stored_path
 * @property string|null $original_filename
 * @property string|null $mime_type
 * @property int|null $size_bytes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $body
 * @property-read Project $project
 * @property-read ArtifactRevision|null $currentRevision
 * @property-read Collection<int, ArtifactRevision> $revisions
 */
#[Fillable(['title', 'type', 'placement', 'sort_order', 'bundle_path', 'entry_file', 'stored_path', 'original_filename', 'mime_type', 'size_bytes'])]
class Artifact extends Model
{
    /** @use HasFactory<ArtifactFactory> */
    use HasFactory;

    /**
     * Transient seed for the first Revision when an Artifact is built through a
     * factory or test helper (never persisted; a column-backed body no longer
     * exists). Application code writes Revisions explicitly via the writer.
     */
    public ?string $draftBody = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ArtifactType::class,
            'placement' => ArtifactPlacement::class,
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * The live Revision whose body is the current markdown content.
     *
     * @return BelongsTo<ArtifactRevision, $this>
     */
    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(ArtifactRevision::class, 'current_revision_id');
    }

    /**
     * The full append-only Revision history, oldest to newest.
     *
     * @return HasMany<ArtifactRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(ArtifactRevision::class)->orderBy('id');
    }

    /**
     * The live markdown body, read through the current Revision so existing
     * render/edit call-sites keep reading `$artifact->body` unchanged (ADR-0005).
     *
     * @return Attribute<string|null, never>
     */
    protected function body(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->currentRevision?->body);
    }

    public function isMarkdown(): bool
    {
        return $this->type === ArtifactType::Markdown;
    }

    public function isHtml(): bool
    {
        return $this->type === ArtifactType::Html;
    }

    public function isFile(): bool
    {
        return $this->type === ArtifactType::File;
    }

    public function origin(): ArtifactOrigin
    {
        return $this->type->origin();
    }

    /**
     * Whether this artifact opens in the main stage (and appears in the page sidebar).
     * Markdown and HTML always stage; a File stages only when placed there.
     */
    public function showsInStage(): bool
    {
        return ! $this->isFile() || $this->placement === ArtifactPlacement::Stage;
    }

    /**
     * Whether this artifact belongs in the downloads list (download-only Files).
     */
    public function isDownload(): bool
    {
        return $this->isFile() && $this->placement === ArtifactPlacement::Download;
    }

    /**
     * Re-point this artifact's unpacked bundle from one sandbox token to another,
     * preserving the artifact-scoped suffix. A no-op when there is no bundle under
     * the old token. Used when a project's link (and sandbox token) is reissued.
     */
    public function rebaseBundlePath(string $previousToken, string $newToken): void
    {
        $prefix = $previousToken.'/';

        if (blank($this->bundle_path) || ! str_starts_with($this->bundle_path, $prefix)) {
            return;
        }

        $this->update(['bundle_path' => $newToken.'/'.substr($this->bundle_path, strlen($prefix))]);
    }

    /**
     * The full sandbox origin URL to this HTML artifact's entry file, or null.
     */
    public function sandboxUrl(): ?string
    {
        if (! $this->isHtml() || blank($this->bundle_path)) {
            return null;
        }

        $base = rtrim((string) config('atelier.sandbox.url'), '/');

        return "{$base}/{$this->bundle_path}/{$this->entry_file}";
    }
}
