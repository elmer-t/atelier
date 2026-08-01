<?php

use App\Models\User;

/**
 * The admin side carries a chrome bar of its own, cut to the same height as the stage
 * bar on the feedback pages. The bell lives in it, at the right edge, and only there.
 */
it('heads the admin pages with a chrome bar the height of the stage bar', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-test="admin-chrome"', escape: false)
        ->assertSee('var(--stage-bar-height)', escape: false);
});

/**
 * Flux pulls the header in beside the sidebar when the sidebar is written first, which
 * would leave the bar stopping at the sidebar's edge. Order is the whole mechanism, so
 * it is what this pins down.
 */
it('runs the bar across the sidebar as well as the working area', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['data-test="admin-chrome"', 'data-flux-sidebar'], escape: false);
});

it('stands the bell in the chrome bar, and nowhere else', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('dashboard'))->assertOk();

    // Right-aligned: the spacer ahead of it pushes the bell to the end of the bar.
    $response->assertSeeInOrder([
        'data-test="admin-chrome"',
        'data-flux-spacer',
        'data-test="notification-bell"',
    ], escape: false);

    expect(substr_count($response->getContent(), 'data-test="notification-bell"'))->toBe(1);
});

it('gives every admin page the same bar', function (string $route) {
    $this->actingAs(User::factory()->create());

    $this->get(route($route))
        ->assertOk()
        ->assertSee('data-test="admin-chrome"', escape: false)
        ->assertSee('data-test="notification-bell"', escape: false);
})->with(['admin.projects', 'admin.users', 'profile.edit']);
