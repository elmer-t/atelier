<x-public.layout :title="config('app.name')">
    <div class="mx-auto max-w-4xl px-6 py-16 sm:py-24">
        <header class="mb-12">
            <p class="text-sm font-medium uppercase tracking-widest text-zinc-400 dark:text-zinc-500">
                {{ config('app.name', 'Atelier') }}
            </p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Work in progress</h1>
            <p class="mt-3 max-w-2xl text-zinc-500 dark:text-zinc-400">
                A studio of concepts and designs currently open for viewing.
            </p>
        </header>

        @if ($projects->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-200 p-12 text-center dark:border-zinc-800">
                <p class="text-zinc-500 dark:text-zinc-400">No public projects are available right now.</p>
            </div>
        @else
            <ul class="grid gap-4 sm:grid-cols-2">
                @foreach ($projects as $project)
                    <li>
                        <a href="{{ route('project.show', $project) }}"
                           class="group block h-full rounded-xl border border-zinc-200 bg-white p-6 transition hover:border-zinc-300 hover:shadow-sm dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700">
                            <h2 class="text-lg font-medium tracking-tight group-hover:underline">
                                {{ $project->title }}
                            </h2>
                            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $project->artifacts_count }} {{ Str::plural('artifact', $project->artifacts_count) }}
                            </p>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        <footer class="mt-16 border-t border-zinc-200 pt-6 text-sm text-zinc-400 dark:border-zinc-800 dark:text-zinc-500">
            <a href="{{ route('privacy') }}" class="underline hover:text-zinc-600 dark:hover:text-zinc-300">Privacy Policy</a>
        </footer>
    </div>
</x-public.layout>
