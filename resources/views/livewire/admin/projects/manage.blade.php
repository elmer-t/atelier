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

    {{-- Settings --}}
    <div class="mb-8 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <form wire:submit="saveSettings" class="space-y-6">
            <flux:heading size="sm">Settings</flux:heading>

            <flux:input wire:model="title" label="Title" />

            {{-- Shareable link --}}
            <flux:field>
                <flux:label>Shareable link</flux:label>
                <div class="flex items-center gap-2" x-data="{
                    link: @js(route('project.show', $project)),
                    async copy() {
                        {{-- navigator.clipboard is undefined outside a secure context (e.g. http://*.test). --}}
                        try {
                            if (window.isSecureContext && navigator.clipboard) {
                                await navigator.clipboard.writeText(this.link);
                            } else {
                                this.$refs.link.select();
                                document.execCommand('copy');
                            }
                            $flux.toast('Link copied');
                        } catch (e) {
                            $flux.toast({ variant: 'danger', text: 'Could not copy. Select the link and copy manually.' });
                        }
                    },
                }">
                    <flux:input x-ref="link" readonly value="{{ route('project.show', $project) }}" class="flex-1" />
                    <flux:button icon="clipboard" x-on:click="copy">
                        Copy
                    </flux:button>
                    <flux:button icon="arrow-path" variant="subtle" wire:click="regenerateSlug"
                        wire:confirm="Generate a new link? The current link will stop working immediately.">
                        Regenerate
                    </flux:button>
                </div>
            </flux:field>

            <flux:switch wire:model="isPublic" label="Public" class="atelier-switch-lg"
                description="Anyone with the link can view, and the project is listed on the public index." />

            {{-- Stays in the layout when public so toggling never reflows the form. --}}
            <div x-bind:class="$wire.isPublic && 'opacity-50'" class="transition-opacity">
                <flux:input type="password" wire:model="newPassword" x-bind:disabled="$wire.isPublic"
                    label="{{ $project->password_hash ? 'Rotate password' : 'Set password' }}"
                    description="{{ $project->password_hash ? 'Leave blank to keep the current password. Rotating signs out existing viewers.' : 'Required for a private project.' }}"
                    placeholder="••••••••" />
            </div>

            <flux:switch wire:model="isArchived" label="Archived" class="atelier-switch-lg"
                description="Archived projects stay reachable by link but are hidden from the public index." />

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
