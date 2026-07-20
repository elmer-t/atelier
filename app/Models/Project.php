<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\ProjectVisibility;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $sandbox_token
 * @property ProjectStatus $status
 * @property ProjectVisibility $visibility
 * @property string|null $password_hash
 * @property int $session_version
 * @property Carbon|null $expires_at
 * @property Carbon|null $first_viewed_at
 * @property Carbon|null $last_viewed_at
 * @property int $view_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'visibility' => ProjectVisibility::class,
            'expires_at' => 'datetime',
            'first_viewed_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'view_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Project $project): void {
            $project->slug ??= self::generateToken(config('atelier.slug_bytes'));
            $project->sandbox_token ??= self::generateToken(config('atelier.sandbox_token_bytes'));
            $project->session_version ??= 1;
        });
    }

    /**
     * @return HasMany<Artifact, $this>
     */
    public function artifacts(): HasMany
    {
        return $this->hasMany(Artifact::class)->orderBy('sort_order');
    }

    public function isPrivate(): bool
    {
        return $this->visibility === ProjectVisibility::Private;
    }

    public function isPublic(): bool
    {
        return $this->visibility === ProjectVisibility::Public;
    }

    public function isActive(): bool
    {
        return $this->status === ProjectStatus::Active;
    }

    public function isArchived(): bool
    {
        return $this->status === ProjectStatus::Archived;
    }

    /**
     * Whether the project has an expiry that has already elapsed.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Whether the project's public links are hard-disabled — archived or past
     * expiry — and must 404 everywhere (view, gate, files, images) just like an
     * archived project. See docs/atelier.specs.md §9.
     */
    public function isHardDisabled(): bool
    {
        return $this->isArchived() || $this->isExpired();
    }

    /**
     * Record an aggregate view of the project: stamp the first/last-viewed times
     * and bump the counter. Deliberately aggregate — no per-recipient identity is
     * stored (link recipients never log in). See docs/atelier.specs.md §1.
     */
    public function recordView(): void
    {
        $now = Carbon::now();

        $this->forceFill([
            'first_viewed_at' => $this->first_viewed_at ?? $now,
            'last_viewed_at' => $now,
        ])->save();

        // Atomic so concurrent views never lose an increment.
        $this->increment('view_count');
    }

    /**
     * Set (or rotate) the project's password. Passing null clears it.
     * Rotating bumps the session version, invalidating existing viewer sessions.
     */
    public function setPassword(?string $plain): void
    {
        $this->password_hash = filled($plain) ? Hash::make($plain) : null;
        $this->session_version++;
        $this->save();
    }

    public function checkPassword(string $plain): bool
    {
        return filled($this->password_hash) && Hash::check($plain, $this->password_hash);
    }

    /**
     * The session key under which a passed password gate is remembered. Includes
     * the session version so rotating the password invalidates prior sessions.
     */
    public function sessionKey(): string
    {
        return "atelier.project.{$this->id}.v{$this->session_version}";
    }

    /**
     * Generate a URL-safe CSPRNG token of the given byte length.
     */
    public static function generateToken(int $bytes): string
    {
        return bin2hex(random_bytes(max(1, $bytes)));
    }
}
