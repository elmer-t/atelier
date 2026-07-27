<?php

namespace App\Policies;

use App\Models\User;

/**
 * Who may act on whom in the Users panel (#30). The `creator` middleware already
 * keeps non-Creators out of the admin area; these rules are the second half —
 * which of the actions a Creator sees are legitimate against a given target.
 *
 * Two invariants sit behind the destructive rules: an install must never end with
 * zero Creators, and the Agent User is never deleted from the UI — revoking its
 * token is the kill switch (ADR-0006).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isCreator();
    }

    public function view(User $user, User $target): bool
    {
        return $user->isCreator();
    }

    /**
     * Inviting mints a passwordless Creator account, so it is Creator-only.
     */
    public function invite(User $user): bool
    {
        return $user->isCreator();
    }

    /**
     * Deleting a User cascade-deletes the Comments they authored, which is why
     * deactivation is the default for Clients. Refused for the Agent and for
     * oneself.
     *
     * Refusing self-removal is also what keeps the install from reaching zero
     * Creators: the actor here is always a Creator, so removing anyone else
     * necessarily leaves at least them. A separate "is this the last Creator"
     * count would be unreachable — the only way to be the last one and be the
     * target is to be the actor, which this already refuses.
     */
    public function delete(User $user, User $target): bool
    {
        if (! $user->isCreator() || $target->isAgent()) {
            return false;
        }

        return ! $target->is($user);
    }

    /**
     * Deactivation is the Client-shaped alternative to deletion: it keeps their
     * feedback and stops them leaving more. Creators and the Agent are out of
     * scope — a Creator who should lose access is removed, an Agent is revoked.
     */
    public function deactivate(User $user, User $target): bool
    {
        return $user->isCreator() && $target->isClient() && ! $target->isDeactivated();
    }

    public function reactivate(User $user, User $target): bool
    {
        return $user->isCreator() && $target->isClient() && $target->isDeactivated();
    }

    /**
     * Minting and revoking the Agent's MCP token — the browser equivalent of
     * `atelier:agent-token`.
     */
    public function manageAgentToken(User $user): bool
    {
        return $user->isCreator();
    }
}
