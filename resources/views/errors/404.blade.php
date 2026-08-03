<x-public.error :title="__('Not available')" :heading="__('This page is not available')" code="404">
    {{ __('The link is not correct, or the content is not available. If a person sent you this link, ask that person for a new link.') }}

    <x-slot:action>
        <a href="{{ route('home') }}"
           class="inline-flex items-center rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
            {{ __('Go to the home page') }}
        </a>
    </x-slot:action>
</x-public.error>
