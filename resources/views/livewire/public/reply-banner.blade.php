{{--
    Shown only when a recognised viewer has unread replies waiting and has not dismissed
    the banner for this visit. Everything else — anonymous, zero unread, or dismissed —
    renders an empty root so the component can still poll itself back into view later.
--}}
<div>
    @if (! $dismissed && $this->unreadCount > 0 && $this->firstUnreadArtifact)
        <div
            class="sticky top-0 z-20 flex items-center gap-3 border-b border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-100"
            data-test="reply-banner"
        >
            <flux:icon.chat-bubble-left-right variant="outline" class="size-4 shrink-0" />

            <a
                href="{{ route('project.artifact', [$project, $this->firstUnreadArtifact]) }}"
                class="flex-1 font-medium hover:underline"
                wire:navigate
            >
                {{ trans_choice('{1}You have :count new reply|[2,*]You have :count new replies', $this->unreadCount, ['count' => $this->unreadCount]) }}
            </a>

            {{-- Offer the away-from-page channel right where the reply signal lands. The
                 permission prompt only fires on this click, never on load (#35). --}}
            <button
                type="button"
                x-data="{ on: false }"
                x-init="on = window.atelierPush?.isSubscribed?.() ?? false"
                x-show="! on"
                x-on:click="window.atelierPush?.enable('client').then(ok => on = ok)"
                class="shrink-0 text-xs font-medium underline-offset-2 hover:underline"
                data-test="banner-enable-push"
            >{{ __('Get notified') }}</button>

            <button
                type="button"
                wire:click="dismiss"
                class="shrink-0 rounded p-1 text-amber-700 transition hover:bg-amber-100 hover:text-amber-900 dark:text-amber-300 dark:hover:bg-amber-900/40 dark:hover:text-amber-100"
                aria-label="{{ __('Dismiss') }}"
                data-test="dismiss-banner"
            >
                <flux:icon.x-mark variant="micro" class="size-4" />
            </button>
        </div>
    @endif
</div>
