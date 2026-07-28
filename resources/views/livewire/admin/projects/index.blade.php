@use('App\Enums\ProjectStatus')
@use('App\Enums\ProjectVisibility')

<div class="mx-auto w-full max-w-5xl px-6 py-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Projects</flux:heading>
        </div>

        <flux:modal.trigger name="create-project">
            <flux:button variant="primary" icon="plus">New project</flux:button>
        </flux:modal.trigger>
    </div>

    {{-- Search takes whatever the intrinsically-sized selects leave behind. --}}
    <div class="mb-4 flex items-center gap-2">
        <flux:input
            class="flex-1"
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="Search projects…"
            clearable
        />

        <flux:select wire:model.live="visibility" class="w-auto shrink-0">
            <flux:select.option value="">All visibility</flux:select.option>
            @foreach (ProjectVisibility::cases() as $case)
                <flux:select.option value="{{ $case->value }}">{{ ucfirst($case->value) }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="status" class="w-auto shrink-0">
            <flux:select.option value="">All statuses</flux:select.option>
            @foreach (ProjectStatus::cases() as $case)
                <flux:select.option value="{{ $case->value }}">{{ ucfirst($case->value) }}</flux:select.option>
            @endforeach
        </flux:select>

        @if ($this->isFiltered)
            <flux:button variant="subtle" icon="x-mark" wire:click="clearFilters" class="shrink-0">Clear</flux:button>
        @endif
    </div>

    @if ($this->projects->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-12 text-center dark:border-zinc-700">
            @if ($this->isFiltered)
                <flux:text>No projects match these filters.</flux:text>
            @else
                <flux:text>No projects yet. Create your first one to get started.</flux:text>
            @endif
        </div>
    @else
        <flux:table :paginate="$this->projects">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$this->sortedColumn() === 'title'" :direction="$this->sortedDirection()" wire:click="sort('title')">
                    Project
                </flux:table.column>
                <flux:table.column sortable :sorted="$this->sortedColumn() === 'artifacts_count'" :direction="$this->sortedDirection()" wire:click="sort('artifacts_count')">
                    Artifacts
                </flux:table.column>
                <flux:table.column sortable :sorted="$this->sortedColumn() === 'visibility'" :direction="$this->sortedDirection()" wire:click="sort('visibility')">
                    Visibility
                </flux:table.column>
                <flux:table.column sortable :sorted="$this->sortedColumn() === 'status'" :direction="$this->sortedDirection()" wire:click="sort('status')">
                    Status
                </flux:table.column>
                <flux:table.column sortable :sorted="$this->sortedColumn() === 'last_viewed_at'" :direction="$this->sortedDirection()" wire:click="sort('last_viewed_at')">
                    Last viewed
                </flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->projects as $project)
                    <flux:table.row :key="$project->id">
                        <flux:table.cell variant="strong">
                            <a href="{{ route('admin.projects.manage', $project) }}" wire:navigate class="hover:underline">
                                {{ $project->title }}
                            </a>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $project->artifacts_count }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$project->isPublic() ? 'red' : 'zinc'">
                                {{ ucfirst($project->visibility->value) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$project->isActive() ? 'blue' : 'amber'">
                                {{ ucfirst($project->status->value) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($project->last_viewed_at)
                                <flux:tooltip content="{{ $project->last_viewed_at->toDayDateTimeString() }} · {{ $project->view_count }} {{ Str::plural('view', $project->view_count) }}">
                                    <span>{{ $project->last_viewed_at->diffForHumans() }}</span>
                                </flux:tooltip>
                            @else
                                <span class="text-zinc-400">Not yet viewed</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex items-center justify-end gap-1">
                                <flux:button size="xs" variant="ghost" icon="link"
                                    x-on:click="navigator.clipboard.writeText('{{ route('project.show', $project) }}'); $flux.toast('Link copied')">
                                    Copy link
                                </flux:button>

                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" inset />
                                    <flux:menu>
                                        <flux:menu.item icon="pencil-square" :href="route('admin.projects.manage', $project)" wire:navigate>
                                            Manage
                                        </flux:menu.item>
                                        <flux:menu.item icon="{{ $project->isActive() ? 'archive-box' : 'arrow-uturn-left' }}"
                                            wire:click="toggleStatus({{ $project->id }})">
                                            {{ $project->isActive() ? 'Archive' : 'Unarchive' }}
                                        </flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger"
                                            wire:click="delete({{ $project->id }})"
                                            wire:confirm="Delete this project and all its artifacts? This cannot be undone.">
                                            Delete
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="create-project" class="md:w-96">
        <form wire:submit="create" class="space-y-6">
            <div>
                <flux:heading size="lg">New project</flux:heading>
                <flux:subheading>A private project is created with an unguessable link. Set a password and content next.</flux:subheading>
            </div>

            <flux:input wire:model="newTitle" label="Title" placeholder="Acme redesign" />

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">Create</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
