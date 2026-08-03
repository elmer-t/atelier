<x-public.error :title="__('System error')" :heading="__('There is an error in our system')" code="500">
    {{ __('The error is in our system. It is not an error in your data. We have a record of the problem. Wait a short time. Then try again.') }}

    <x-slot:action>
        <a href="{{ route('home') }}"
           class="inline-flex items-center rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
            {{ __('Go to the home page') }}
        </a>
    </x-slot:action>
</x-public.error>
