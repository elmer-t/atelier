{{--
    The Creator's bell. The badge polls the unread count on its own; the list is only
    fetched once the panel is open (see the component). Alpine owns the open/close of
    the panel and mirrors it to the server so the list query stays gated, while the
    badge and rows are Livewire's to draw.
--}}
<div
    x-data="{ open: false }"
    x-on:keydown.escape.window="open && (open = false, $wire.close())"
    class="relative"
    wire:poll.60s
>
    <button
        type="button"
        x-on:click="open = ! open; open ? $wire.open() : $wire.close()"
        class="relative flex items-center justify-center rounded-lg p-2 text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white"
        aria-label="{{ __('Notifications') }}"
        data-test="notification-bell"
    >
        <flux:icon.bell variant="outline" class="size-5" />

        @if ($this->unreadCount > 0)
            <span
                class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold leading-none text-white"
                data-test="notification-badge"
            >{{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}</span>
        @endif
    </button>

    <div
        x-show="open"
        x-cloak
        x-on:click.outside="open && (open = false, $wire.close())"
        x-transition.origin.top.right
        class="absolute right-0 z-50 mt-2 w-80 origin-top-right overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
    >
        <div class="flex items-center justify-between border-b border-zinc-100 px-4 py-3 dark:border-zinc-800">
            <span class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Notifications') }}</span>

            @if ($this->unreadCount > 0)
                <button
                    type="button"
                    wire:click="markAllRead"
                    class="text-xs text-zinc-500 transition hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white"
                    data-test="mark-all-read"
                >{{ __('Mark all as read') }}</button>
            @endif
        </div>

        <div class="max-h-96 overflow-y-auto">
            @forelse ($this->notifications as $notification)
                @php $data = $notification->data; @endphp
                <button
                    type="button"
                    wire:key="notification-{{ $notification->id }}"
                    wire:click="markRead('{{ $notification->id }}')"
                    @class([
                        'flex w-full flex-col gap-0.5 border-b border-zinc-50 px-4 py-3 text-left transition last:border-b-0 hover:bg-zinc-50 dark:border-zinc-800/60 dark:hover:bg-zinc-800',
                        'bg-zinc-50/70 dark:bg-zinc-800/40' => $notification->unread(),
                    ])
                >
                    {{-- Weight is what separates read from unread: the whole line carries it,
                         so nothing inside may set a weight of its own. --}}
                    <span
                        data-test="notification-message"
                        @class([
                            'text-sm text-zinc-800 dark:text-zinc-100',
                            'font-semibold' => $notification->unread(),
                            'font-normal' => $notification->read(),
                        ])
                    >
                        {{ $data['author'] ?? __('Someone') }}
                        {{ ($data['is_reply'] ?? false) ? __('replied on') : __('commented on') }}
                        {{ $data['artifact_title'] ?? __('an artifact') }}
                    </span>
                    <span class="text-xs text-zinc-400 dark:text-zinc-500">
                        @if (! empty($data['project_title'])){{ $data['project_title'] }} · @endif{{ $notification->created_at?->diffForHumans() }}
                    </span>
                </button>
            @empty
                <p class="px-4 py-10 text-center text-sm text-zinc-400 dark:text-zinc-500">
                    {{ __('No notifications yet.') }}
                </p>
            @endforelse
        </div>

        {{-- Browser-push opt-in. Explicit and contextual: the permission prompt is only
             ever raised by this click, never on load (#35). --}}
        <div
            class="border-t border-zinc-100 px-4 py-3 dark:border-zinc-800"
            x-data="{
                on: false,

                /** Whether the last attempt was turned down — see the settings page for why. */
                failed: false,

                init() {
                    this.on = window.atelierPush?.isSubscribed?.() ?? false;
                },

                async toggle() {
                    if (this.on) {
                        await window.atelierPush?.disable();
                        this.on = false;
                        this.failed = false;

                        return;
                    }

                    const result = await window.atelierPush?.enable('creator');

                    this.on = result?.ok ?? false;
                    this.failed = ! this.on;
                },
            }"
        >
            <button
                type="button"
                x-on:click="toggle()"
                class="flex w-full items-center gap-2 text-xs text-zinc-500 transition hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white"
                data-test="enable-push"
            >
                <flux:icon.bell-alert variant="outline" class="size-4" />
                <span x-show="! on">{{ __('Enable browser notifications') }}</span>
                <span x-show="on" x-cloak>{{ __('Disable browser notifications') }}</span>
            </button>

            {{-- The bell has no room to explain; it says that it failed and points at the
                 page that names the reason. --}}
            <p x-show="failed" x-cloak class="mt-2 text-xs text-red-500 dark:text-red-400" data-test="bell-push-failed">
                {{ __('Could not turn notifications on.') }}
                <a href="{{ route('notifications.edit') }}" class="underline underline-offset-2" wire:navigate>{{ __('See why') }}</a>
            </p>
        </div>
    </div>
</div>
