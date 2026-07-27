<?php

use App\Livewire\Admin\Users\AgentAccess;
use App\Livewire\Admin\Users\Index;
use App\Mcp\AgentAbilities;
use App\Models\User;
use App\Services\AgentProvisioner;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    $this->actingAs(User::factory()->create());
});

it('is forbidden to a non-creator', function () {
    $this->actingAs(User::factory()->client()->create(['email_verified_at' => now()]));

    $this->get(route('admin.users.agent'))->assertForbidden();
});

it('reports no token before the agent has ever been provisioned', function () {
    $this->get(route('admin.users.agent'))->assertOk();

    Livewire::test(AgentAccess::class)
        ->assertSee('No token. The agent cannot reach the MCP server.')
        ->assertSet('plainTextToken', null);

    // Reading the panel must not mint an Agent User as a side effect.
    expect(User::where('email', AgentProvisioner::EMAIL)->exists())->toBeFalse();
});

it('mints a token with the same abilities the artisan command grants, shown once', function () {
    $component = Livewire::test(AgentAccess::class)->call('mint');

    $agent = User::where('email', AgentProvisioner::EMAIL)->firstOrFail();
    $token = $agent->tokens()->firstOrFail();

    expect($agent->isAgent())->toBeTrue()
        ->and($token->abilities)->toBe(AgentAbilities::all());

    $plain = $component->get('plainTextToken');
    expect($plain)->toBeString()->and($plain)->toContain('|');

    $component->assertSee($plain)->assertSee('Copy this token now');

    // Re-rendering from a fresh mount never shows it again.
    Livewire::test(AgentAccess::class)
        ->assertSet('plainTextToken', null)
        ->assertDontSee($plain);
});

it('revokes every agent token', function () {
    app(AgentProvisioner::class)->mint();
    app(AgentProvisioner::class)->mint();

    $agent = User::where('email', AgentProvisioner::EMAIL)->firstOrFail();
    expect($agent->tokens()->count())->toBe(2);

    Livewire::test(AgentAccess::class)
        ->call('revoke')
        ->assertSee('No token. The agent cannot reach the MCP server.');

    expect($agent->tokens()->count())->toBe(0);
});

it('shows when the token was minted and last used', function () {
    app(AgentProvisioner::class)->mint();

    $agent = User::where('email', AgentProvisioner::EMAIL)->firstOrFail();
    $agent->tokens()->update(['last_used_at' => now()->subHours(3)]);

    Livewire::test(AgentAccess::class)
        ->assertSee('last used 3 hours ago')
        ->assertSee(AgentAbilities::ARTIFACT_WRITE);
});

it('never lets the agent user be deleted from the users panel', function () {
    $agent = app(AgentProvisioner::class)->user();

    Livewire::test(Index::class)
        ->call('delete', $agent->id)
        ->assertForbidden();

    expect($agent->fresh())->not->toBeNull();
});
