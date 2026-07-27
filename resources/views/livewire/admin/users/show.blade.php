<div class="mx-auto w-full max-w-3xl px-6 py-8">
    <flux:button size="sm" variant="ghost" icon="arrow-left" :href="route('admin.users')" wire:navigate class="mb-4 -ml-2">
        Users
    </flux:button>

    <div class="mb-8 flex items-start justify-between gap-4">
        <div class="flex items-center gap-4">
            <flux:avatar :name="$user->name" :initials="$user->initials()" size="lg" />

            <div>
                <flux:heading size="xl">{{ $user->name }}</flux:heading>
                <flux:subheading>{{ $user->email }}</flux:subheading>

                <div class="mt-2 flex items-center gap-1">
                    <flux:badge size="sm" :color="$user->role->color()">
                        {{ $user->role->label() }}
                    </flux:badge>

                    @if ($user->isDeactivated())
                        <flux:badge size="sm" color="amber">
                            Deactivated {{ $user->deactivated_at->diffForHumans() }}
                        </flux:badge>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            @if ($user->isAgent())
                <flux:button size="sm" variant="ghost" icon="key" :href="route('admin.users.agent')" wire:navigate>
                    Manage token
                </flux:button>
            @endif

            @can('deactivate', $user)
                <flux:button size="sm" variant="ghost" icon="no-symbol" wire:click="deactivate"
                    wire:confirm="Block this email from leaving new feedback? Their existing comments stay.">
                    Deactivate
                </flux:button>
            @endcan

            @can('reactivate', $user)
                <flux:button size="sm" variant="ghost" icon="arrow-uturn-left" wire:click="reactivate">
                    Reactivate
                </flux:button>
            @endcan

            @can('delete', $user)
                <flux:button size="sm" variant="danger" icon="trash" wire:click="delete"
                    wire:confirm="Delete {{ $user->name }}? Their {{ $this->comments->total() }} {{ Str::plural('comment', $this->comments->total()) }} will be deleted with them. This cannot be undone.">
                    Delete
                </flux:button>
            @endcan
        </div>
    </div>

    @if ($user->isDeactivated())
        <flux:callout icon="no-symbol" class="mb-6">
            <flux:callout.heading>Deactivated</flux:callout.heading>
            <flux:callout.text>
                This email is blocked from leaving new feedback. Everything below stays visible on the projects it was left on.
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="mb-2 flex items-center gap-2 px-1">
        <flux:icon.chat-bubble-left-right variant="micro" class="text-zinc-400" />
        <flux:heading size="sm">Comments</flux:heading>
        <flux:badge size="sm">{{ $this->comments->total() }}</flux:badge>
    </div>

    @if ($this->comments->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-12 text-center dark:border-zinc-700">
            <flux:text>{{ $user->name }} has not left any feedback.</flux:text>
        </div>
    @else
        <div class="divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-700">
            @foreach ($this->comments as $comment)
                @php($project = $comment->artifact->project)

                <div wire:key="comment-{{ $comment->id }}" class="px-4 py-3">
                    <div class="mb-1 flex items-center gap-2 text-xs text-zinc-400">
                        @if ($project->isHardDisabled())
                            <span>{{ $project->title }} · {{ $comment->artifact->title }}</span>
                            <flux:badge size="sm" color="amber">Project unavailable</flux:badge>
                        @else
                            <a href="{{ route('project.artifact', ['project' => $project, 'artifact' => $comment->artifact]) }}"
                                class="truncate hover:underline">
                                {{ $project->title }} · {{ $comment->artifact->title }}
                            </a>
                        @endif

                        <span>·</span>
                        <span>{{ $comment->created_at?->diffForHumans() }}</span>

                        @if ($comment->isReply())
                            <flux:badge size="sm" color="zinc">Reply</flux:badge>
                        @endif

                        @if ($comment->isResolved())
                            <flux:badge size="sm" color="green">Resolved</flux:badge>
                        @endif
                    </div>

                    <flux:text class="whitespace-pre-line">{{ $comment->body }}</flux:text>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $this->comments->links() }}
        </div>
    @endif
</div>
