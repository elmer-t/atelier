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

it('keeps the notification settings page behind auth', function () {
    $this->get(route('notifications.edit'))
        ->assertRedirect(route('login'));
});
