<x-public.layout :title="$project->title">
    @php
        $stageArtifacts = $project->artifacts->filter->showsInStage();
        $downloads = $project->artifacts->filter->isDownload();
    @endphp

    <div class="flex min-h-screen flex-col lg:flex-row">
        {{-- Sidebar --}}
        <aside class="w-full shrink-0 border-b border-zinc-200 bg-white lg:sticky lg:top-0 lg:h-screen lg:w-72 lg:overflow-y-auto lg:border-b-0 lg:border-r dark:border-zinc-800 dark:bg-zinc-900">
            <div class="p-6">
                <p class="text-xs font-medium uppercase tracking-widest text-zinc-400 dark:text-zinc-500">
                    {{ config('app.name', 'Atelier') }}
                </p>
                <h1 class="mt-1 text-lg font-semibold tracking-tight">{{ $project->title }}</h1>
            </div>

            @if ($stageArtifacts->isNotEmpty())
                <nav class="px-3 pb-4">
                    <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Pages</p>
                    <ul class="space-y-0.5">
                        @foreach ($stageArtifacts as $artifact)
                            @php($active = $current && $artifact->is($current))
                            <li>
                                <a href="{{ route('project.artifact', [$project, $artifact]) }}"
                                   @class([
                                       'flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition',
                                       'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' => $active,
                                       'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800' => ! $active,
                                   ])>
                                    <span @class([
                                        'text-xs',
                                        'opacity-60' => $active,
                                        'text-zinc-400 dark:text-zinc-500' => ! $active,
                                    ])>{{ $artifact->isHtml() ? '◆' : ($artifact->isFile() ? '▣' : '▤') }}</span>
                                    <span class="truncate">{{ $artifact->title }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endif

            @if ($downloads->isNotEmpty())
                <div class="border-t border-zinc-200 px-3 py-4 dark:border-zinc-800">
                    <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Downloads</p>
                    <ul class="space-y-0.5">
                        @foreach ($downloads as $artifact)
                            <li>
                                <a href="{{ route('project.artifact.download', [$project, $artifact]) }}"
                                   class="flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm text-zinc-600 transition hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                    <span class="truncate">{{ $artifact->original_filename ?? $artifact->title }}</span>
                                    <span class="shrink-0 text-xs text-zinc-400 dark:text-zinc-500">↓</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>

        {{-- Main stage --}}
        <main class="flex-1 lg:h-screen lg:overflow-y-auto">
            @if (! $current)
                <div class="flex h-full items-center justify-center p-12 text-center">
                    <p class="text-zinc-400 dark:text-zinc-500">This project has no pages yet.</p>
                </div>
            @else
                @include($current->type->stagePartial())

                <livewire:public.artifact-comments :artifact="$current" :wire:key="'comments-'.$current->id" />
            @endif
        </main>
    </div>
</x-public.layout>
