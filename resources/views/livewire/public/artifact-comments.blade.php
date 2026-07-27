@php
    use App\Enums\ArtifactOrigin;

    $anchorType = match ($artifact->origin()) {
        ArtifactOrigin::Sandbox => 'html_point',
        default => $artifact->isMarkdown() ? 'text_range' : 'image_region',
    };
@endphp

<aside
    @class([
        'w-full shrink-0 border-t border-zinc-200 bg-white transition-[width] duration-200 ease-in-out lg:sticky lg:top-0 lg:h-screen lg:overflow-y-auto lg:border-t-0 lg:border-l dark:border-zinc-800 dark:bg-zinc-900',
        'lg:w-14' => $collapsed,
        'lg:w-96' => ! $collapsed,
    ])
    x-bind:class="collapsed ? 'lg:w-14' : 'lg:w-96'"
    x-data="{
        collapsed: @js($collapsed),
        gutter: @js($artifact->isMarkdown()),
        anchorType: @js($anchorType),
        composing: false,

        setCollapsed(value) {
            this.collapsed = value;
            $wire.setCollapsed(value);
        },

        capturePoint(event) {
            const rect = event.currentTarget.getBoundingClientRect();
            const x = Math.round(((event.clientX - rect.left) / rect.width) * 1000) / 10;
            const y = Math.round(((event.clientY - rect.top) / rect.height) * 1000) / 10;
            $wire.set('draftAnchor', { type: this.anchorType, x, y });
            this.composing = true;
        },

        /**
         * Anchor a comment to one paragraph. Its quoted text is captured straight from the
         * rendered stage, so the highlight always re-finds an exact match on redraw.
         */
        commentOnBlock(quote) {
            $wire.set('draftAnchor', { type: 'text_range', quote });
            this.composing = true;
            this.scrollToComposer();
        },

        scrollToComposer() {
            this.$nextTick(() => {
                const box = this.$root.querySelector('[data-composer]');

                if (! box) {
                    return;
                }

                box.scrollIntoView({ behavior: 'smooth', block: 'center' });
                this.flash(box);

                // Drop the cursor straight into the comment field (or the name field if the
                // visitor still needs to identify). preventScroll keeps the smooth scroll above.
                box.querySelector('textarea, input')?.focus({ preventScroll: true });
            });
        },

        /**
         * Give every paragraph in the stage a hover pin in the left gutter. Runs on load and
         * after any thread mutation; skips blocks that already have one so redraws are cheap.
         */
        mountGutter() {
            if (! this.gutter) {
                return;
            }

            const stage = document.querySelector('[data-artifact-stage]');

            if (! stage) {
                return;
            }

            // Reserve gutter room inside the article so a pin in the block's negative margin
            // stays within the stage's horizontal clip and never spills off-screen.
            stage.closest('article')?.classList.add('comment-gutter-host');

            stage.querySelectorAll('p, li, h2, h3, blockquote').forEach((block) => {
                if (block.querySelector(':scope > .comment-gutter-pin') || block.textContent.trim().length < 24) {
                    return;
                }

                const quote = block.textContent.trim().slice(0, 280);
                block.classList.add('comment-gutter-block');

                const pin = document.createElement('button');
                pin.type = 'button';
                pin.className = 'comment-gutter-pin';
                pin.title = 'Comment on this paragraph';
                pin.setAttribute('aria-label', 'Comment on this paragraph');
                pin.textContent = '💬';
                pin.addEventListener('click', (event) => {
                    event.stopPropagation();
                    this.commentOnBlock(quote);
                });
                block.appendChild(pin);
            });
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

            this.mountGutter();
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

    {{-- Collapsed: slim vertical tab to reopen (desktop) --}}
    <button type="button" x-show="collapsed" x-on:click="setCollapsed(false)" title="Show feedback"
        @unless ($collapsed) style="display: none" @endunless
        class="hidden h-full w-full flex-col items-center gap-3 pt-5 text-zinc-500 transition hover:text-zinc-900 lg:flex dark:text-zinc-400 dark:hover:text-white">
        <span class="text-lg leading-none">«</span>
        <span class="text-xs font-semibold uppercase tracking-widest" style="writing-mode: vertical-rl">Feedback</span>
        <span class="rounded-full bg-zinc-200 px-1.5 py-0.5 text-[10px] font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">{{ $this->threads->count() }}</span>
    </button>

    {{-- Collapsed: header bar to reopen (mobile) --}}
    <button type="button" x-show="collapsed" x-on:click="setCollapsed(false)"
        @unless ($collapsed) style="display: none" @endunless
        class="flex w-full items-center justify-between p-5 text-left lg:hidden">
        <span class="flex items-center gap-2">
            <flux:heading size="sm">Feedback</flux:heading>
            <span class="rounded-full bg-zinc-200 px-1.5 py-0.5 text-[10px] font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">{{ $this->threads->count() }}</span>
        </span>
        <span class="text-base text-zinc-400">⌄</span>
    </button>

    {{-- Expanded panel: entry form top-most, newest-first threads beneath --}}
    <div x-show="! collapsed" @if ($collapsed) style="display: none" @endif class="flex flex-col p-6">
        {{--
            Honeypot for every write path in this panel. Positioned off-screen rather
            than hidden with `display: none`, which form-filling automation is known to
            skip; a human never reaches it, so a filled value means a bot.
        --}}
        <div aria-hidden="true" class="pointer-events-none absolute -left-[9999px] h-px w-px overflow-hidden">
            <label for="atelier-website">Website</label>
            <input type="text" id="atelier-website" wire:model="website" tabindex="-1" autocomplete="off" />
        </div>

        <div class="order-1 mb-5 flex items-center justify-between gap-2">
            <flux:heading size="sm">Feedback</flux:heading>
            <div class="flex items-center gap-2">
                <flux:text size="sm" class="whitespace-nowrap text-zinc-400">
                    {{ trans_choice(':count comment|:count comments', $this->threads->count(), ['count' => $this->threads->count()]) }}
                </flux:text>
                <button type="button" x-on:click="setCollapsed(true)" title="Collapse feedback" aria-label="Collapse feedback"
                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200">
                    <span class="hidden text-base leading-none lg:inline">»</span>
                    <span class="text-base leading-none lg:hidden">⌃</span>
                </button>
            </div>
        </div>

        {{-- Identity capture --}}
        @unless ($identified)
            <div class="order-2 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800" data-composer>
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
            <div class="order-2 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800" data-composer>
                <div class="flex items-center justify-between gap-2">
                    <flux:heading size="sm">New comment</flux:heading>
                    <flux:text size="sm" class="truncate text-zinc-400">as {{ $identityName }}</flux:text>
                </div>

                @if ($artifact->isMarkdown())
                    <div x-show="! @js(filled($draftAnchor))" class="mt-3 flex items-center gap-2 rounded-lg border border-dashed border-zinc-200 px-3 py-2.5 text-sm text-zinc-400 dark:border-zinc-700 dark:text-zinc-500">
                        <span>💬</span>
                        <span>Hover a paragraph and click the pin in the margin to comment on it.</span>
                    </div>
                @else
                    <div class="mt-2 flex items-center gap-2">
                        <flux:button size="xs" variant="ghost" x-on:click="composing = true">Add a comment</flux:button>
                        @if (filled($draftAnchor))
                            <flux:badge size="sm" color="blue">Anchor set</flux:badge>
                        @endif
                    </div>
                @endif

                <div class="mt-3" x-show="composing || @js(filled($draftAnchor))" x-cloak>
                    @if ($artifact->isMarkdown())
                        <flux:badge size="sm" color="blue" class="mb-2">Commenting on the highlighted text</flux:badge>
                    @endif
                    <flux:textarea wire:model="draft" rows="3" placeholder="Share your feedback…" />
                    <flux:error name="draft" />
                    <div class="mt-2 flex gap-2">
                        <flux:button size="sm" variant="primary" wire:click="postComment">Post comment</flux:button>
                        @if ($artifact->isMarkdown())
                            <flux:button size="sm" variant="ghost" x-show="@js(filled($draftAnchor))" x-cloak wire:click="$set('draftAnchor', [])" x-on:click="composing = false">Cancel</flux:button>
                        @endif
                    </div>
                </div>
            </div>
        @endunless

        {{-- Existing threads (newest first) --}}
        <ul class="order-3 mt-5 space-y-3 border-t border-zinc-200 pt-5 dark:border-zinc-800">
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
                    No feedback yet. {{ $artifact->isMarkdown() ? 'Hover a paragraph and click the pin' : 'Click the content' }} to leave the first comment.
                </li>
            @endforelse
        </ul>
    </div>
</aside>
