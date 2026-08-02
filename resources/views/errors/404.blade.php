<x-public.error :title="__('Not available')" :heading="__('This project isn\'t available')" code="404">
    {{ __('The link may have been archived, replaced, or it may never have existed. If someone shared it with you, ask them for an up-to-date link.') }}

    <x-slot:action>
        <a href="{{ route('home') }}"
           class="inline-flex items-center rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
            {{ __('Go to the homepage') }}
        </a>
    </x-slot:action>
</x-public.error>
