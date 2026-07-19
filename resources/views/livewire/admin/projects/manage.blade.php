<div class="mx-auto w-full max-w-4xl px-6 py-8">
    <div class="mb-6">
        <flux:button size="sm" variant="ghost" icon="arrow-left" :href="route('admin.projects')" wire:navigate>
            All projects
        </flux:button>
    </div>

    <div class="mb-8 flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $project->title }}</flux:heading>
            <flux:subheading>Manage settings and artifacts for this project.</flux:subheading>
        </div>
        <flux:badge :color="$project->isActive() ? 'blue' : 'amber'">{{ ucfirst($project->status->value) }}</flux:badge>
    </div>

    {{-- Shareable link --}}
    <div class="mb-8 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="sm">Shareable link</flux:heading>
        <div class="mt-3 flex items-center gap-2">
            <flux:input readonly value="{{ route('project.show', $project) }}" class="flex-1" />
            <flux:button icon="clipboard"
                x-on:click="navigator.clipboard.writeText('{{ route('project.show', $project) }}'); $flux.toast('Link copied')">
                Copy
            </flux:button>
            <flux:button icon="arrow-path" variant="subtle" wire:click="regenerateSlug"
                wire:confirm="Generate a new link? The current link will stop working immediately.">
                Regenerate
            </flux:button>
        </div>
    </div>

    {{-- Settings --}}
    <div class="mb-8 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <form wire:submit="saveSettings" class="space-y-6">
            <flux:heading size="sm">Settings</flux:heading>

            <flux:input wire:model="title" label="Title" />

            <flux:radio.group wire:model.live="visibility" label="Visibility" variant="segmented">
                <flux:radio value="private" label="Private" />
                <flux:radio value="public" label="Public" />
            </flux:radio.group>

            @if ($visibility === 'private')
                <flux:input type="password" wire:model="newPassword"
                    label="{{ $project->password_hash ? 'Rotate password' : 'Set password' }}"
                    description="{{ $project->password_hash ? 'Leave blank to keep the current password. Rotating signs out existing viewers.' : 'Required for a private project.' }}"
                    placeholder="••••••••" />
            @else
                <flux:callout icon="information-circle" variant="secondary">
                    <flux:callout.text>
                        Public projects are open to anyone with the link and listed on the public index. No password.
                    </flux:callout.text>
                </flux:callout>
            @endif

            <flux:radio.group wire:model="status" label="Status" variant="segmented">
                <flux:radio value="active" label="Active" />
                <flux:radio value="archived" label="Archived" />
            </flux:radio.group>

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">Save settings</flux:button>
            </div>
        </form>
    </div>

    {{-- Artifacts --}}
    <div class="mb-8">
        <livewire:admin.projects.artifacts-manager :project="$project" :key="'artifacts-'.$project->id" />
    </div>
</div>
