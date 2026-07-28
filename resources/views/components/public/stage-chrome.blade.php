@props(['project', 'current' => null])

@php
    /** Where the panel choice is remembered, so it survives the next artifact's page load. */
    $panelsKey = 'atelier.stage.panels';

    $appearances = [
        'system' => ['monitor', __('System')],
        'light' => ['sun', __('Light')],
        'dark' => ['moon', __('Dark')],
    ];
@endphp

{{--
    The stage bar, and the panel state it drives.

    A link recipient arrives here having never seen the page before, so every control
    is legible without hovering anything: the bar pays 2.75rem of height for that and
    buys it back by being the head of the layout rather than a band laid over it. Its
    zones are measured off the real sidebar and rail, so their hairlines continue those
    columns' own borders down the page, and each panel's toggle stands inside the panel
    it opens — which is what lets the toggles go unlabelled. The title takes the middle
    zone, and so centres over the article rather than over the viewport.

    Panels are hidden by CSS keyed off the data attributes published here rather than by
    `x-show` on the panels themselves: the feedback rail is a Livewire component root,
    and a morph re-evaluates bound attributes against a stale scope (see the note on
    setCollapsed() in livewire/public/artifact-comments.blade.php), so nothing outside
    the rail may bind to its class list.
--}}
<div
    x-data="{
        pages: true,
        feedback: true,

        /** What was showing before focus mode, so leaving it puts things back. */
        restore: null,

        /** Mirrors Flux's appearance so the segmented control can track it. */
        appearance: window.localStorage.getItem('flux.appearance') || 'system',

        init() {
            const saved = window.localStorage.getItem(@js($panelsKey));

            if (saved) {
                const state = JSON.parse(saved);

                this.pages = state.pages ?? true;
                this.feedback = state.feedback ?? true;
            }

            this.$watch('pages', () => this.settle());
            this.$watch('feedback', () => this.settle());

            this.appearance = this.$flux?.appearance ?? this.appearance;
        },

        get focused() {
            return ! this.pages && ! this.feedback;
        },

        /**
         * Every artifact is its own page load, so a visitor who cleared the chrome to
         * read would have to clear it again on the next page. The choice is kept on the
         * device rather than on the server: it describes this screen, not this person.
         *
         * Toggling a panel also reflows the article without firing a resize, and the
         * feedback rail places every thread level with text whose position just moved.
         * So the resize it already listens for is raised by hand, a frame later, once
         * the new widths have been laid out.
         */
        settle() {
            window.localStorage.setItem(
                @js($panelsKey),
                JSON.stringify({ pages: this.pages, feedback: this.feedback })
            );

            requestAnimationFrame(() => window.dispatchEvent(new Event('resize')));
        },

        toggleFocus() {
            if (this.focused) {
                this.pages = this.restore?.pages ?? true;
                this.feedback = this.restore?.feedback ?? true;

                return;
            }

            this.restore = { pages: this.pages, feedback: this.feedback };
            this.pages = false;
            this.feedback = false;
        },

        /**
         * The public layout carries Flux's appearance script but not its full bundle,
         * so out here the theme is the small inline applier rather than the Alpine
         * magic the admin side binds to. Both are honoured: same localStorage key.
         */
        setAppearance(value) {
            this.appearance = value;

            if (this.$flux) {
                this.$flux.appearance = value;
            } else {
                window.Flux?.applyAppearance?.(value);
            }
        },

        /**
         * Shortcuts for the two panels and for focus mode. Held back while a field has
         * the caret, or a bracket typed into a comment would fold the page away.
         */
        onKey(event) {
            const el = document.activeElement;

            if (event.metaKey || event.ctrlKey || event.altKey) { return; }
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(el?.tagName) || el?.isContentEditable) { return; }

            if (event.key === '[') { this.pages = ! this.pages; }
            else if (event.key === ']') { this.feedback = ! this.feedback; }
            else if (event.key === 'f') { this.toggleFocus(); }
            else if (event.key === 'Escape' && this.focused) { this.toggleFocus(); }
        },
    }"
    x-bind:data-stage-pages="pages ? 'on' : 'off'"
    x-bind:data-stage-feedback="feedback ? 'on' : 'off'"
    x-on:keydown.window="onKey($event)">

    <header
        x-data="{
            zoneLeft: 0,
            zoneRight: 0,

            /** Whether each zone actually took its column's width — see fits(). */
            fitLeft: false,
            fitRight: false,

            /**
             * The rail sets its own width and can be collapsed to a tab from inside
             * itself, so the columns are measured rather than assumed. Below lg they
             * stack, and the zones give up their widths and pack to their contents.
             */
            measure() {
                const wide = window.innerWidth >= 1024;
                const width = (el) => (el && getComputedStyle(el).display !== 'none')
                    ? el.getBoundingClientRect().width
                    : 0;

                this.zoneLeft = wide ? width(document.querySelector('[data-stage-nav]')) : 0;
                this.zoneRight = wide ? width(document.querySelector('.feedback-rail')) : 0;

                this.$nextTick(() => {
                    this.fitLeft = this.fits(this.$refs.zoneLeft, this.zoneLeft);
                    this.fitRight = this.fits(this.$refs.zoneRight, this.zoneRight);
                });
            },

            /**
             * A zone is floored at the width of its own controls, so a rail collapsed to
             * its tab leaves the zone standing wider than the column it is in. Its seam
             * hairline would then be drawn well off the border it is meant to continue —
             * so the line is only claimed when the two widths actually agree.
             */
            fits(el, column) {
                return column > 0 && !! el && Math.abs(el.getBoundingClientRect().width - column) < 1;
            },
        }"
        x-init="
            measure();
            $nextTick(() => measure());
            window.addEventListener('resize', () => measure());
        "
        {{-- The rail animates its own width over 200ms, and does it without telling anyone. --}}
        x-on:click.window="setTimeout(() => measure(), 260)"
        class="stage-bar">

        {{-- The pages column. --}}
        <div class="stage-bar-zone gap-2"
            x-ref="zoneLeft"
            x-bind:class="fitLeft ? 'border-r' : ''"
            x-bind:style="zoneLeft ? `width: ${zoneLeft}px` : ''">

            <a href="{{ route('home') }}"
               class="flex items-center gap-1.5 text-zinc-500 transition hover:text-zinc-900 dark:hover:text-white">
                <x-public.stage-icon name="arrow-left" />
                <span class="stage-bar-label">{{ config('app.name', 'Atelier') }}</span>
            </a>

            <button type="button" class="stage-bar-btn ml-auto"
                x-bind:data-stage-on="pages ? 'yes' : 'no'"
                x-on:click="pages = ! pages"
                x-bind:title="(pages ? @js(__('Hide pages')) : @js(__('Show pages'))) + '  ['"
                aria-label="{{ __('Toggle pages panel') }}">
                <x-public.stage-icon name="panel-left" />
            </button>
        </div>

        {{-- The stage column: the title centres over the article, not over the screen. --}}
        <div class="flex min-w-0 flex-1 items-center justify-center px-4">
            <p class="truncate text-[13px] text-zinc-500 dark:text-zinc-400">
                <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $project->title }}</span>
                @if ($current)
                    <span class="text-zinc-300 dark:text-zinc-700">·</span>
                    {{ $current->title }}
                @endif
            </p>
        </div>

        {{-- The feedback column, closed off by the controls that belong to no panel. --}}
        <div class="stage-bar-zone gap-1"
            x-ref="zoneRight"
            x-bind:class="fitRight ? 'border-l' : ''"
            x-bind:style="zoneRight ? `width: ${zoneRight}px` : ''">

            <button type="button" class="stage-bar-btn"
                x-bind:data-stage-on="feedback ? 'yes' : 'no'"
                x-on:click="feedback = ! feedback"
                x-bind:title="(feedback ? @js(__('Hide feedback')) : @js(__('Show feedback'))) + '  ]'"
                aria-label="{{ __('Toggle feedback panel') }}">
                <x-public.stage-icon name="panel-right" />
            </button>

            <div class="ml-auto flex items-center gap-1 pl-3">
                <button type="button" class="stage-bar-btn"
                    x-bind:data-stage-on="focused ? 'yes' : 'no'"
                    x-on:click="toggleFocus()"
                    x-bind:title="(focused ? @js(__('Show panels')) : @js(__('Focus mode'))) + '  F'"
                    aria-label="{{ __('Toggle focus mode') }}">
                    <span x-show="! focused"><x-public.stage-icon name="expand" /></span>
                    <span x-show="focused" style="display: none"><x-public.stage-icon name="collapse" /></span>
                </button>

                <div class="flex items-center gap-0.5 rounded-md border border-zinc-200 p-0.5 dark:border-zinc-700">
                    @foreach ($appearances as $value => [$glyph, $label])
                        <button type="button" class="stage-bar-btn"
                            x-bind:data-stage-on="appearance === @js($value) ? 'yes' : 'no'"
                            x-on:click="setAppearance(@js($value))"
                            title="{{ $label }}"
                            aria-label="{{ $label }}">
                            <x-public.stage-icon :name="$glyph" />
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </header>

    {{ $slot }}
</div>
