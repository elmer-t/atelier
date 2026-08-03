@php
    use App\Enums\ArtifactOrigin;

    $anchorType = match ($artifact->origin()) {
        ArtifactOrigin::Sandbox => 'html_point',
        default => $artifact->isMarkdown() ? 'text_range' : 'image_region',
    };

    $draftQuote = $draftAnchor['quote'] ?? null;
    $draftPointY = $draftAnchor['y'] ?? null;

    $unreadIds = $this->unreadThreadIds;
@endphp

{{--
    The feedback rail: every Thread is placed level with the text it is about.

    A Thread sitting at the same height as its highlight needs no chrome to say what it
    belongs to, which is what lets the cards go — separation is whitespace, alignment and a
    2px accent rule, nothing else. On desktop the quoted line is hidden for the same reason:
    the alignment already says it. Under lg there is no side-by-side geometry to read, so the
    rail falls back to a plain stacked list and the quote comes back.

    What alignment cannot say is anything about the feedback you are not currently level
    with. The minimap fills that gap: a strip down the rail's leading edge with one tick per
    anchor at its depth in the document, so the distribution of feedback down the whole
    article is legible at a glance.

    Anchors store quoted text rather than offsets (ADR-0004), so highlights are recovered by
    searching the stage for the quote on every redraw. The stage lives outside this component,
    so that pass is driven from here on load and on `threads-updated` rather than by the morph.

    Threads are expanded from the moment the rail draws — body, replies and all — because
    feedback you have to click open is feedback nobody reads. Folding one down is a per-Thread
    choice, so the accent rule follows attention (`data-rail-active`) rather than disclosure,
    and what is new to you is said outright with a mark instead of being inferred from what
    you have not yet opened.

    Whether the rail is showing at all belongs to the stage bar, which hides it with CSS keyed
    off its own state (see components/public/stage-chrome.blade.php). The rail therefore has no
    collapse control of its own: it asks to be shown by dispatching `stage-feedback-open`.
--}}

<aside
    class="feedback-rail relative w-full shrink-0 border-t border-zinc-200 bg-white lg:sticky lg:top-0 lg:h-screen lg:w-96 lg:overflow-hidden lg:border-t-0 lg:border-l dark:border-zinc-800 dark:bg-zinc-900"
    x-data="{
        gutter: @js($artifact->isMarkdown()),
        anchorType: @js($anchorType),

        /** The Thread being attended to, and the one under the cursor on either side of the bond. */
        activeId: null,
        hoverId: null,

        /** Threads folded down to their byline by hand. Everything else stays expanded. */
        folded: [],

        /** Threads that were carrying something new to this viewer when the rail drew. */
        unread: @js(array_map('intval', $unreadIds)),

        /**
         * Threads read during this visit. The server write is renderless, so the unread
         * marks it retires would otherwise sit there until the next full redraw.
         */
        seen: [],

        isUnread(id) {
            return this.unread.includes(id) && ! this.seen.includes(id);
        },

        unreadCount() {
            return this.unread.filter((id) => ! this.seen.includes(id)).length;
        },

        /** Threads the alignment has carried past the top and bottom edges of the rail. */
        above: 0,
        below: 0,
        aboveId: null,
        belowId: null,

        rail: null,
        scroller: null,
        sizes: null,
        frame: null,

        boot() {
            this.rail = this.$root;

            // Expanding a Thread or opening a reply box changes its height, which moves
            // everything below it.
            this.sizes = new ResizeObserver(() => this.schedule());

            const onMove = () => this.schedule();

            this.scroller = this.scrollParent(this.stage());
            this.scroller?.addEventListener('scroll', onMove, { passive: true });
            window.addEventListener('scroll', onMove, { passive: true });

            // A resize reflows the article, so tick depths change, not just placements.
            window.addEventListener('resize', () => {
                this.scroller = this.scrollParent(this.stage());
                this.layoutMap();
                this.schedule();
            });

            // A morph rewrites attributes, dropping the transforms the placement pass wrote.
            window.Livewire?.hook('morphed', () => this.schedule());

            this.$nextTick(() => this.sync());
        },

        stage() {
            return document.querySelector('[data-artifact-stage]');
        },

        /**
         * The rail element. $root and $refs resolve by walking up from the element whose
         * directive started the call, and a morph deletes some of those mid-flight — the
         * composer's Cancel button, the header's compose prompt — leaving them undefined in
         * the callback that runs after the round-trip. So the rail is captured at boot and
         * everything is queried from there.
         */
        root() {
            if (! this.rail?.isConnected) {
                this.rail = document.querySelector('.feedback-rail');
            }

            return this.rail;
        },

        /** One Thread in the rail. Scoped past the minimap, which repeats the same ids. */
        item(id) {
            return this.root()?.querySelector(`[data-rail-item][data-comment-id='${id}']`);
        },

        /** The highlight in the stage, whether it landed as a <mark> or on a whole block. */
        highlight(id) {
            return document.querySelector(
                `[data-artifact-stage] [data-comment-id='${id}'], [data-artifact-stage] [data-comment-block~='${id}']`
            );
        },

        /** The element the stage actually scrolls in — <main> on desktop, the window on mobile. */
        scrollParent(el) {
            let node = el?.parentElement;

            while (node && node !== document.body) {
                if (/(auto|scroll)/.test(getComputedStyle(node).overflowY)) {
                    return node;
                }

                node = node.parentElement;
            }

            return null;
        },

        /**
         * Whether the rail is on screen at all. The stage bar hides it outright, so a
         * placement pass fired while it is hidden would measure every box at zero and
         * write placements against nothing.
         */
        visible() {
            return !! this.root()?.getClientRects().length;
        },

        /** Ask the stage bar to bring the rail back, for a jump that arrived from the stage. */
        reveal() {
            if (! this.visible()) {
                window.dispatchEvent(new CustomEvent('stage-feedback-open'));
            }
        },

        /* ------------------------------------------------------------------- placement */

        schedule() {
            if (this.frame) {
                return;
            }

            this.frame = requestAnimationFrame(() => {
                this.frame = null;
                this.layout();
            });
        },

        /**
         * Where an item wants to sit: the viewport y of its highlight, relative to the
         * positioning layer. Null for a Thread whose quote is no longer anywhere in the
         * current revision — it has no text to be level with.
         */
        targetTop(el, layerBox) {
            const point = el.dataset.anchorY;

            if (point) {
                const box = this.stage()?.getBoundingClientRect();

                return box ? box.top + (parseFloat(point) / 100) * box.height - layerBox.top : null;
            }

            const mark = this.highlight(el.dataset.commentId);

            return mark ? mark.getBoundingClientRect().top - layerBox.top - 2 : null;
        },

        /**
         * Place every item against its highlight, then resolve overlaps. One item may hold
         * its exact position — the composer once it has an anchor, else the open Thread —
         * and its neighbours are pushed away from it in both directions; with nothing pinned
         * it is a plain top-down pass. Items with no place in the document dock at the top
         * and reserve their room, so nothing is ever placed where its text is not.
         */
        layout() {
            // The minimap tracks the article whether or not the rail is open, so it goes first.
            this.updateViewport();

            const layer = this.root()?.querySelector('.rail-layer');

            if (! layer) {
                return;
            }

            const items = [...layer.querySelectorAll('[data-rail-item]')];

            // Under lg the rail sits beneath the article: no alignment, so a plain stack, and
            // nothing is hanging off an edge that needs room made for it.
            if (! this.visible() || window.innerWidth < 1024) {
                items.forEach((el) => { el.style.transform = ''; });
                this.above = this.below = 0;
                this.setRunway(0);
                this.tether(null);

                return;
            }

            const layerBox = layer.getBoundingClientRect();
            const gap = 18;
            const place = (el, y) => { el.style.transform = `translateY(${Math.round(y)}px)`; };
            const anchored = [];
            let reserve = 0;
            let tallest = 0;

            items.forEach((el) => {
                const target = this.targetTop(el, layerBox);
                const height = el.offsetHeight;

                tallest = Math.max(tallest, height);

                if (target === null) {
                    place(el, reserve);
                    reserve += height + gap;
                } else {
                    anchored.push({ el, height, target });
                }
            });

            // The heights are already measured, so the runway costs nothing to work out here.
            this.setRunway(tallest > 0 ? Math.min(layerBox.height, tallest + gap) : 0);

            anchored.sort((a, b) => a.target - b.target);

            // Anything whose text has scrolled clean off the top follows it out of the rail
            // rather than jamming against the edge.
            const gone = [];
            const live = [];

            anchored.forEach((item) => {
                if (item.target + item.height + gap < reserve) {
                    place(item.el, item.target);
                    gone.push(item);
                } else {
                    live.push(item);
                }
            });

            const pinnedId = String(layer.querySelector('[data-rail-pin]')?.dataset.commentId ?? '');
            const pin = live.findIndex((item) => item.el.dataset.commentId === pinnedId);

            if (pin > -1) {
                live[pin].y = live[pin].target;

                // Upward from the pin, each item clears the one below it — and nothing else.
                // Flooring this at the top edge as well would give every item the pin has
                // taken the room from the same y, printing them over each other in a pile at
                // the top of the rail. The layer clips, so an item with no room left simply
                // leaves, which is the whole of what being carried out of view means here.
                for (let i = pin - 1; i >= 0; i--) {
                    live[i].y = Math.min(live[i].target, live[i + 1].y - live[i].height - gap);
                }

                for (let i = pin + 1; i < live.length; i++) {
                    live[i].y = Math.max(live[i].target, live[i - 1].y + live[i - 1].height + gap);
                }
            } else {
                let floor = reserve;

                live.forEach((item) => {
                    item.y = Math.max(item.target, floor);
                    floor = item.y + item.height + gap;
                });
            }

            live.forEach((item) => place(item.el, item.y));

            // Pushed off the top by the pin rather than carried off it by the article, but
            // out of the rail either way — so the edge counter accounts for it the same.
            const risen = live.filter((item) => item.y + item.height + gap < reserve);
            const above = [...gone, ...risen];
            const coming = live.filter((item) => item.y > layerBox.height - 28);

            this.above = above.length;
            this.aboveId = above.length ? above[above.length - 1].el.dataset.commentId : null;
            this.below = coming.length;
            this.belowId = coming.length ? coming[0].el.dataset.commentId : null;

            this.tether(this.hoverId ?? this.activeId);
        },

        /**
         * Scroll runway past the end of the article.
         *
         * The rail is only as tall as the viewport, so a Thread level with the last line of
         * the document has nothing beneath it and is cut off by the bottom edge. Extending
         * the article past its last line lets that line be scrolled up far enough for its
         * Thread to come with it. The worst case is an anchor on the very last line, which
         * needs exactly the Thread's own height in runway — so the tallest Thread sets it,
         * capped at the rail's height, past which nothing more can be revealed anyway.
         *
         * Written only when the value changes: this runs inside the per-frame placement pass,
         * and re-styling the article every frame would thrash layout for nothing.
         */
        setRunway(px) {
            const article = this.stage()?.closest('article');

            if (! article || article.dataset.commentRunway === String(px)) {
                return;
            }

            article.dataset.commentRunway = String(px);
            article.style.paddingBottom = px > 0 ? `${px}px` : '';

            // The runway lengthens the document, so every tick's depth in it just changed.
            this.layoutMap();
        },

        /**
         * A hairline from the highlight to the Thread, drawn for whichever end of the bond
         * the pointer is on. The alignment carries the pairing; this removes the last doubt
         * where two anchors sit close together.
         */
        tether(id) {
            const svg = this.root()?.querySelector('.rail-tether');

            if (! svg) {
                return;
            }

            document.querySelectorAll('.atelier-anchor-lit')
                .forEach((el) => el.classList.remove('atelier-anchor-lit'));

            const item = id !== null ? this.item(id) : null;
            const mark = id !== null ? this.highlight(id) : null;

            if (! item || ! mark || ! this.visible() || window.innerWidth < 1024) {
                svg.replaceChildren();

                return;
            }

            mark.classList.add('atelier-anchor-lit');

            const a = mark.getBoundingClientRect();
            const b = item.getBoundingClientRect();
            const x1 = a.right + 6;
            const y1 = a.top + a.height / 2;
            const x2 = b.left - 2;
            const y2 = b.top + 11;
            const mid = x1 + (x2 - x1) / 2;

            const ns = 'http://www.w3.org/2000/svg';
            const path = document.createElementNS(ns, 'path');
            path.setAttribute('d', `M ${x1} ${y1} C ${mid} ${y1}, ${mid} ${y2}, ${x2} ${y2}`);
            path.setAttribute('fill', 'none');
            path.setAttribute('stroke', 'currentColor');
            path.setAttribute('stroke-width', '1.25');

            const dot = document.createElementNS(ns, 'circle');
            dot.setAttribute('cx', x1);
            dot.setAttribute('cy', y1);
            dot.setAttribute('r', '2.5');
            dot.setAttribute('fill', 'currentColor');

            svg.replaceChildren(path, dot);
        },

        /* --------------------------------------------------------------------- minimap */

        /**
         * The article's scroll geometry. The stage scrolls inside <main> on desktop and in
         * the window on mobile, so every proportional measurement asks which one is moving.
         */
        metrics() {
            const s = this.scroller;

            if (s) {
                return {
                    scrollTop: s.scrollTop,
                    scrollHeight: s.scrollHeight || 1,
                    clientHeight: s.clientHeight,
                    rectTop: s.getBoundingClientRect().top,
                };
            }

            return {
                scrollTop: window.scrollY,
                scrollHeight: document.documentElement.scrollHeight || 1,
                clientHeight: window.innerHeight,
                rectTop: 0,
            };
        },

        /**
         * Place every tick at its depth in the article. Depth is scroll-invariant, so this
         * runs on redraw and resize rather than every frame. An anchor whose quote no longer
         * resolves has no depth, so it parks in the bay at the foot of the strip rather than
         * being guessed at or quietly dropped.
         */
        layoutMap() {
            const m = this.metrics();
            let bay = 0;

            this.root()?.querySelectorAll('[data-rail-tick]').forEach((tick) => {
                const el = this.highlight(tick.dataset.commentId);
                let fraction = null;

                if (el) {
                    fraction = (el.getBoundingClientRect().top - m.rectTop + m.scrollTop) / m.scrollHeight;
                } else if (tick.dataset.anchorY) {
                    fraction = parseFloat(tick.dataset.anchorY) / 100;
                }

                if (fraction === null || ! isFinite(fraction)) {
                    tick.dataset.railPlaced = 'no';
                    tick.style.top = 'auto';
                    tick.style.bottom = `${2 + bay * 8}px`;
                    bay += 1;

                    return;
                }

                tick.dataset.railPlaced = 'yes';
                tick.style.bottom = 'auto';
                tick.style.top = `${Math.min(99.5, Math.max(0.5, fraction * 100))}%`;
            });

            const bayMark = this.root()?.querySelector('[data-rail-bay]');

            if (bayMark) {
                bayMark.style.display = bay > 0 ? 'block' : 'none';
            }
        },

        /** Where you currently are in the article, drawn behind the ticks. */
        updateViewport() {
            const view = this.root()?.querySelector('[data-rail-view]');

            if (! view) {
                return;
            }

            const m = this.metrics();

            view.style.top = `${Math.max(0, (m.scrollTop / m.scrollHeight) * 100)}%`;
            view.style.height = `${Math.max(5, (m.clientHeight / m.scrollHeight) * 100)}%`;
        },

        /* ----------------------------------------------------------------- interaction */

        /**
         * Turning to a Thread is what registers it as read. Every Thread is already
         * expanded, so there is no disclosure to take as the signal — attending to one
         * is. Renderless on the server, so it never costs the rail a morph mid-interaction;
         * the mark is retired here so the client does not wait for a redraw to see it go.
         */
        markSeen(id) {
            const key = Number(id);

            if (! this.seen.includes(key)) {
                this.seen.push(key);
            }

            $wire.markThreadSeen(key);
        },

        /** Content → feedback: turn to the Thread and bring it to the eye. */
        focusThread(id) {
            this.reveal();

            this.activeId = Number(id);
            this.markSeen(id);

            this.$nextTick(() => {
                this.layout();

                const item = this.item(id);

                if (item) {
                    this.flash(item);
                }
            });
        },

        /** Feedback → content: bring the anchored text to the eye. */
        focusAnchor(id) {
            const target = this.highlight(id);

            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                this.flash(target);
            }
        },

        /** A minimap tick is a shortcut to both ends of the bond at once. */
        focusBoth(id) {
            this.focusAnchor(id);
            this.focusThread(id);
        },

        /**
         * Turn to a Thread: it becomes the one the rail holds in place and the one the
         * quote and accent are drawn for. It is already expanded, so nothing opens —
         * which is why this is idempotent, and why a click inside a Thread you are
         * already reading neither collapses it nor drags the article about.
         */
        attend(id) {
            this.markSeen(id);

            if (this.activeId === id) {
                return;
            }

            this.activeId = id;

            this.$nextTick(() => {
                this.layout();
                this.focusAnchor(id);
            });
        },

        /** Fold one Thread down to its byline, or unfold it. Expanded is the resting state. */
        toggleFold(id) {
            const at = this.folded.indexOf(id);

            if (at > -1) {
                this.folded.splice(at, 1);
            } else {
                this.folded.push(id);
            }

            this.markSeen(id);
            this.$nextTick(() => this.layout());
        },

        /**
         * Anchor a comment to one paragraph. Its quoted text is captured straight from the
         * rendered stage, so the highlight always re-finds an exact match on redraw.
         */
        commentOnBlock(quote) {
            this.setDraft({ type: 'text_range', quote });
        },

        setDraft(anchor) {
            this.activeId = null;

            $wire.set('draftAnchor', anchor).then(() => this.$nextTick(() => {
                this.sync();
                this.root()?.querySelector('[data-composer] textarea, [data-composer] input')
                    ?.focus({ preventScroll: true });
            }));
        },

        clearDraft() {
            $wire.set('draftAnchor', []).then(() => this.$nextTick(() => this.sync()));
        },

        flash(el) {
            el.classList.add('atelier-anchor-flash');
            setTimeout(() => el.classList.remove('atelier-anchor-flash'), 1200);
        },

        /* ------------------------------------------------------------- stage decoration */

        /**
         * Give every paragraph in the stage a hover pin in the left gutter. Runs on load and
         * after any Thread mutation; skips blocks that already have one so redraws are cheap.
         */
        mountGutter() {
            const stage = this.stage();

            if (! this.gutter || ! stage) {
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
                pin.title = '{{ __('Comment on this paragraph') }}';
                pin.setAttribute('aria-label', '{{ __('Comment on this paragraph') }}');
                // Same 16-grid and stroke weight as the stage bar's glyphs, so the pin reads
                // as one of this page's controls rather than as a dropped-in emoji. Attributes
                // are single-quoted: this whole script sits inside one double-quoted x-data.
                pin.innerHTML = `<svg viewBox='0 0 16 16' fill='none' stroke='currentColor' stroke-width='1.25' stroke-linecap='round' stroke-linejoin='round' aria-hidden='true' class='h-4 w-4 flex-none'><path d='M14 9.25a2 2 0 0 1-2 2H5.5L2 13.75V4.25a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2Z' /></svg>`;
                pin.addEventListener('click', (event) => {
                    event.stopPropagation();
                    this.commentOnBlock(quote);
                });
                block.appendChild(pin);
            });
        },

        /** Re-draw every anchor highlight in the stage, then re-place the rail against them. */
        sync() {
            const stage = this.stage();

            this.root()?.querySelectorAll('[data-rail-item]').forEach((el) => this.sizes?.observe(el));

            if (! stage) {
                this.schedule();

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

            this.root()?.querySelectorAll('[data-rail-item][data-anchor-quote]').forEach((item) => {
                item.dataset.anchorFound = this.drawAnchor(stage, item) ? 'yes' : 'no';
            });

            this.mountGutter();
            this.layoutMap();
            this.schedule();
        },

        /**
         * Anchors store the quoted text, not offsets (ADR-0004), so the spot is recovered
         * by searching for it: an exact hit inside one text node becomes a <mark>; a quote
         * spanning elements falls back to highlighting the innermost block containing it.
         */
        drawAnchor(stage, item) {
            const quote = item.dataset.anchorQuote;

            if (! quote) {
                return false;
            }

            const id = item.dataset.commentId;
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
                mark.dataset.anchorResolved = item.dataset.anchorResolved;
                this.bindAnchor(mark, id);
                range.surroundContents(mark);

                return true;
            }

            return this.drawBlockAnchor(stage, item, quote, id);
        },

        /**
         * Fallback for a quote the text-node pass could not place, because a selection
         * spanning elements loses its intervening markup. Prefers the innermost single
         * block holding the whole quote, else spans first-line block to last-line block —
         * a selection dragged across paragraphs is the common case, and the 280-char cap
         * on capture means the tail is often a partial line.
         */
        drawBlockAnchor(stage, item, quote, id) {
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
                // A token list: two Threads can quote the same block and both keep their anchor.
                el.dataset.commentBlock = [...new Set(
                    (el.dataset.commentBlock ?? '').split(' ').concat(id).filter(Boolean)
                )].join(' ');
                el.dataset.anchorResolved = item.dataset.anchorResolved;
                this.bindAnchor(el, id);
            });

            return hits.length > 0;
        },

        /** Both ends of the bond light together, and either end can summon the other. */
        bindAnchor(el, id) {
            el.onclick = () => this.focusThread(id);
            el.onmouseenter = () => { this.hoverId = id; this.schedule(); };
            el.onmouseleave = () => { this.hoverId = null; this.schedule(); };
        },
    }"
    x-init="boot()"
    x-on:threads-updated.window="$nextTick(() => sync())">

    {{-- The tether. Fixed, so it crosses the stage/rail boundary; never intercepts a pointer. --}}
    <svg class="rail-tether" aria-hidden="true"></svg>

    {{--
        The minimap runs down the seam between article and rail. Every tick's function is
        reachable from its Thread, so the strip stays out of the tab order and the a11y tree
        rather than doubling every Thread with a second focus stop.
    --}}
    <div class="rail-map" aria-hidden="true">
        <div data-rail-view class="rail-map-view"></div>
        <div data-rail-bay class="rail-map-bay"></div>

        @foreach ($this->threads as $thread)
            <button type="button" tabindex="-1"
                wire:key="tick-{{ $thread->id }}"
                data-rail-tick
                data-comment-id="{{ $thread->id }}"
                data-anchor-resolved="{{ $thread->isResolved() ? 'yes' : 'no' }}"
                data-rail-unread="{{ in_array($thread->id, $unreadIds, true) ? 'yes' : 'no' }}"
                @if (($thread->anchor['y'] ?? null) !== null) data-anchor-y="{{ $thread->anchor['y'] }}" @endif
                x-bind:data-rail-unread="isUnread({{ $thread->id }}) ? 'yes' : 'no'"
                x-bind:data-rail-lit="String(hoverId) === @js((string) $thread->id) || activeId === {{ $thread->id }} ? 'yes' : 'no'"
                x-on:mouseenter="hoverId = @js((string) $thread->id); schedule()"
                x-on:mouseleave="hoverId = null; schedule()"
                x-on:click.stop="focusBoth({{ $thread->id }})"
                class="rail-map-tick"
                title="{{ $thread->author->name }} — {{ Str::limit($thread->body, 70) }}">
                <span class="rail-map-bar"></span>
            </button>
        @endforeach
    </div>

    {{--
        Honeypot for every write path in this panel. Positioned off-screen rather
        than hidden with `display: none`, which form-filling automation is known to
        skip; a human never reaches it, so a filled value means a bot.
    --}}
    <div aria-hidden="true" class="pointer-events-none absolute -left-[9999px] h-px w-px overflow-hidden">
        <label for="atelier-website">Website</label>
        <input type="text" id="atelier-website" wire:model="website" tabindex="-1" autocomplete="off" />
    </div>

    <div class="rail-shell flex flex-col lg:h-full">

        {{-- The only horizontal rule in the rail: below it nothing sits anywhere but level
             with its own text. --}}
        <div class="shrink-0 border-b border-zinc-200 px-5 py-3 dark:border-zinc-800">
            <div class="flex items-baseline justify-between gap-2">
                <span class="text-[11px] font-semibold uppercase tracking-widest text-zinc-400 dark:text-zinc-500">{{ __('Feedback') }}</span>
                <span class="flex items-center gap-2 text-[11px] tabular-nums text-zinc-400 dark:text-zinc-500">
                    {{-- How much of the rail is new to you, before you have read a line of it. --}}
                    @if (count($unreadIds) > 0)
                        <span class="rail-unread-count" x-show="unreadCount() > 0">
                            <span x-text="unreadCount()">{{ count($unreadIds) }}</span> {{ __('new') }}
                        </span>
                    @endif
                    {{ $this->threads->count() }}
                </span>
            </div>

            @unless (filled($draftAnchor))
                @if ($artifact->isMarkdown())
                    <p class="mt-1 flex items-start gap-1.5 text-xs leading-snug text-zinc-400 dark:text-zinc-500">
                        <x-public.stage-icon name="comment" />
                        <span>{{ __('Hover a paragraph and click the pin to add a comment.') }}</span>
                    </p>
                @else
                    {{-- No prose to quote, so the anchor is a point on the artifact rather than
                         a passage; it is placed centrally and the Thread docks at the top. --}}
                    <div class="mt-1 flex items-center gap-2">
                        <flux:button size="xs" variant="ghost" x-on:click.stop="setDraft({ type: anchorType, x: 50, y: 50 })">{{ __('Add a comment') }}</flux:button>
                    </div>
                @endif

                {{--
                    The identity and posting fields live in the composer, which only exists
                    once an anchor is picked. A rejection that arrives while it is closed —
                    a refused address, a spent rate limit — would otherwise have nowhere to
                    be shown, so it surfaces here instead. Gated on the composer being shut,
                    so a message is never rendered twice.
                --}}
                <div class="mt-1 empty:mt-0">
                    <flux:error name="captureName" />
                    <flux:error name="captureEmail" />
                    <flux:error name="draft" />
                </div>
            @endunless
        </div>

        {{-- Offered the moment a Client posts (#35): the one contextual chance to catch a
             first-time commenter, who has nothing unread yet and so sees no banner. Hidden
             if the server did not raise it or the device is already subscribed; the
             permission request stays gated on the explicit click. --}}
        @if ($offerPush)
            <div
                x-data="{ show: (window.atelierPush?.isSubscribed?.() ?? false) === false, failed: false }"
                x-show="show"
                x-cloak
                class="flex shrink-0 items-center gap-3 border-b border-emerald-200 bg-emerald-50 px-5 py-3 text-xs text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-100"
                data-test="post-comment-push-offer"
            >
                {{-- The offer only closes on a subscription that actually took. A browser
                     that refused leaves it standing, saying so, rather than quietly
                     folding away as though the ask had been honoured. --}}
                <span x-show="! failed" class="flex-1">{{ __('Want a heads-up when someone replies? We can notify you in this browser.') }}</span>
                <span x-show="failed" x-cloak class="flex-1">{{ __('This browser cannot take notifications. We cannot tell you about new replies here.') }}</span>
                <button
                    type="button"
                    x-show="! failed"
                    x-on:click="(window.atelierPush?.enable('client') ?? Promise.resolve(null)).then(result => { if (result?.ok) { show = false } else { failed = true } })"
                    class="shrink-0 font-semibold underline-offset-2 hover:underline"
                    data-test="post-comment-enable-push"
                >{{ __('Notify me') }}</button>
                <button
                    type="button"
                    x-on:click="show = false"
                    class="shrink-0 rounded p-1 hover:bg-emerald-100 dark:hover:bg-emerald-900/40"
                    aria-label="{{ __('Dismiss') }}"
                >
                    <flux:icon.x-mark variant="micro" class="size-4" />
                </button>
            </div>
        @endif

        {{-- The positioning layer. Items are absolute here on desktop, stacked under lg. --}}
        <div class="rail-layer relative flex-1 px-5 py-4">

            {{--
                The composer only exists once it has a place in the document: picking a
                paragraph sends it to that paragraph and holds it there while everything
                else gets out of its way. Identity capture happens in the same spot.
            --}}
            @if (filled($draftAnchor))
                <div data-rail-item
                    data-composer
                    data-rail-pin
                    data-comment-id="draft"
                    data-anchor-resolved="no"
                    @if (filled($draftQuote)) data-anchor-quote="{{ $draftQuote }}" @endif
                    @if ($draftPointY !== null) data-anchor-y="{{ $draftPointY }}" @endif
                    class="rail-item rail-compose"
                    x-on:click.stop>

                    {{-- A quoted passage says what the comment is about. A point anchor draws
                         nothing in the stage, so its coordinates would only be a number to
                         puzzle over — the artifact you are on is the subject, and you already
                         know which one that is. --}}
                    @if (filled($draftQuote))
                        <p class="text-[11px] italic leading-snug text-amber-700 dark:text-amber-500">
                            “{{ Str::limit($draftQuote, 90) }}”
                        </p>
                    @endif

                    @unless ($identified)
                        <p class="mt-1.5 text-[13px] font-medium text-zinc-800 dark:text-zinc-100">{{ __('Add your details to comment') }}</p>
                        <div class="mt-2 grid gap-2">
                            <flux:input size="sm" wire:model="captureName" placeholder="{{ __('Name') }}" />
                            <flux:input size="sm" wire:model="captureEmail" type="email" placeholder="{{ __('Email') }}" />
                        </div>
                        <flux:error name="captureName" />
                        <flux:error name="captureEmail" />
                        <div class="mt-2 flex items-center gap-2">
                            <flux:button size="sm" variant="primary" wire:click="saveIdentity">{{ __('Continue') }}</flux:button>
                            <flux:button size="sm" variant="ghost" x-on:click="clearDraft()">{{ __('Cancel') }}</flux:button>
                        </div>
                        <p class="mt-2 text-[11px] leading-relaxed text-zinc-400 dark:text-zinc-500">
                            {{ __('We use your name and email only to attribute your feedback; your email is never shown to others. We’ll remember you on this device with a cookie so you don’t have to re-enter your details.') }}
                        </p>
                    @else
                        <flux:textarea wire:model="draft" rows="3" placeholder="{{ __('Share your feedback…') }}" class="mt-2 text-sm" />
                        <flux:error name="draft" />
                        <div class="mt-2 flex items-center gap-2">
                            <flux:button size="xs" variant="primary" wire:click="postComment">{{ __('Post') }}</flux:button>
                            <flux:button size="xs" variant="ghost" x-on:click="clearDraft()">{{ __('Cancel') }}</flux:button>
                            <span class="ml-auto truncate text-[11px] text-zinc-400">{{ $identityName }}</span>
                        </div>
                    @endunless
                </div>
            @endif

            {{-- Threads. Newest first in the DOM; the placement pass orders them by position. --}}
            @foreach ($this->threads as $thread)
                @php
                    $quote = $thread->anchor['quote'] ?? null;
                    $pointY = $thread->anchor['y'] ?? null;
                    $isUnread = in_array($thread->id, $unreadIds, true);
                @endphp

                <div wire:key="thread-{{ $thread->id }}"
                    data-rail-item
                    data-comment-id="{{ $thread->id }}"
                    data-anchor-resolved="{{ $thread->isResolved() ? 'yes' : 'no' }}"
                    data-rail-unread="{{ $isUnread ? 'yes' : 'no' }}"
                    {{-- Expanded and unattended is the state the rail rests in, written here
                         as well as bound so the first paint is already it. --}}
                    data-rail-open="yes"
                    data-rail-active="no"
                    @if (filled($quote)) data-anchor-quote="{{ $quote }}" @endif
                    @if ($pointY !== null) data-anchor-y="{{ $pointY }}" @endif
                    x-bind:data-rail-open="folded.includes({{ $thread->id }}) ? 'no' : 'yes'"
                    x-bind:data-rail-active="activeId === {{ $thread->id }} ? 'yes' : 'no'"
                    x-bind:data-rail-unread="isUnread({{ $thread->id }}) ? 'yes' : 'no'"
                    x-bind:data-rail-lit="String(hoverId) === @js((string) $thread->id) ? 'yes' : 'no'"
                    x-bind:data-rail-pin="activeId === {{ $thread->id }} && ! @js(filled($draftAnchor)) ? '' : null"
                    x-on:mouseenter="hoverId = @js((string) $thread->id); schedule()"
                    x-on:mouseleave="hoverId = null; schedule()"
                    x-on:click="attend({{ $thread->id }})"
                    class="rail-item rail-thread">

                    {{-- Detached: the quote is the only trace left, so it is always shown here. --}}
                    <p class="rail-detached text-[11px] font-medium text-amber-600 dark:text-amber-500">
                        ⚠ {{ __('This text is not in the new version.') }}
                    </p>

                    @if (filled($quote))
                        <p class="rail-quote atelier-anchor-quote text-[11px] italic leading-snug text-zinc-400 dark:text-zinc-500">
                            “{{ Str::limit($quote, 110) }}”
                        </p>
                    @endif

                    <div class="flex items-baseline gap-2">
                        {{-- New to you: the same filled amber mark the pages panel uses, so the
                             surface you arrived from and the one you landed on agree. --}}
                        <span class="rail-unread" title="{{ __('New since you last looked') }}">
                            <span class="sr-only">{{ __('New since you last looked') }}</span>
                        </span>
                        <span class="truncate text-[13px] font-semibold text-zinc-800 dark:text-zinc-100">{{ $thread->author->name }}</span>
                        <span class="shrink-0 text-[11px] text-zinc-400">{{ $thread->created_at?->diffForHumans(short: true) }}</span>
                        @if ($thread->isResolved())
                            <span class="shrink-0 text-[10px] font-medium uppercase tracking-wider text-emerald-600 dark:text-emerald-500">{{ __('Resolved') }}</span>
                        @endif
                        @if ($thread->replies->isNotEmpty())
                            {{-- What a folded Thread trades its replies for; redundant once
                                 they are on screen, which is the resting state. --}}
                            <span class="rail-reply-count shrink-0 text-[11px] tabular-nums text-zinc-400">{{ $thread->replies->count() }} ↩</span>
                        @endif
                        <button type="button" x-on:click.stop="toggleFold({{ $thread->id }})"
                            class="rail-fold ml-auto shrink-0 text-[11px] text-zinc-400 transition hover:text-zinc-800 dark:hover:text-zinc-100"
                            x-bind:aria-expanded="folded.includes({{ $thread->id }}) ? 'false' : 'true'"
                            x-bind:title="folded.includes({{ $thread->id }}) ? @js(__('Expand')) : @js(__('Collapse'))"
                            x-text="folded.includes({{ $thread->id }}) ? '▼' : '▲'">▲</button>
                    </div>

                    <p class="rail-body mt-1 whitespace-pre-line text-[13px] leading-snug text-zinc-600 dark:text-zinc-300">{{ $thread->body }}</p>

                    <div class="rail-open-only">
                        @if ($thread->replies->isNotEmpty())
                            <div class="mt-3 space-y-2.5">
                                @foreach ($thread->replies as $reply)
                                    <div wire:key="reply-{{ $reply->id }}" class="rail-reply">
                                        <div class="flex items-baseline gap-2">
                                            <span class="truncate text-xs font-medium text-zinc-700 dark:text-zinc-200">{{ $reply->author->name }}</span>
                                            <span class="shrink-0 text-[10px] text-zinc-400">{{ $reply->created_at?->diffForHumans(short: true) }}</span>
                                        </div>
                                        <p class="whitespace-pre-line text-xs leading-snug text-zinc-600 dark:text-zinc-400">{{ $reply->body }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="mt-2.5 flex flex-wrap items-center gap-2" x-on:click.stop>
                            @if ($replyingToId === $thread->id)
                                <flux:textarea wire:model="replyDraft" rows="2" placeholder="{{ __('Write a reply…') }}" class="w-full text-sm" />
                                <flux:error name="replyDraft" />
                                <flux:button size="xs" variant="primary" wire:click="reply({{ $thread->id }})">{{ __('Reply') }}</flux:button>
                                <flux:button size="xs" variant="ghost" wire:click="$set('replyingToId', null)">{{ __('Cancel') }}</flux:button>
                            @else
                                <button type="button" wire:click.stop="startReply({{ $thread->id }})"
                                    class="text-[11px] font-medium text-zinc-500 underline-offset-4 transition hover:text-zinc-900 hover:underline dark:text-zinc-400 dark:hover:text-zinc-100">{{ __('Reply') }}</button>
                                {{-- A point anchor draws no marker in the stage, so there is
                                     nothing to send the eye to; only a quoted passage can be
                                     jumped to. --}}
                                @if (filled($quote))
                                    <button type="button" x-on:click.stop="focusAnchor({{ $thread->id }})"
                                        class="rail-jump text-[11px] font-medium text-zinc-500 underline-offset-4 transition hover:text-zinc-900 hover:underline dark:text-zinc-400 dark:hover:text-zinc-100">{{ __('Show in page') }}</button>
                                @endif
                                @if ($this->canResolve)
                                    @if ($thread->isResolved())
                                        <button type="button" wire:click.stop="unresolve({{ $thread->id }})"
                                            class="text-[11px] font-medium text-zinc-500 underline-offset-4 transition hover:text-zinc-900 hover:underline dark:text-zinc-400 dark:hover:text-zinc-100">{{ __('Reopen') }}</button>
                                    @else
                                        <button type="button" wire:click.stop="resolve({{ $thread->id }})"
                                            class="text-[11px] font-medium text-emerald-600 underline-offset-4 transition hover:underline dark:text-emerald-500">{{ __('Resolve') }}</button>
                                    @endif
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- The empty state and the composer are both placed in this layer, so they would
                 sit on top of each other. It only ever says how to start, which is answered
                 the moment a spot is picked — so it stands down once the composer is up. --}}
            @if ($this->threads->isEmpty() && ! filled($draftAnchor))
                <p class="rail-empty text-xs leading-snug text-zinc-400 dark:text-zinc-500">
                    {{ __('No feedback yet.') }}
                    {{ $artifact->isMarkdown() ? __('Hover a paragraph and click the pin to leave the first comment.') : __('Use “Add a comment” to leave the first comment.') }}
                </p>
            @endif

            {{-- What the alignment has carried out of sight, one click away. --}}
            <button type="button" x-bind:class="above > 0 ? 'rail-edge-on' : ''" x-on:click.stop="focusAnchor(aboveId)"
                class="rail-edge rail-edge-top">
                ↑ <span x-text="above"></span> {{ __('above') }}
            </button>
            <button type="button" x-bind:class="below > 0 ? 'rail-edge-on' : ''" x-on:click.stop="focusAnchor(belowId)"
                class="rail-edge rail-edge-bottom">
                ↓ <span x-text="below"></span> {{ __('below') }}
            </button>
        </div>
    </div>
</aside>
