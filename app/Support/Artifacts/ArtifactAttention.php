<?php

namespace App\Support\Artifacts;

use App\Enums\AttentionLevel;

/**
 * One Artifact's feedback, counted from one viewer's position. Immutable, and computed
 * for a whole Project in one pass by {@see FeedbackAttention}.
 */
final readonly class ArtifactAttention
{
    public function __construct(
        /** Threads waiting on the viewer that they have not opened since the latest Comment. */
        public int $unread = 0,
        /** Threads waiting on the viewer that they have already opened. */
        public int $awaiting = 0,
        /** Unresolved Threads that are not waiting on the viewer. */
        public int $open = 0,
        public int $resolved = 0,
        public int $threads = 0,
    ) {}

    /**
     * The single state the panel draws, most urgent first. A Thread that wants the
     * viewer outranks bulk: one unread reply on an Artifact carrying twenty resolved
     * Threads is still the reason to go there.
     */
    public function level(): AttentionLevel
    {
        return match (true) {
            $this->unread > 0 => AttentionLevel::Unread,
            $this->awaiting > 0 => AttentionLevel::Awaiting,
            $this->open > 0 => AttentionLevel::Open,
            $this->threads > 0 => AttentionLevel::Resolved,
            default => AttentionLevel::None,
        };
    }

    /** Threads waiting on the viewer, read or not. */
    public function waiting(): int
    {
        return $this->unread + $this->awaiting;
    }
}
