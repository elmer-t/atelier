<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Fulfils a data-subject erasure request (GDPR Art. 17) for a passwordless Client
 * (ADR-0003). The Client's identifying fields are anonymised and the bodies of the
 * comments they authored are redacted, while every Comment row — and so the Thread
 * structure, replies and resolution state — is preserved. Only Client Users are
 * erasable this way; refusing Creators/Agents guards the operator's own account.
 */
class ClientDataEraser
{
    /** Replaces an erased Client's display name. */
    public const ANONYMISED_NAME = 'Former participant';

    /** Replaces an erased Comment's body, keeping the row so the Thread survives. */
    public const REDACTED_BODY = '[Removed at the author’s request.]';

    /**
     * Anonymise the Client and redact their authored comments in one transaction.
     *
     * @return array{comments: int} the number of comment bodies redacted
     */
    public function erase(User $client): array
    {
        if (! $client->isClient()) {
            throw new RuntimeException('Only Client Users can be erased; refusing to anonymise a '.$client->role->value.'.');
        }

        return DB::transaction(function () use ($client): array {
            $redacted = $client->comments()->update(['body' => self::REDACTED_BODY]);

            // A unique, non-identifying placeholder — the email column is unique and
            // not nullable, so re-point it at an unroutable address rather than clearing it.
            $client->forceFill([
                'name' => self::ANONYMISED_NAME,
                'email' => 'erased-'.$client->id.'@atelier.invalid',
                'remember_token' => null,
            ])->save();

            return ['comments' => $redacted];
        });
    }
}
