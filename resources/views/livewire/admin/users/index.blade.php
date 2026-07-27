@use('App\Enums\UserRole')

<div class="mx-auto w-full max-w-5xl px-6 py-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Users</flux:heading>
            <flux:subheading>Everyone with an account here — creators, clients who left feedback, and the agent.</flux:subheading>
        </div>

        <flux:modal.trigger name="invite-creator">
            <flux:button variant="primary" icon="plus">Invite creator</flux:button>
        </flux:modal.trigger>
    </div>

    {{-- Search takes whatever the intrinsically-sized select leaves behind. --}}
    <div class="mb-4 flex items-center gap-2">
        <flux:input
            class="flex-1"
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="Search by name or email…"
            clearable
        />

        <flux:select wire:model.live="role" class="w-auto shrink-0">
            <flux:select.option value="">All roles</flux:select.option>
            @foreach (UserRole::cases() as $case)
                <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        @if ($this->isFiltered)
            <flux:button variant="subtle" icon="x-mark" wire:click="clearFilters" class="shrink-0">Clear</flux:button>
        @endif
    </div>

    @if ($this->users->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-12 text-center dark:border-zinc-700">
            <flux:text>No users match these filters.</flux:text>
        </div>
    @else
        <flux:table :paginate="$this->users">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$this->sortedColumn() === 'name'" :direction="$this->sortedDirection()" wire:click="sort('name')">
                    Name
                </flux:table.column>
                <flux:table.column sortable :sorted="$this->sortedColumn() === 'role'" :direction="$this->sortedDirection()" wire:click="sort('role')">
                    Role
                </flux:table.column>
                <flux:table.column sortable :sorted="$this->sortedColumn() === 'comments_count'" :direction="$this->sortedDirection()" wire:click="sort('comments_count')">
                    Comments
                </flux:table.column>
                <flux:table.column sortable :sorted="$this->sortedColumn() === 'comments_max_created_at'" :direction="$this->sortedDirection()" wire:click="sort('comments_max_created_at')">
                    Last active
                </flux:table.column>
                <flux:table.column sortable :sorted="$this->sortedColumn() === 'created_at'" :direction="$this->sortedDirection()" wire:click="sort('created_at')">
                    Joined
                </flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->users as $user)
                    <flux:table.row :key="$user->id">
                        <flux:table.cell variant="strong">
                            <a href="{{ route('admin.users.show', $user) }}" wire:navigate class="hover:underline">
                                {{ $user->name }}
                            </a>
                            <div class="text-xs font-normal text-zinc-400">{{ $user->email }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-1">
                                <flux:badge size="sm" :color="match ($user->role) {
                                    UserRole::Creator => 'red',
                                    UserRole::Client => 'zinc',
                                    UserRole::Agent => 'purple',
                                }">
                                    {{ $user->role->label() }}
                                </flux:badge>

                                @if ($user->isDeactivated())
                                    <flux:tooltip content="Deactivated {{ $user->deactivated_at->toDayDateTimeString() }} — cannot leave new feedback.">
                                        <flux:badge size="sm" color="amber">Deactivated</flux:badge>
                                    </flux:tooltip>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $user->comments_count }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($user->comments_max_created_at)
                                {{ \Illuminate\Support\Carbon::parse($user->comments_max_created_at)->diffForHumans() }}
                            @else
                                <span class="text-zinc-400">Never commented</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $user->created_at?->diffForHumans() }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" inset />
                                <flux:menu>
                                    <flux:menu.item icon="chat-bubble-left-right" :href="route('admin.users.show', $user)" wire:navigate>
                                        View comments
                                    </flux:menu.item>

                                    @if ($user->isAgent())
                                        <flux:menu.item icon="key" :href="route('admin.users.agent')" wire:navigate>
                                            Manage token
                                        </flux:menu.item>
                                    @endif

                                    @can('deactivate', $user)
                                        <flux:menu.item icon="no-symbol" wire:click="deactivate({{ $user->id }})"
                                            wire:confirm="Block this email from leaving new feedback? Their existing comments stay.">
                                            Deactivate
                                        </flux:menu.item>
                                    @endcan

                                    @can('reactivate', $user)
                                        <flux:menu.item icon="arrow-uturn-left" wire:click="reactivate({{ $user->id }})">
                                            Reactivate
                                        </flux:menu.item>
                                    @endcan

                                    @can('delete', $user)
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger"
                                            wire:click="delete({{ $user->id }})"
                                            wire:confirm="Delete {{ $user->name }}? Their {{ $user->comments_count }} {{ Str::plural('comment', $user->comments_count) }} will be deleted too. This cannot be undone.">
                                            Delete
                                        </flux:menu.item>
                                    @endcan
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <flux:text size="sm" class="mt-3 text-zinc-400">
            Creators edit their own name and email under Settings — this panel never edits someone else's profile.
        </flux:text>
    @endif

    <flux:modal name="invite-creator" class="md:w-96">
        <form wire:submit="invite" class="space-y-6">
            <div>
                <flux:heading size="lg">Invite a creator</flux:heading>
                <flux:subheading>They receive an email to set their own password. No public sign-up page is opened.</flux:subheading>
            </div>

            <flux:input wire:model="inviteName" label="Name" placeholder="Sam Rivera" />
            <flux:input wire:model="inviteEmail" type="email" label="Email" placeholder="sam@example.com" />

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">Send invite</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
