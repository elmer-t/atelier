<?php

namespace App\Enums;

/**
 * What an Artifact's feedback asks of one viewer, as the pages panel draws it.
 *
 * A four-step ladder, descending in weight: the only filled mark in the panel is the
 * one thing genuinely new to this viewer. Unread and Awaiting are kept apart because
 * "new to me" and "still on me" are different facts — one mark carries both without a
 * second affordance, and neither is worth saying twice.
 */
enum AttentionLevel: string
{
    /** Unresolved, waiting on this viewer, and not opened since the latest Comment landed. */
    case Unread = 'unread';

    /** Unresolved and waiting on this viewer, but they have opened it. */
    case Awaiting = 'awaiting';

    /** Unresolved feedback that is not waiting on this viewer. */
    case Open = 'open';

    /** Every Thread on the Artifact is Resolved. */
    case Resolved = 'resolved';

    /** No Threads at all. */
    case None = 'none';

    /** Whether this level is asking the viewer for something. */
    public function wantsYou(): bool
    {
        return $this === self::Unread || $this === self::Awaiting;
    }

    public function label(): string
    {
        return match ($this) {
            self::Unread => __('New replies waiting on you'),
            self::Awaiting => __('Waiting on you'),
            self::Open => __('Open feedback'),
            self::Resolved => __('All feedback resolved'),
            self::None => '',
        };
    }
}
