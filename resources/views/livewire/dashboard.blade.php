@php
    $unresolvedCount = array_sum(array_column($this->feedback, 'count'));
    $allClear = $this->feedback === [] && $this->needsContent->isEmpty() && $this->readyToShare->isEmpty();
@endphp

<div class="mx-auto w-full max-w-3xl px-6 py-8">
    <div class="mb-8 flex items-end justify-between">
        <div>
            <flux:heading size="xl">Good to see you</flux:heading>
            <flux:subheading>
                {{ $allClear ? "You're all caught up. Nice." : "Here's everything waiting on you." }}
            </flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" :href="route('admin.projects')" wire:navigate>
            New project
        </flux:button>
    </div>

    <div class="space-y-8">
        {{-- Feedback to review --}}
        @if ($this->feedback !== [])
            <section>
                <div class="mb-2 flex items-center gap-2 px-1">
                    <flux:icon.chat-bubble-left-right variant="micro" class="text-amber-500" />
                    <flux:heading size="sm">Feedback to review</flux:heading>
                    <flux:badge size="sm" color="amber">{{ $unresolvedCount }}</flux:badge>
                </div>

                <div class="divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-700">
                    @foreach ($this->feedback as $row)
                        <div wire:key="feedback-{{ $row['project']->id }}" class="flex items-center gap-4 px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                                <span class="text-sm font-semibold">{{ $row['count'] }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-medium">{{ $row['project']->title }}</div>
                                <div class="text-xs text-zinc-400">
                                    {{ $row['count'] }} unresolved {{ Str::plural('thread', $row['count']) }}
                                    · last {{ $row['latest']?->diffForHumans() }}
                                </div>
                            </div>
                            <flux:button size="sm" variant="primary" :href="route('admin.projects.manage', $row['project'])" wire:navigate>
                                Review
                            </flux:button>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Finish setup --}}
        @if ($this->needsContent->isNotEmpty())
            <section>
                <div class="mb-2 flex items-center gap-2 px-1">
                    <flux:icon.document-plus variant="micro" class="text-sky-500" />
                    <flux:heading size="sm">Finish setup</flux:heading>
                    <flux:badge size="sm" color="sky">{{ $this->needsContent->count() }}</flux:badge>
                </div>

                <div class="divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-700">
                    @foreach ($this->needsContent as $project)
                        <div wire:key="empty-{{ $project->id }}" class="flex items-center gap-4 px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-sky-50 text-sky-500 dark:bg-sky-500/10">
                                <flux:icon.folder variant="micro" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-medium">{{ $project->title }}</div>
                                <div class="text-xs text-zinc-400">No artifacts yet — add content before sharing</div>
                            </div>
                            <flux:button size="sm" variant="filled" icon="plus" :href="route('admin.projects.manage', $project)" wire:navigate>
                                Add content
                            </flux:button>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Ready to share --}}
        @if ($this->readyToShare->isNotEmpty())
            <section>
                <div class="mb-2 flex items-center gap-2 px-1">
                    <flux:icon.paper-airplane variant="micro" class="text-emerald-500" />
                    <flux:heading size="sm">Ready to share</flux:heading>
                    <flux:badge size="sm" color="emerald">{{ $this->readyToShare->count() }}</flux:badge>
                </div>

                <div class="divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-700">
                    @foreach ($this->readyToShare as $project)
                        <div wire:key="share-{{ $project->id }}" class="flex items-center gap-4 px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-500 dark:bg-emerald-500/10">
                                <flux:icon.paper-airplane variant="micro" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-medium">{{ $project->title }}</div>
                                <div class="text-xs text-zinc-400">Not opened yet — send the link along</div>
                            </div>
                            <flux:button
                                size="sm"
                                variant="filled"
                                icon="link"
                                x-on:click="navigator.clipboard.writeText('{{ route('project.show', $project) }}'); $flux.toast('Link copied')"
                            >
                                Copy link
                            </flux:button>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- All caught up --}}
        @if ($allClear)
            <div class="rounded-xl border border-dashed border-zinc-300 p-12 text-center dark:border-zinc-700">
                <flux:icon.check-circle class="mx-auto mb-3 text-emerald-500" />
                <flux:text>Nothing needs you right now. Start something new?</flux:text>
            </div>
        @endif
    </div>
</div>
