<x-public.error :title="__('No access')" :heading="__('You don\'t have access to this')" code="403">
    {{ __('This page is out of reach with the link you followed. If you think that\'s a mistake, ask whoever shared it for the right link.') }}

    <x-slot:action>
        <a href="{{ route('home') }}"
           class="inline-flex items-center rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
            {{ __('Go to the homepage') }}
        </a>
    </x-slot:action>
</x-public.error>
