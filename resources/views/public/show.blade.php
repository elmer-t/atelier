<x-public.layout :title="$project->title">
    <x-public.stage-chrome :project="$project" :current="$current">
        <div class="stage-columns flex min-h-screen flex-col pt-[var(--stage-bar-height)] lg:flex-row">
            {{-- Pages panel: the ordered stage artifacts, the downloads, and what each
                 page's feedback still wants from this viewer. --}}
            <livewire:public.project-pages :project="$project" :current="$current" />

            {{-- Main stage --}}
            <main class="min-w-0 flex-1 lg:h-screen lg:overflow-y-auto">
                @if (! $current)
                    <div class="flex h-full items-center justify-center p-12 text-center">
                        <p class="text-zinc-400 dark:text-zinc-500">This project has no pages yet.</p>
                    </div>
                @else
                    @include($current->type->stagePartial())
                @endif
            </main>

            {{-- Feedback rail: docked right on desktop, stacked beneath the stage on mobile. --}}
            @if ($current)
                <livewire:public.artifact-comments :artifact="$current" :wire:key="'comments-'.$current->id" />
            @endif
        </div>
    </x-public.stage-chrome>
</x-public.layout>
