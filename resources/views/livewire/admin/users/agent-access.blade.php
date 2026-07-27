<div class="mx-auto w-full max-w-3xl px-6 py-8">
    <flux:button size="sm" variant="ghost" icon="arrow-left" :href="route('admin.users')" wire:navigate class="mb-4 -ml-2">
        Users
    </flux:button>

    <div class="mb-8">
        <flux:heading size="xl">Agent access</flux:heading>
        <flux:subheading>
            The agent reads and writes artifacts and replies to feedback over MCP. It never resolves threads or
            changes who can see a project — that stays with you.
        </flux:subheading>
    </div>

    @if ($this->plainTextToken)
        <flux:callout icon="key" variant="success" class="mb-6">
            <flux:callout.heading>Copy this token now</flux:callout.heading>
            <flux:callout.text>
                It is shown once and never again. Store it wherever the agent reads its credentials from.
            </flux:callout.text>

            <div class="mt-3 flex items-center gap-2">
                <flux:input readonly value="{{ $this->plainTextToken }}" class="font-mono" />
                <flux:button icon="clipboard" class="shrink-0"
                    x-on:click="navigator.clipboard.writeText(@js($this->plainTextToken)); $flux.toast('Token copied')">
                    Copy
                </flux:button>
            </div>
        </flux:callout>
    @endif

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700">
        <div class="flex items-center justify-between gap-4 px-5 py-4">
            <div>
                <flux:heading size="sm">Token</flux:heading>

                @if ($this->token)
                    <flux:text size="sm" class="mt-1 text-zinc-400">
                        Minted {{ $this->token->created_at?->diffForHumans() }} ·
                        @if ($this->token->last_used_at)
                            last used {{ $this->token->last_used_at->diffForHumans() }}
                        @else
                            never used
                        @endif
                        @if ($this->tokenCount > 1)
                            · {{ $this->tokenCount }} tokens live
                        @endif
                    </flux:text>
                @else
                    <flux:text size="sm" class="mt-1 text-zinc-400">
                        No token. The agent cannot reach the MCP server.
                    </flux:text>
                @endif
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <flux:button size="sm" variant="primary" icon="key" wire:click="mint">
                    {{ $this->token ? 'Mint another' : 'Mint token' }}
                </flux:button>

                @if ($this->tokenCount > 0)
                    <flux:button size="sm" variant="danger" icon="no-symbol" wire:click="revoke"
                        wire:confirm="Revoke every agent token? The agent stops working immediately.">
                        Revoke
                    </flux:button>
                @endif
            </div>
        </div>

        <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-700">
            <flux:heading size="sm">Abilities</flux:heading>
            <div class="mt-2 flex flex-wrap gap-1">
                @foreach ($this->abilities as $ability)
                    <flux:badge size="sm" color="purple">{{ $ability }}</flux:badge>
                @endforeach
            </div>
        </div>

        @if ($this->agent)
            @php($agentComments = $this->agent->comments()->count())

            <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-700">
                <flux:heading size="sm">Agent user</flux:heading>
                <flux:text size="sm" class="mt-1 text-zinc-400">
                    {{ $this->agent->name }} · {{ $this->agent->email }} ·
                    <a href="{{ route('admin.users.show', $this->agent) }}" wire:navigate class="hover:underline">
                        {{ $agentComments }} {{ Str::plural('comment', $agentComments) }}
                    </a>
                </flux:text>
            </div>
        @endif
    </div>

    <flux:text size="sm" class="mt-3 text-zinc-400">
        The same two actions are available on the command line as <code>php artisan atelier:agent-token</code>.
    </flux:text>
</div>
