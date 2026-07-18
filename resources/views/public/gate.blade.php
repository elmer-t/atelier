<x-public.layout :title="$project->title">
    <div class="flex min-h-screen items-center justify-center px-6 py-16">
        <div class="w-full max-w-sm">
            <div class="mb-8 text-center">
                <p class="text-sm font-medium uppercase tracking-widest text-zinc-400 dark:text-zinc-500">
                    {{ config('app.name', 'Atelier') }}
                </p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ $project->title }}</h1>
                <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                    This project is protected. Enter the password to continue.
                </p>
            </div>

            <form method="POST" action="{{ route('project.unlock', $project) }}"
                  class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
                @csrf

                <label for="password" class="block text-sm font-medium">Password</label>
                <input id="password" name="password" type="password" autofocus autocomplete="off"
                       class="mt-2 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm outline-none focus:border-zinc-500 focus:ring-2 focus:ring-zinc-200 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:ring-zinc-800" />

                @error('password')
                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <button type="submit"
                        class="mt-4 w-full rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                    Unlock
                </button>
            </form>
        </div>
    </div>
</x-public.layout>
