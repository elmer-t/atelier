<div class="mx-auto w-full max-w-5xl px-6 py-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Projects</flux:heading>
            <flux:subheading>Concepts and designs you present to clients.</flux:subheading>
        </div>

        <flux:modal.trigger name="create-project">
            <flux:button variant="primary" icon="plus">New project</flux:button>
        </flux:modal.trigger>
    </div>

    @if ($this->projects->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-12 text-center dark:border-zinc-700">
            <flux:text>No projects yet. Create your first one to get started.</flux:text>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wider text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">Project</th>
                        <th class="px-4 py-3 font-medium">Visibility</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($this->projects as $project)
                        <tr wire:key="project-{{ $project->id }}">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.projects.manage', $project) }}" wire:navigate class="font-medium hover:underline">
                                    {{ $project->title }}
                                </a>
                                <div class="text-xs text-zinc-400">
                                    {{ $project->assets_count }} {{ Str::plural('asset', $project->assets_count) }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" :color="$project->isPublic() ? 'green' : 'zinc'">
                                    {{ ucfirst($project->visibility->value) }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" :color="$project->isActive() ? 'blue' : 'amber'">
                                    {{ ucfirst($project->status->value) }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3">
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
                                                wire:confirm="Delete this project and all its assets? This cannot be undone.">
                                                Delete
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
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
