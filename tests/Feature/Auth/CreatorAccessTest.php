<?php

use App\Models\User;

// The gate, not the frontend build, is under test — skip Vite asset resolution
// so a rendered page does not depend on a compiled manifest.
beforeEach(fn () => $this->withoutVite());

it('lets a creator reach the admin area', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.projects'))->assertOk();
});

it('lets a creator reach the dashboard', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertOk();
});

// Verified so the `verified` middleware passes and the `creator` gate is the
// thing under test — proving the role boundary holds even if a non-Creator
// clears email verification.
it('forbids a verified client from the operator area', function (string $route) {
    $this->actingAs(User::factory()->client()->create(['email_verified_at' => now()]));

    $this->get(route($route))->assertForbidden();
})->with(['admin.projects', 'dashboard']);

it('forbids a verified agent from the operator area', function (string $route) {
    $this->actingAs(User::factory()->agent()->create(['email_verified_at' => now()]));

    $this->get(route($route))->assertForbidden();
})->with(['admin.projects', 'dashboard']);
