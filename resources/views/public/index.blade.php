{{--
    Public project index — a single design with a grid (default) / list view toggle.
    Full-page width; both views share the same header-image treatment.

    The cover comes from `projects.header_artifact_id`: rendered as an image when the
    artifact is an image (Project::headerImageUrl()), else a gradient-monogram fallback.
--}}
@php
    $view = request('view') === 'list' ? 'list' : 'grid';

    // Deterministic gradient per project, used as the fallback cover when a project
    // has no (image) header artifact.
    $palettes = [
        'from-rose-400 to-orange-300', 'from-sky-400 to-indigo-400', 'from-emerald-400 to-teal-300',
        'from-violet-400 to-fuchsia-300', 'from-amber-400 to-yellow-300', 'from-cyan-400 to-blue-400',
    ];
@endphp

<x-public.layout :title="config('app.name')">
    <div class="mx-auto max-w-6xl px-6 py-16 sm:py-20">
        <header class="mb-10 flex flex-wrap items-end justify-between gap-6">
            <div>
                <p class="text-sm font-medium uppercase tracking-widest text-zinc-400 dark:text-zinc-500">
                    {{ config('app.name', 'Atelier') }}
                </p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Currently on view</h1>
                <p class="mt-3 max-w-xl text-zinc-500 dark:text-zinc-400">
                    A studio of concepts and designs, open for viewing.
                </p>
            </div>

            @unless ($projects->isEmpty())
                {{-- Grid / list view toggle --}}
                <div class="flex items-center gap-1 rounded-lg border border-zinc-200 bg-white p-1 dark:border-zinc-800 dark:bg-zinc-900">
                    @php
                        $active = 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900';
                        $idle = 'text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200';
                    @endphp
                    <a href="{{ route('home', ['view' => 'grid']) }}" aria-label="Grid view"
                       class="flex h-8 w-8 items-center justify-center rounded-md transition {{ $view === 'grid' ? $active : $idle }}">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z" />
                        </svg>
                    </a>
                    <a href="{{ route('home', ['view' => 'list']) }}" aria-label="List view"
                       class="flex h-8 w-8 items-center justify-center rounded-md transition {{ $view === 'list' ? $active : $idle }}">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                    </a>
                </div>
            @endunless
        </header>

        @if ($projects->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-200 p-12 text-center dark:border-zinc-800">
                <p class="text-zinc-500 dark:text-zinc-400">No public projects are available right now.</p>
            </div>
        @elseif ($view === 'grid')
            {{-- GRID VIEW --}}
            <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($projects as $project)
                    @php
                        // Cover: the project's header artifact when it is an image, else the
                        // gradient monogram fallback. See Project::headerImageUrl().
                        $monogram = Str::of($project->title)->trim()->substr(0, 1)->upper();
                        $image = $project->headerImageUrl();
                        $hasImage = $image !== null;
                        $palette = $palettes[$loop->index % count($palettes)];
                    @endphp
                    <li>
                        <a href="{{ route('project.show', $project) }}"
                           class="group block overflow-hidden rounded-2xl border border-zinc-200 bg-white transition hover:-translate-y-0.5 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900">
                            <div class="relative h-40 overflow-hidden bg-gradient-to-br {{ $palette }}">
                                @if ($hasImage)
                                    <img src="{{ $image }}" alt="" loading="lazy"
                                         class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                                @else
                                    <div class="flex h-full items-center justify-center">
                                        <span class="font-serif text-6xl font-semibold text-white/90 drop-shadow-sm">{{ $monogram }}</span>
                                    </div>
                                @endif
                                <span class="absolute right-3 top-3 rounded-full bg-black/40 px-2.5 py-1 text-xs font-medium text-white backdrop-blur">
                                    {{ $project->artifacts_count }} {{ Str::plural('doc', $project->artifacts_count) }}
                                </span>
                            </div>
                            <div class="p-5">
                                <h2 class="text-lg font-medium tracking-tight group-hover:underline">{{ $project->title }}</h2>
                                <div class="mt-3 flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                                    <span class="inline-flex items-center gap-1">
                                        <span class="text-zinc-400 dark:text-zinc-500">&#9673;</span>
                                        {{ number_format($project->view_count) }} views
                                    </span>
                                    <span>updated {{ $project->updated_at?->diffForHumans(short: true) }}</span>
                                </div>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            {{-- LIST VIEW --}}
            <ol class="divide-y divide-zinc-200 border-t border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                @foreach ($projects as $project)
                    @php
                        // Cover: the project's header artifact when it is an image, else the
                        // gradient monogram fallback. See Project::headerImageUrl().
                        $monogram = Str::of($project->title)->trim()->substr(0, 1)->upper();
                        $image = $project->headerImageUrl();
                        $hasImage = $image !== null;
                        $palette = $palettes[$loop->index % count($palettes)];
                    @endphp
                    <li>
                        <a href="{{ route('project.show', $project) }}"
                           class="group flex items-center gap-6 py-5">
                            <div class="relative hidden h-16 w-28 shrink-0 overflow-hidden rounded-lg bg-gradient-to-br {{ $palette }} sm:block">
                                @if ($hasImage)
                                    <img src="{{ $image }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                @else
                                    <div class="flex h-full items-center justify-center">
                                        <span class="font-serif text-2xl font-semibold text-white/90">{{ $monogram }}</span>
                                    </div>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <h2 class="text-xl font-medium tracking-tight group-hover:underline">{{ $project->title }}</h2>
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $project->artifacts_count }} {{ Str::plural('document', $project->artifacts_count) }}
                                    <span class="mx-2 text-zinc-300 dark:text-zinc-700">&middot;</span>
                                    {{ number_format($project->view_count) }} {{ Str::plural('view', $project->view_count) }}
                                    <span class="mx-2 text-zinc-300 dark:text-zinc-700">&middot;</span>
                                    updated {{ $project->updated_at?->diffForHumans() }}
                                </p>
                            </div>
                            <span class="hidden shrink-0 text-zinc-300 transition group-hover:translate-x-1 group-hover:text-zinc-500 sm:block dark:text-zinc-600">
                                &rarr;
                            </span>
                        </a>
                    </li>
                @endforeach
            </ol>
        @endif

        {{-- spacer so content never hides behind the sticky footer --}}
        <div class="h-16"></div>
    </div>

    <footer class="fixed inset-x-0 bottom-0 z-40 border-t border-zinc-200/80 bg-zinc-50/80 backdrop-blur dark:border-zinc-800/80 dark:bg-zinc-950/80">
        <div class="mx-auto flex max-w-6xl items-center px-6 py-4 text-sm text-zinc-400 dark:text-zinc-500">
            <a href="{{ route('privacy') }}" class="underline hover:text-zinc-600 dark:hover:text-zinc-300">Privacy Policy</a>
        </div>
    </footer>
</x-public.layout>
