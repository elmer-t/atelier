<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Mcp\AgentAbilities;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Creator-side provisioning of the dedicated Agent User and its revocable MCP token
 * (ADR-0006). Exactly one Agent User is expected per install. Minting hands the agent
 * a token scoped to the fixed capability boundary; revoking cuts a leaked or
 * misbehaving agent off without touching the Creator's own credentials.
 */
class ProvisionAgent extends Command
{
    protected $signature = 'atelier:agent-token
        {--revoke : Revoke all existing agent tokens instead of minting one}';

    protected $description = 'Provision the Agent User and mint (or revoke) a revocable MCP access token.';

    public function handle(): int
    {
        $agent = User::firstOrCreate(
            ['email' => 'agent@atelier.local'],
            ['name' => 'Atelier Agent', 'role' => UserRole::Agent, 'password' => null],
        );

        if (! $agent->isAgent()) {
            $agent->forceFill(['role' => UserRole::Agent])->save();
        }

        if ($this->option('revoke')) {
            $count = $agent->tokens()->count();
            $agent->tokens()->delete();

            $this->info("Revoked {$count} agent token(s). The agent can no longer reach the MCP server.");

            return self::SUCCESS;
        }

        $token = $agent->createToken('mcp', AgentAbilities::all());

        $this->info('Agent token minted. Store it now — it is shown only once:');
        $this->newLine();
        $this->line($token->plainTextToken);
        $this->newLine();
        $this->comment('Abilities: '.implode(', ', AgentAbilities::all()));

        return self::SUCCESS;
    }
}
