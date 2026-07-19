@php
    use App\Enums\ArtifactOrigin;

    $anchorType = match ($artifact->origin()) {
        ArtifactOrigin::Sandbox => 'html_point',
        default => $artifact->isMarkdown() ? 'text_range' : 'image_region',
    };
@endphp

<section class="border-t border-zinc-200 bg-white px-6 py-8 dark:border-zinc-800 dark:bg-zinc-900"
    x-data="{
        anchorType: @js($anchorType),
        composing: false,
        captureSelection() {
            const text = (window.getSelection()?.toString() ?? '').trim().slice(0, 280);
            $wire.set('draftAnchor', { type: 'text_range', quote: text });
            this.composing = true;
        },
        capturePoint(event) {
            const rect = event.currentTarget.getBoundingClientRect();
            const x = Math.round(((event.clientX - rect.left) / rect.width) * 1000) / 10;
            const y = Math.round(((event.clientY - rect.top) / rect.height) * 1000) / 10;
            $wire.set('draftAnchor', { type: this.anchorType, x, y });
            this.composing = true;
        },
    }">
    <div class="mx-auto max-w-3xl">
        <div class="mb-5 flex items-center justify-between">
            <flux:heading size="sm">Feedback</flux:heading>
            <flux:text size="sm" class="text-zinc-400">
                {{ trans_choice(':count comment|:count comments', $this->threads->count(), ['count' => $this->threads->count()]) }}
            </flux:text>
        </div>

        {{-- Existing threads --}}
        <ul class="space-y-4">
            @forelse ($this->threads as $thread)
                <li wire:key="thread-{{ $thread->id }}"
                    @class([
                        'rounded-xl border p-4',
                        'border-zinc-200 dark:border-zinc-800' => ! $thread->isResolved(),
                        'border-zinc-100 bg-zinc-50/60 opacity-70 dark:border-zinc-800/60 dark:bg-zinc-800/20' => $thread->isResolved(),
                    ])>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <span class="text-sm font-medium">{{ $thread->author->name }}</span>
                            <span class="ml-2 text-xs text-zinc-400">{{ $thread->created_at?->diffForHumans() }}</span>
                            @if ($thread->isResolved())
                                <flux:badge size="sm" color="green" class="ml-2">Resolved</flux:badge>
                            @endif
                        </div>
                        <div class="flex items-center gap-1">
                            @if ($this->canResolve)
                                @if ($thread->isResolved())
                                    <flux:button size="xs" variant="ghost" wire:click="unresolve({{ $thread->id }})">Reopen</flux:button>
                                @else
                                    <flux:button size="xs" variant="ghost" wire:click="resolve({{ $thread->id }})">Resolve</flux:button>
                                @endif
                            @endif
                        </div>
                    </div>

                    <p class="mt-2 whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">{{ $thread->body }}</p>

                    {{-- Replies --}}
                    @if ($thread->replies->isNotEmpty())
                        <ul class="mt-3 space-y-3 border-l-2 border-zinc-100 pl-4 dark:border-zinc-800">
                            @foreach ($thread->replies as $reply)
                                <li wire:key="reply-{{ $reply->id }}">
                                    <span class="text-sm font-medium">{{ $reply->author->name }}</span>
                                    <span class="ml-2 text-xs text-zinc-400">{{ $reply->created_at?->diffForHumans() }}</span>
                                    <p class="mt-1 whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">{{ $reply->body }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    {{-- Reply composer --}}
                    <div class="mt-3">
                        @if ($replyingToId === $thread->id)
                            <flux:textarea wire:model="replyDraft" rows="2" placeholder="Write a reply…" class="text-sm" />
                            <flux:error name="replyDraft" />
                            <div class="mt-2 flex gap-2">
                                <flux:button size="xs" variant="primary" wire:click="reply({{ $thread->id }})">Reply</flux:button>
                                <flux:button size="xs" variant="ghost" wire:click="$set('replyingToId', null)">Cancel</flux:button>
                            </div>
                        @else
                            <flux:button size="xs" variant="ghost" wire:click="startReply({{ $thread->id }})">Reply</flux:button>
                        @endif
                    </div>
                </li>
            @empty
                <li class="rounded-xl border border-dashed border-zinc-200 p-6 text-center text-sm text-zinc-400 dark:border-zinc-800">
                    No feedback yet. {{ $artifact->isMarkdown() ? 'Select text' : 'Click the content' }} to leave the first comment.
                </li>
            @endforelse
        </ul>

        {{-- Identity capture --}}
        @unless ($identified)
            <div class="mt-6 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                <flux:heading size="sm">Add your details to comment</flux:heading>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <flux:input wire:model="captureName" label="Name" placeholder="Jane Doe" />
                    <flux:input wire:model="captureEmail" type="email" label="Email" placeholder="jane@example.com" />
                </div>
                <flux:error name="captureName" />
                <flux:error name="captureEmail" />
                <div class="mt-3">
                    <flux:button size="sm" variant="primary" wire:click="saveIdentity">Continue</flux:button>
                </div>
                <flux:text size="sm" class="mt-2 text-zinc-400">Your email is never shown to others.</flux:text>
            </div>
        @else
            {{-- Composer --}}
            <div class="mt-6 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                <div class="flex items-center justify-between">
                    <flux:heading size="sm">New comment</flux:heading>
                    <flux:text size="sm" class="text-zinc-400">Commenting as {{ $identityName }}</flux:text>
                </div>

                <div class="mt-2 flex items-center gap-2">
                    @if ($artifact->isMarkdown())
                        <flux:button size="xs" variant="ghost" x-on:click="captureSelection()">Use selected text</flux:button>
                    @else
                        <flux:button size="xs" variant="ghost" x-on:click="composing = true">Add a comment</flux:button>
                    @endif
                    @if (filled($draftAnchor))
                        <flux:badge size="sm" color="blue">Anchor set</flux:badge>
                    @endif
                </div>

                <div class="mt-3" x-show="composing || @js(filled($draftAnchor))" x-cloak>
                    <flux:textarea wire:model="draft" rows="3" placeholder="Share your feedback…" />
                    <flux:error name="draft" />
                    <div class="mt-2">
                        <flux:button size="sm" variant="primary" wire:click="postComment">Post comment</flux:button>
                    </div>
                </div>
            </div>
        @endunless
    </div>
</section>
