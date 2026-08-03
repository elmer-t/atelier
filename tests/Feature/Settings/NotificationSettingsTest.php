<?php

use App\Models\User;

it('shows the Creator a browser-notification opt-in on the settings page', function () {
    $creator = User::factory()->create();

    $this->actingAs($creator)
        ->get(route('notifications.edit'))
        ->assertOk()
        ->assertSee('Notifications')
        ->assertSeeHtml('data-test="settings-enable-push"');
});

/**
 * A refused subscription used to leave the page unchanged, with the browser's complaint
 * reaching only the console. The page now carries a line for every reason the opt-in can
 * report, so whatever comes back can be named.
 */
it('carries a plain-language line for every way the opt-in can fail', function () {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('notifications.edit'))
        ->assertOk()
        ->assertSeeHtml('data-test="push-failure"');

    foreach (['unsupported', 'unconfigured', 'denied', 'no-worker', 'push-service', 'server', 'unknown'] as $reason) {
        expect($response->getContent())->toContain($reason);
    }

    // The one a browser refusing to register reports, in words that say what to check.
    $response->assertSee('Your browser cannot connect to its push service', escape: false);
});

it('keeps the notification settings page behind auth', function () {
    $this->get(route('notifications.edit'))
        ->assertRedirect(route('login'));
});
