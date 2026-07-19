<?php

use App\Mcp\AgentAbilities;
use App\Models\User;

it('provisions a single Agent User and mints a scoped token', function () {
    $this->artisan('atelier:agent-token')->assertSuccessful();

    $agent = User::where('email', 'agent@atelier.local')->firstOrFail();
    expect($agent->isAgent())->toBeTrue()
        ->and($agent->password)->toBeNull()
        ->and($agent->tokens()->count())->toBe(1);

    // The token grants exactly the capability-boundary abilities — no resolve/lifecycle.
    $token = $agent->tokens()->firstOrFail();
    expect($token->abilities)->toBe(AgentAbilities::all())
        ->and(in_array('comment:resolve', $token->abilities, true))->toBeFalse();

    // Running again reuses the same Agent User (exactly one per install).
    $this->artisan('atelier:agent-token')->assertSuccessful();
    expect(User::where('role', 'agent')->count())->toBe(1)
        ->and($agent->tokens()->count())->toBe(2);
});

it('revokes all agent tokens', function () {
    $this->artisan('atelier:agent-token')->assertSuccessful();
    $agent = User::where('email', 'agent@atelier.local')->firstOrFail();
    expect($agent->tokens()->count())->toBe(1);

    $this->artisan('atelier:agent-token', ['--revoke' => true])->assertSuccessful();
    expect($agent->tokens()->count())->toBe(0);
});
