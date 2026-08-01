<?php

namespace App\Livewire\Settings;

use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The Settings home for browser push (#35). The same explicit, contextual opt-in the
 * bell dropdown offers, given a permanent place a Creator can find it again. All the
 * work is client-side (`window.atelierPush`) — the permission prompt only ever fires
 * from the toggle, never on load — so this component carries no server state of its own.
 */
#[Title('Notification settings')]
class Notifications extends Component
{
    //
}
