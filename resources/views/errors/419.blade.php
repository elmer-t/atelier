<x-public.error :title="__('Session ended')" :heading="__('Your session ended')" code="419">
    {{ __('The page was open too long. For your safety, the system stopped the session. Go back and send the page again. If the project has a password, give the password again.') }}

    <x-slot:action>
        <a href="{{ url()->previous() }}"
           class="inline-flex items-center rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
            {{ __('Go back') }}
        </a>
    </x-slot:action>
</x-public.error>
