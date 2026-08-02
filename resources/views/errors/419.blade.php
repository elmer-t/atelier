<x-public.error :title="__('Session expired')" :heading="__('Your session expired')" code="419">
    {{ __('For your security the page timed out before it was submitted. Head back and try again — if the project is password-protected you may need to unlock it once more.') }}

    <x-slot:action>
        <a href="{{ url()->previous() }}"
           class="inline-flex items-center rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
            {{ __('Go back') }}
        </a>
    </x-slot:action>
</x-public.error>
