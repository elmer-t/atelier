<x-public.error :title="__('No access')" :heading="__('You do not have access to this page')" code="403">
    {{ __('Your link does not give access to this page. If this is an error, ask the person who sent the link for the correct link.') }}

    <x-slot:action>
        <a href="{{ route('home') }}"
           class="inline-flex items-center rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
            {{ __('Go to the home page') }}
        </a>
    </x-slot:action>
</x-public.error>
