<?php

namespace App\Console\Commands;

use App\Mcp\AgentAbilities;
use App\Services\AgentProvisioner;
use Illuminate\Console\Command;

/**
 * Creator-side provisioning of the dedicated Agent User and its revocable MCP token
 * (ADR-0006), for installs driven from the shell. The Users panel offers the same two
 * actions in the browser; both go through {@see AgentProvisioner} so they cannot drift.
 */
class ProvisionAgent extends Command
{
    protected $signature = 'atelier:agent-token
        {--revoke : Revoke all existing agent tokens instead of minting one}';

    protected $description = 'Provision the Agent User and mint (or revoke) a revocable MCP access token.';

    public function handle(AgentProvisioner $provisioner): int
    {
        if ($this->option('revoke')) {
            $count = $provisioner->revoke();

            $this->info("Revoked {$count} agent token(s). The agent can no longer reach the MCP server.");

            return self::SUCCESS;
        }

        $token = $provisioner->mint();

        $this->info('Agent token minted. Store it now — it is shown only once:');
        $this->newLine();
        $this->line($token->plainTextToken);
        $this->newLine();
        $this->comment('Abilities: '.implode(', ', AgentAbilities::all()));

        return self::SUCCESS;
    }
}
