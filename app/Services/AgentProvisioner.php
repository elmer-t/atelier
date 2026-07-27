<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Mcp\AgentAbilities;
use App\Models\User;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The single place the Agent User and its MCP token are managed (ADR-0006), shared
 * by `atelier:agent-token` and the Users panel so the CLI and the browser cannot
 * drift on either the fixed email or the granted abilities.
 *
 * Exactly one Agent User is expected per install; the token is revocable so a leaked
 * or misbehaving agent can be cut off without touching the Creator's credentials.
 */
class AgentProvisioner
{
    /** The fixed identity of the install's one Agent User. */
    public const EMAIL = 'agent@atelier.local';

    /** The Sanctum token name, so the panel and the command mint the same thing. */
    public const TOKEN_NAME = 'mcp';

    /**
     * The Agent User, created on first use. Repairs the role if the row was
     * created some other way (a Client who happened to claim the address).
     */
    public function user(): User
    {
        $agent = User::firstOrCreate(
            ['email' => self::EMAIL],
            ['name' => 'Atelier Agent', 'role' => UserRole::Agent, 'password' => null],
        );

        if (! $agent->isAgent()) {
            $agent->forceFill(['role' => UserRole::Agent])->save();
        }

        return $agent;
    }

    /**
     * The Agent User if one has been provisioned, without creating it — the read
     * path, so merely opening the panel does not mint a row.
     */
    public function existingUser(): ?User
    {
        return User::where('email', self::EMAIL)->first();
    }

    /**
     * The Agent's current token, if any. Only one is expected; the newest wins
     * when a mint was interleaved with a stale session.
     */
    public function currentToken(): ?PersonalAccessToken
    {
        $token = $this->existingUser()?->tokens()->latest('id')->first();

        return $token instanceof PersonalAccessToken ? $token : null;
    }

    /**
     * Mint a token scoped to the capability boundary. Additive rather than
     * replacing, so an in-flight agent keeps working while its replacement is
     * rolled out; `revoke()` is what closes the door. The plain-text value on the
     * returned token is the only time it is ever visible.
     */
    public function mint(): NewAccessToken
    {
        return $this->user()->createToken(self::TOKEN_NAME, AgentAbilities::all());
    }

    /**
     * Cut the agent off. Returns how many tokens were revoked.
     */
    public function revoke(): int
    {
        $agent = $this->existingUser();

        if ($agent === null) {
            return 0;
        }

        $count = $agent->tokens()->count();
        $agent->tokens()->delete();

        return $count;
    }
}
