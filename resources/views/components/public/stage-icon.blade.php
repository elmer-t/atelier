@props(['name'])

{{--
    The stage bar's icon set: one 16-grid, one stroke weight, so the controls read as
    a set rather than as a row of borrowed glyphs. Heroicons has no panel icon, which
    is the one shape this bar cannot do without.

    `.stage-icon-fill` is always drawn; whether it shows is decided in CSS by
    `[data-stage-on]` on an ancestor, so a toggle can flip state without the glyph
    being re-rendered.
--}}
<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.25"
     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
     {{ $attributes->class('stage-icon') }}>
    @switch($name)
        @case('panel-left')
            <rect class="stage-icon-fill" x="2" y="3" width="4.25" height="10" rx="1.5" fill="currentColor" stroke="none" />
            <rect x="2" y="3" width="12" height="10" rx="2" />
            <path d="M6.25 3v10" />
            @break

        @case('panel-right')
            <rect class="stage-icon-fill" x="9.75" y="3" width="4.25" height="10" rx="1.5" fill="currentColor" stroke="none" />
            <rect x="2" y="3" width="12" height="10" rx="2" />
            <path d="M9.75 3v10" />
            @break

        @case('expand')
            <path d="M6.25 2H2v4.25M9.75 2H14v4.25M6.25 14H2V9.75M9.75 14H14V9.75" />
            @break

        @case('collapse')
            <path d="M2 6.25h4.25V2M14 6.25H9.75V2M2 9.75h4.25V14M14 9.75H9.75V14" />
            @break

        @case('sun')
            <circle cx="8" cy="8" r="2.9" />
            <path d="M8 1v1.4M8 13.6V15M15 8h-1.4M2.4 8H1M12.95 3.05l-1 1M4.05 11.95l-1 1M12.95 12.95l-1-1M4.05 4.05l-1-1" />
            @break

        @case('moon')
            <path d="M13.4 9.7A5.8 5.8 0 0 1 6.3 2.6a5.8 5.8 0 1 0 7.1 7.1Z" />
            @break

        @case('monitor')
            <rect x="2" y="2.75" width="12" height="8.25" rx="1.5" />
            <path d="M5.75 14h4.5M8 11v3" />
            @break

        @case('arrow-left')
            <path d="M13 8H3.25M6.75 4.5 3.25 8l3.5 3.5" />
            @break
    @endswitch
</svg>
