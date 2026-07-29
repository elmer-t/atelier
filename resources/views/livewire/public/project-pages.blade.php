@php
    use App\Enums\AttentionLevel;
@endphp

{{--
    The pages panel. The app name, the project and this panel's own name are all named in
    the stage bar directly above it, so the list starts at the top.

    Each page carries one 6px mark at its trailing edge saying what its feedback wants
    from this viewer — a four-step ladder descending in weight, of which only the top rung
    is a filled shape. The mark is drawn from the row's `data-attention`, so the title's
    weight and a resolved page's retreat into lower contrast follow from the same
    attribute (see app.css) and cannot drift apart from it.

    The stage bar can open the panel out, which adds a line per row naming that same state
    in words. Every row gets one, including the pages with no feedback, so opening does not
    turn an even list into a ragged one. That line is hidden by CSS keyed off the bar's
    `data-stage-details`, the same way the panels themselves are hidden.
--}}

<aside data-stage-nav class="w-full shrink-0 border-b border-zinc-200 bg-white lg:sticky lg:top-0 lg:h-screen lg:w-72 lg:overflow-y-auto lg:border-b-0 lg:border-r dark:border-zinc-800 dark:bg-zinc-900">
    @if ($this->stageArtifacts->isNotEmpty())
        <nav class="px-3 py-4">
            <ul class="space-y-0.5">
                @foreach ($this->stageArtifacts as $artifact)
                    @php
                        $active = $current && $artifact->is($current);
                        $attention = $this->attention[$artifact->id];
                        $level = $attention->level();
                    @endphp
                    <li>
                        <a href="{{ route('project.artifact', [$project, $artifact]) }}"
                           data-attention="{{ $level->value }}"
                           @class([
                               'stage-page block rounded-lg px-3 py-2 transition',
                               'bg-zinc-900 dark:bg-white' => $active,
                               'hover:bg-zinc-100 dark:hover:bg-zinc-800' => ! $active,
                               'stage-page-active' => $active,
                           ])>
                            <span class="flex items-center gap-2 text-sm">
                                <span class="stage-page-glyph text-xs">{{ $artifact->isHtml() ? '◆' : ($artifact->isFile() ? '▣' : '▤') }}</span>
                                <span class="stage-page-title truncate">{{ $artifact->title }}</span>
                                <span class="stage-attention" @if ($level->label() !== '') title="{{ $level->label() }}" @endif></span>
                                @if ($level !== AttentionLevel::None)
                                    <span class="sr-only">{{ $level->label() }}</span>
                                @endif
                            </span>

                            <span data-stage-detail class="stage-page-detail truncate">
                                @if ($attention->unread > 0)
                                    {{ trans_choice('{1}:count new reply for you|[2,*]:count new replies for you', $attention->unread, ['count' => $attention->unread]) }}
                                    @if ($attention->awaiting > 0)
                                        <span class="opacity-60">· {{ __(':count read', ['count' => $attention->awaiting]) }}</span>
                                    @endif
                                @elseif ($attention->awaiting > 0)
                                    {{ trans_choice('{1}:count reply still unanswered|[2,*]:count replies still unanswered', $attention->awaiting, ['count' => $attention->awaiting]) }}
                                @elseif ($level === AttentionLevel::Open)
                                    {{ trans_choice('{1}:count open thread|[2,*]:count open threads', $attention->open, ['count' => $attention->open]) }}
                                @elseif ($level === AttentionLevel::Resolved)
                                    ✓ {{ __('All resolved') }}
                                @else
                                    {{ __('No feedback') }}
                                @endif
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif

    @if ($this->downloads->isNotEmpty())
        <div class="border-t border-zinc-200 px-3 py-4 dark:border-zinc-800">
            <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Downloads') }}</p>
            <ul class="space-y-0.5">
                @foreach ($this->downloads as $artifact)
                    <li>
                        <a href="{{ route('project.artifact.download', [$project, $artifact]) }}"
                           class="flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm text-zinc-600 transition hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                            <span class="truncate">{{ $artifact->original_filename ?? $artifact->title }}</span>
                            <span class="shrink-0 text-xs text-zinc-400 dark:text-zinc-500">↓</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</aside>
