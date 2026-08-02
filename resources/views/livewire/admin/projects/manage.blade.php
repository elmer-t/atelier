{{--
    Manage a project. Settings are read far less often than artifacts are
    edited, so they live behind a modal and the page itself belongs to the
    artifact list. The sticky bar carries the identity — cover thumbnail,
    title, state and shareable link — without spending vertical room on it.
--}}
@php
    $cover = $this->coverArtifact;
    $coverUrl = $cover ? route('admin.projects.artifact-preview', [$project, $cover]) : null;
    $link = route('project.show', $project);
@endphp

<div>
    <div class="sticky top-0 z-20 border-b border-zinc-200 bg-white/90 backdrop-blur dark:border-zinc-700 dark:bg-zinc-800/90">
        <div class="mx-auto flex w-full max-w-5xl items-center gap-3 px-6 py-3">
            <flux:tooltip content="All projects">
                <flux:button size="sm" variant="ghost" icon="arrow-left" :href="route('admin.projects')" wire:navigate inset />
            </flux:tooltip>

            {{-- Cover thumbnail, falling back to the same gradient monogram the public index uses. --}}
            <div class="h-9 w-14 shrink-0 overflow-hidden rounded-lg bg-gradient-to-br from-sky-400 to-indigo-400">
                @if ($coverUrl)
                    <img src="{{ $coverUrl }}" alt="" class="h-full w-full object-cover">
                @else
                    <div class="flex h-full w-full items-center justify-center text-sm font-semibold text-white/90">
                        {{ Str::upper(Str::substr($project->title, 0, 1)) }}
                    </div>
                @endif
            </div>

            <div class="min-w-0 flex-1" x-data="{
                link: @js($link),
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
                <div class="flex items-center gap-2">
                    <flux:heading class="truncate">{{ $project->title }}</flux:heading>

                    <flux:badge size="sm" :color="$isPublic ? 'red' : 'zinc'">
                        {{ $isPublic ? 'Public' : 'Private' }}
                    </flux:badge>

                    @if ($isArchived)
                        <flux:badge size="sm" color="amber">Archived</flux:badge>
                    @endif

                    @if ($project->expires_at)
                        <flux:tooltip content="Link expires {{ $project->expires_at->toDayDateTimeString() }}">
                            <flux:badge size="sm" color="zinc" icon="clock">
                                {{ $project->expires_at->isPast() ? 'Expired' : 'Expires' }}
                            </flux:badge>
                        </flux:tooltip>
                    @endif

                    {{-- Who owns this project, i.e. who feedback on it reaches. --}}
                    @if ($project->owner)
                        <flux:tooltip content="Owner — feedback on this project notifies {{ $project->owner->name }}">
                            <span class="flex shrink-0 items-center gap-1 text-xs text-zinc-400">
                                <flux:icon.user variant="micro" />
                                <span class="max-w-32 truncate">{{ $project->owner->name }}</span>
                            </span>
                        </flux:tooltip>
                    @else
                        <flux:tooltip content="No owner — feedback on this project notifies every Creator">
                            <flux:badge size="sm" color="amber">Unowned</flux:badge>
                        </flux:tooltip>
                    @endif
                </div>

                {{-- Off-screen twin for the execCommand fallback above. --}}
                <input x-ref="link" readonly value="{{ $link }}" class="sr-only" tabindex="-1" aria-hidden="true">

                <div class="flex items-center gap-1">
                    <button type="button" x-on:click="copy"
                        class="hidden max-w-md truncate font-mono text-xs text-zinc-400 hover:text-zinc-700 sm:block dark:hover:text-zinc-100">
                        {{ Str::after($link, '://') }}
                    </button>
                    <flux:tooltip content="Copy link">
                        <flux:button size="xs" variant="ghost" icon="clipboard" x-on:click="copy" inset />
                    </flux:tooltip>
                    <flux:tooltip content="Open link in a new tab">
                        <flux:button size="xs" variant="ghost" icon="arrow-top-right-on-square" inset
                            :href="$link" target="_blank" rel="noopener" />
                    </flux:tooltip>
                    {{-- Reissue is destructive, so it sits past a divider rather than a mis-click away from copy. --}}
                    <div class="mx-2 h-3.5 w-px bg-zinc-200 dark:bg-zinc-700"></div>
                    <flux:tooltip content="Reissue — revokes this link and its sandboxed HTML immediately, without archiving the project">
                        <flux:button size="xs" variant="ghost" icon="arrow-path" inset wire:click="reissueLink"
                            wire:confirm="Revoke and reissue this link? The current link (including any sandboxed HTML) stops working immediately." />
                    </flux:tooltip>
                </div>
            </div>

            <flux:modal.trigger name="project-settings">
                <flux:button size="sm" variant="filled" icon="cog-6-tooth">Settings</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    <div class="mx-auto w-full max-w-5xl px-6 py-6">
        <livewire:admin.projects.artifacts-manager :project="$project" :key="'artifacts-'.$project->id" />
    </div>

    <flux:modal name="project-settings" class="md:w-2xl">
        <form wire:submit="saveSettings" class="space-y-6">
            <flux:heading size="lg">Settings</flux:heading>

            <div class="grid items-start gap-4 sm:grid-cols-2">
                <flux:input wire:model="title" label="Title" />

                <flux:select wire:model.live="headerArtifactId" label="Cover image">
                    <flux:select.option value="">Generated cover</flux:select.option>
                    @foreach ($this->imageArtifacts as $artifact)
                        <flux:select.option value="{{ $artifact->id }}">{{ $artifact->title }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:radio.group wire:model.live="visibility" label="Visibility" variant="segmented"
                    description="Public projects are listed on the public index.">
                    <flux:radio value="private" label="Private" />
                    <flux:radio value="public" label="Public" />
                </flux:radio.group>

                <flux:radio.group wire:model.live="status" label="Status" variant="segmented"
                    description="Archived projects stay reachable by link, hidden from the index.">
                    <flux:radio value="active" label="Active" />
                    <flux:radio value="archived" label="Archived" />
                </flux:radio.group>

                {{-- Stays in the layout when public so toggling never reflows the form. --}}
                <div x-bind:class="$wire.isPublic && 'opacity-50'" class="transition-opacity">
                    <flux:input type="password" wire:model="newPassword" x-bind:disabled="$wire.isPublic"
                        label="{{ $project->password_hash ? 'Rotate password' : 'Password' }}"
                        placeholder="{{ $project->password_hash ? '••••••••' : 'Required when private' }}"
                        description="{{ $project->password_hash ? 'Rotating signs out existing viewers.' : '' }}" />

                    {{-- One click gives a strong, memorable passphrase a Creator can read
                         aloud to a Client — no reused or short-but-annoying passwords (#43). --}}
                    <flux:button type="button" variant="ghost" size="sm" icon="sparkles"
                        wire:click="generatePassphrase" x-bind:disabled="$wire.isPublic"
                        data-test="generate-passphrase" class="mt-2">
                        Generate a passphrase
                    </flux:button>
                </div>

                <flux:field>
                    <flux:label>
                        Link expiry
                        <flux:tooltip content="After this moment the link and password gate return 404, just like archiving.">
                            <flux:icon.question-mark-circle variant="micro" class="ml-0.5 inline text-zinc-400" />
                        </flux:tooltip>
                    </flux:label>
                    <flux:input type="datetime-local" wire:model="expiresAt" />
                    <flux:description>Leave blank for no expiry.</flux:description>
                    <flux:error name="expiresAt" />
                </flux:field>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" type="button">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Save settings</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
