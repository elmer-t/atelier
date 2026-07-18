<?php

namespace App\Models;

use App\Enums\AssetOrigin;
use App\Enums\AssetPlacement;
use App\Enums\AssetType;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $title
 * @property AssetType $type
 * @property AssetPlacement|null $placement
 * @property int $sort_order
 * @property string|null $body
 * @property string|null $bundle_path
 * @property string|null $entry_file
 * @property string|null $stored_path
 * @property string|null $original_filename
 * @property string|null $mime_type
 * @property int|null $size_bytes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 */
#[Fillable(['title', 'type', 'placement', 'sort_order', 'body', 'bundle_path', 'entry_file', 'stored_path', 'original_filename', 'mime_type', 'size_bytes'])]
class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AssetType::class,
            'placement' => AssetPlacement::class,
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isMarkdown(): bool
    {
        return $this->type === AssetType::Markdown;
    }

    public function isHtml(): bool
    {
        return $this->type === AssetType::Html;
    }

    public function isFile(): bool
    {
        return $this->type === AssetType::File;
    }

    public function origin(): AssetOrigin
    {
        return $this->type->origin();
    }

    /**
     * Whether this asset opens in the main stage (and appears in the page sidebar).
     * Markdown and HTML always stage; a File stages only when placed there.
     */
    public function showsInStage(): bool
    {
        return ! $this->isFile() || $this->placement === AssetPlacement::Stage;
    }

    /**
     * Whether this asset belongs in the downloads list (download-only Files).
     */
    public function isDownload(): bool
    {
        return $this->isFile() && $this->placement === AssetPlacement::Download;
    }

    /**
     * The full sandbox origin URL to this HTML asset's entry file, or null.
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
