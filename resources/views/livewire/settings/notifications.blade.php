@php
    /**
     * Every way turning push on can fail, said in terms of what to do next. Keyed by the
     * reason `atelierPush.enable()` reports; `unknown` catches anything it has not
     * learned to name yet. Without these the page could only stand still — which is
     * exactly what it did while a browser was rejecting the subscription outright.
     */
    $pushFailures = [
        'unsupported' => __('This browser will not take push notifications on this address. They need a page served over https.'),
        'unconfigured' => __('This copy of Atelier has no push keys set, so notifications cannot be turned on.'),
        'denied' => __('Notifications are blocked for this site. Allow them in your browser settings, then try again.'),
        'no-worker' => __('The background worker that notifications rely on would not start in this browser.'),
        'push-service' => __('Your browser could not register with its own push service. Check that it allows push messaging — some Chromium browsers turn it off by default — then try again.'),
        'server' => __('The subscription was made but Atelier could not store it. Try again in a moment.'),
        'unknown' => __('Notifications could not be turned on in this browser.'),
    ];
@endphp

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Notification settings') }}</flux:heading>

    <x-settings.layout :heading="__('Notifications')" :subheading="__('Choose how this browser lets you know about new feedback')">
        <div
            x-data="{
                on: false,
                supported: true,

                /** The named failure to show, and the browser's own words beneath it. */
                failure: null,
                detail: null,

                messages: @js($pushFailures),

                init() {
                    this.supported = window.atelierPush?.supported?.() ?? false;
                    this.on = window.atelierPush?.isSubscribed?.() ?? false;
                },

                /**
                 * One path for both directions, so a failure is reported the same way
                 * whichever button raised it. `enable()` and `disable()` never reject —
                 * they answer with a reason — so nothing here needs a catch.
                 */
                async toggle(wanted) {
                    if (! window.atelierPush) {
                        this.failure = this.messages.unsupported;

                        return;
                    }

                    const result = wanted
                        ? await window.atelierPush.enable('creator')
                        : await window.atelierPush.disable();

                    this.on = wanted ? result.ok : false;
                    this.failure = result.ok ? null : (this.messages[result.reason] ?? this.messages.unknown);
                    this.detail = result.ok ? null : result.detail;
                },
            }"
        >
            <flux:text class="mb-4">
                {{ __('Get a browser notification when someone comments or replies on your projects, even when Atelier is not open. This device asks your permission the first time you turn it on.') }}
            </flux:text>

            <div x-show="supported">
                <flux:button
                    variant="primary"
                    x-show="! on"
                    x-on:click="toggle(true)"
                    data-test="settings-enable-push"
                >
                    {{ __('Enable browser notifications') }}
                </flux:button>

                <flux:button
                    variant="danger"
                    x-show="on"
                    x-cloak
                    x-on:click="toggle(false)"
                    data-test="settings-disable-push"
                >
                    {{ __('Disable browser notifications') }}
                </flux:button>
            </div>

            <flux:text x-show="! supported" x-cloak class="text-zinc-400 dark:text-zinc-500">
                {{ __('This browser does not support push notifications.') }}
            </flux:text>

            {{-- The browser's own message is kept, quieter, under the plain-language one:
                 it is the only thing that tells a failure like "Registration failed" apart
                 from any other, and it is what gets pasted into a bug report. --}}
            <div x-show="failure" x-cloak class="mt-4" data-test="push-failure">
                <flux:text class="text-red-600 dark:text-red-400" x-text="failure" />
                <flux:text x-show="detail" x-cloak class="mt-1 font-mono text-xs text-zinc-400 dark:text-zinc-500" x-text="detail" />
            </div>
        </div>
    </x-settings.layout>
</section>
