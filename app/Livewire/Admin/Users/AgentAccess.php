<?php

namespace App\Livewire\Admin\Users;

use App\Mcp\AgentAbilities;
use App\Models\User;
use App\Services\AgentProvisioner;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The browser half of `atelier:agent-token` (ADR-0006): whether the Agent holds a
 * token, when it was minted, when it was last used, and the two actions that matter
 * — mint a fresh one, or revoke everything. Revoking is the kill switch for a leaked
 * or misbehaving agent, which is why the Agent User itself is not deletable here.
 *
 * Both actions go through {@see AgentProvisioner}, so the abilities granted in the
 * browser are exactly the ones the command grants.
 */
#[Title('Agent access')]
class AgentAccess extends Component
{
    /**
     * The plain-text token, held only for the render that follows a mint. Locked
     * so it can never be set from the client, and never persisted anywhere else —
     * this is the one and only time it is visible.
     */
    #[Locked]
    public ?string $plainTextToken = null;

    public function mount(): void
    {
        $this->authorize('manageAgentToken', User::class);
    }

    /**
     * The Agent User, or null when no agent has ever been provisioned. Reading the
     * panel must not mint the row, so this is the non-creating lookup.
     */
    #[Computed]
    public function agent(): ?User
    {
        return $this->provisioner()->existingUser();
    }

    #[Computed]
    public function token(): ?PersonalAccessToken
    {
        return $this->provisioner()->currentToken();
    }

    /**
     * How many tokens are live. More than one means a rollover is in progress —
     * both keep working until revoked.
     */
    #[Computed]
    public function tokenCount(): int
    {
        return $this->provisioner()->existingUser()?->tokens()->count() ?? 0;
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function abilities(): array
    {
        return AgentAbilities::all();
    }

    public function mint(AgentProvisioner $provisioner): void
    {
        $this->authorize('manageAgentToken', User::class);

        $this->plainTextToken = $provisioner->mint()->plainTextToken;

        $this->forgetAgentState();

        Flux::toast(variant: 'success', text: __('Token minted. Copy it now — it is shown only once.'));
    }

    public function revoke(AgentProvisioner $provisioner): void
    {
        $this->authorize('manageAgentToken', User::class);

        $count = $provisioner->revoke();

        $this->plainTextToken = null;
        $this->forgetAgentState();

        Flux::toast(variant: 'success', text: trans_choice(
            '{0}There was no token to revoke.|{1}Token revoked. The agent can no longer reach the MCP server.|[2,*]:count tokens revoked. The agent can no longer reach the MCP server.',
            $count,
            ['count' => $count],
        ));
    }

    public function render(): View
    {
        return view('livewire.admin.users.agent-access');
    }

    private function forgetAgentState(): void
    {
        unset($this->agent, $this->token, $this->tokenCount);
    }

    private function provisioner(): AgentProvisioner
    {
        return app(AgentProvisioner::class);
    }
}
