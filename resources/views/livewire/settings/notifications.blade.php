<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Notification settings') }}</flux:heading>

    <x-settings.layout :heading="__('Notifications')" :subheading="__('Choose how this browser lets you know about new feedback')">
        <div
            x-data="{ on: false, supported: true }"
            x-init="supported = window.atelierPush?.supported?.() ?? false; on = window.atelierPush?.isSubscribed?.() ?? false"
        >
            <flux:text class="mb-4">
                {{ __('Get a browser notification when someone comments or replies on your projects, even when Atelier is not open. This device asks your permission the first time you turn it on.') }}
            </flux:text>

            <div x-show="supported">
                <flux:button
                    variant="primary"
                    x-show="! on"
                    x-on:click="window.atelierPush?.enable('creator').then(ok => on = ok)"
                    data-test="settings-enable-push"
                >
                    {{ __('Enable browser notifications') }}
                </flux:button>

                <flux:button
                    variant="danger"
                    x-show="on"
                    x-cloak
                    x-on:click="window.atelierPush?.disable().then(() => on = false)"
                    data-test="settings-disable-push"
                >
                    {{ __('Disable browser notifications') }}
                </flux:button>
            </div>

            <flux:text x-show="! supported" x-cloak class="text-zinc-400 dark:text-zinc-500">
                {{ __('This browser does not support push notifications.') }}
            </flux:text>
        </div>
    </x-settings.layout>
</section>
