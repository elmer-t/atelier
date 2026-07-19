@php
    use App\Enums\ArtifactOrigin;

    $anchorType = match ($artifact->origin()) {
        ArtifactOrigin::Sandbox => 'html_point',
        default => $artifact->isMarkdown() ? 'text_range' : 'image_region',
    };
@endphp

<aside class="w-full shrink-0 border-t border-zinc-200 bg-white lg:sticky lg:top-0 lg:h-screen lg:w-96 lg:overflow-y-auto lg:border-t-0 lg:border-l dark:border-zinc-800 dark:bg-zinc-900"
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

        /**
         * Re-draw every anchor highlight in the stage. Highlights live in the stage DOM,
         * which is outside this component, so this runs on load and after any thread
         * mutation rather than being driven by the morph.
         */
        sync() {
            const stage = document.querySelector('[data-artifact-stage]');

            if (! stage) {
                return;
            }

            stage.querySelectorAll('mark[data-comment-id]').forEach((mark) => {
                mark.replaceWith(document.createTextNode(mark.textContent));
            });
            stage.querySelectorAll('[data-comment-block]').forEach((el) => {
                el.classList.remove('atelier-anchor-block');
                delete el.dataset.commentBlock;
            });
            stage.normalize();

            this.$root.querySelectorAll('[data-anchor-quote]').forEach((card) => {
                card.dataset.anchorFound = this.drawAnchor(stage, card) ? 'yes' : 'no';
            });
        },

        /**
         * Anchors store the quoted text, not offsets (ADR-0004), so the spot is recovered
         * by searching for it: an exact hit inside one text node becomes a <mark>; a quote
         * spanning elements falls back to highlighting the innermost block containing it.
         */
        drawAnchor(stage, card) {
            const quote = card.dataset.anchorQuote;

            if (! quote) {
                return false;
            }

            const id = card.dataset.commentId;
            const walker = document.createTreeWalker(stage, NodeFilter.SHOW_TEXT);
            let node;

            while ((node = walker.nextNode())) {
                const at = node.nodeValue.indexOf(quote);

                if (at === -1) {
                    continue;
                }

                const range = document.createRange();
                range.setStart(node, at);
                range.setEnd(node, at + quote.length);

                const mark = document.createElement('mark');
                mark.className = 'atelier-anchor';
                mark.dataset.commentId = id;
                mark.dataset.anchorResolved = card.dataset.anchorResolved;
                mark.onclick = () => this.focusCard(id);
                range.surroundContents(mark);

                return true;
            }

            return this.drawBlockAnchor(stage, card, quote, id);
        },

        /**
         * Fallback for a quote the text-node pass could not place, because a selection
         * spanning elements loses its intervening markup. Prefers the innermost single
         * block holding the whole quote, else spans first-line block to last-line block —
         * a selection dragged across paragraphs is the common case, and the 280-char cap
         * on capture means the tail is often a partial line.
         */
        drawBlockAnchor(stage, card, quote, id) {
            const norm = (text) => text.replace(/\s+/g, ' ').trim();
            const blocks = [...stage.querySelectorAll('p, li, h1, h2, h3, h4, blockquote, td, pre')];
            const holds = (el, text) => norm(el.textContent).includes(text);

            const whole = blocks.filter((el) => holds(el, norm(quote)));
            let hits = whole.length ? [whole[whole.length - 1]] : [];

            if (! hits.length) {
                const lines = quote.split('\n').map(norm).filter((line) => line.length > 2);

                if (lines.length < 2) {
                    return false;
                }

                const first = blocks.findIndex((el) => holds(el, lines[0]));
                const last = blocks.findLastIndex((el) => holds(el, lines[lines.length - 1]));

                if (first === -1 || last < first) {
                    return false;
                }

                hits = blocks.slice(first, last + 1);
            }

            // Drop outer blocks that merely wrap a narrower hit, so nesting highlights once.
            hits = hits.filter((el) => ! hits.some((other) => other !== el && el.contains(other)));

            hits.forEach((el) => {
                el.classList.add('atelier-anchor-block');
                el.dataset.commentBlock = id;
                el.dataset.anchorResolved = card.dataset.anchorResolved;
                el.onclick = () => this.focusCard(id);
            });

            return hits.length > 0;
        },

        focusCard(id) {
            const card = this.$root.querySelector(`[data-comment-id='${id}']`);

            if (card) {
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                this.flash(card);
            }
        },

        focusAnchor(id) {
            const target = document.querySelector(
                `[data-artifact-stage] [data-comment-id='${id}'], [data-artifact-stage] [data-comment-block='${id}']`
            );

            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                this.flash(target);
            }
        },

        flash(el) {
            el.classList.add('atelier-anchor-flash');
            setTimeout(() => el.classList.remove('atelier-anchor-flash'), 1200);
        },
    }"
    x-init="$nextTick(() => sync())"
    x-on:threads-updated.window="$nextTick(() => sync())">
    <div class="p-6">
        <div class="mb-5 flex items-center justify-between">
            <flux:heading size="sm">Feedback</flux:heading>
            <flux:text size="sm" class="text-zinc-400">
                {{ trans_choice(':count comment|:count comments', $this->threads->count(), ['count' => $this->threads->count()]) }}
            </flux:text>
        </div>

        {{-- Existing threads --}}
        <ul class="space-y-3">
            @forelse ($this->threads as $thread)
                <li wire:key="thread-{{ $thread->id }}"
                    data-comment-id="{{ $thread->id }}"
                    data-anchor-resolved="{{ $thread->isResolved() ? 'yes' : 'no' }}"
                    @if (($thread->anchor['type'] ?? null) === 'text_range')
                        data-anchor-quote="{{ $thread->anchor['quote'] ?? '' }}"
                    @endif
                    x-on:click="focusAnchor({{ $thread->id }})"
                    @class([
                        'atelier-anchor-card cursor-pointer rounded-xl border p-4 transition',
                        'border-zinc-200 hover:border-zinc-300 dark:border-zinc-800 dark:hover:border-zinc-700' => ! $thread->isResolved(),
                        'border-zinc-100 bg-zinc-50/60 opacity-70 dark:border-zinc-800/60 dark:bg-zinc-800/20' => $thread->isResolved(),
                    ])>
                    {{-- The anchored spot, so a Thread reads as feedback on something --}}
                    @if (filled($thread->anchor['quote'] ?? null))
                        <p class="atelier-anchor-quote mb-2 border-l-2 border-amber-300 pl-2 text-xs italic text-zinc-500 dark:border-amber-500/50 dark:text-zinc-400">
                            “{{ Str::limit($thread->anchor['quote'], 120) }}”
                        </p>
                        <p class="atelier-anchor-missing mb-2 text-xs text-amber-600 dark:text-amber-500">
                            This text is no longer in the current version.
                        </p>
                    @elseif (filled($thread->anchor['x'] ?? null))
                        <p class="mb-2 text-xs text-zinc-400">
                            Pinned at {{ $thread->anchor['x'] }}%, {{ $thread->anchor['y'] }}%
                        </p>
                    @endif

                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <span class="text-sm font-medium">{{ $thread->author->name }}</span>
                            <span class="ml-2 text-xs text-zinc-400">{{ $thread->created_at?->diffForHumans() }}</span>
                            @if ($thread->isResolved())
                                <flux:badge size="sm" color="green" class="ml-2">Resolved</flux:badge>
                            @endif
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            @if ($this->canResolve)
                                @if ($thread->isResolved())
                                    <flux:button size="xs" variant="ghost" wire:click.stop="unresolve({{ $thread->id }})">Reopen</flux:button>
                                @else
                                    <flux:button size="xs" variant="ghost" wire:click.stop="resolve({{ $thread->id }})">Resolve</flux:button>
                                @endif
                            @endif
                        </div>
                    </div>

                    <p class="mt-2 whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">{{ $thread->body }}</p>

                    {{-- Replies --}}
                    @if ($thread->replies->isNotEmpty())
                        <ul class="mt-3 space-y-3 border-l-2 border-zinc-100 pl-3 dark:border-zinc-800">
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
                    <div class="mt-3" x-on:click.stop>
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
                <div class="mt-3 grid gap-3">
                    <flux:input wire:model="captureName" label="Name" placeholder="Jane Doe" />
                    <flux:input wire:model="captureEmail" type="email" label="Email" placeholder="jane@example.com" />
                </div>
                <flux:error name="captureName" />
                <flux:error name="captureEmail" />
                <div class="mt-3">
                    <flux:button size="sm" variant="primary" wire:click="saveIdentity">Continue</flux:button>
                </div>
                <flux:text size="sm" class="mt-2 text-zinc-400">
                    We use your name and email only to attribute your feedback; your email is never shown to others.
                    We’ll remember you on this device with a cookie so you don’t have to re-enter your details.
                    See our <a href="{{ route('privacy') }}" target="_blank" class="underline hover:text-zinc-600 dark:hover:text-zinc-300">Privacy Policy</a>.
                </flux:text>
            </div>
        @else
            {{-- Composer --}}
            <div class="mt-6 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                <div class="flex items-center justify-between gap-2">
                    <flux:heading size="sm">New comment</flux:heading>
                    <flux:text size="sm" class="truncate text-zinc-400">as {{ $identityName }}</flux:text>
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
</aside>
