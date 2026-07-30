{{--
    Button that copies a value to the clipboard. navigator.clipboard is undefined
    outside a secure context (e.g. http://*.test), so it falls back to selecting
    an off-screen twin of the value and using execCommand.
--}}
@props(['text', 'toast' => 'Copied'])

<div class="contents" x-data="{
    text: @js($text),
    async copy() {
        try {
            if (window.isSecureContext && navigator.clipboard) {
                await navigator.clipboard.writeText(this.text);
            } else {
                this.$refs.source.select();
                document.execCommand('copy');
            }
            $flux.toast(@js($toast));
        } catch (e) {
            $flux.toast({ variant: 'danger', text: 'Could not copy. Select the value and copy manually.' });
        }
    },
}">
    <input x-ref="source" readonly value="{{ $text }}" class="sr-only" tabindex="-1" aria-hidden="true">

    <flux:button x-on:click="copy" {{ $attributes }}>{{ $slot }}</flux:button>
</div>
