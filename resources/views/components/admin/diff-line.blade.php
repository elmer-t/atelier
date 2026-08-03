{{--
    One line of a Revision comparison: both line-number gutters, the +/− marker,
    and the text. Numbering is one-sided — a removed line has no number in the
    newer Revision, and an added line has none in the older one.
--}}
@props(['row'])

<div @class([
    'flex',
    'bg-emerald-50 dark:bg-emerald-950/30' => $row['type'] === 'added',
    'bg-rose-50 dark:bg-rose-950/30' => $row['type'] === 'removed',
])>
    <span class="w-11 shrink-0 px-2 py-0.5 text-right text-[11px] text-zinc-400 tabular-nums select-none">{{ $row['old'] }}</span>
    <span class="w-11 shrink-0 border-r border-zinc-200 px-2 py-0.5 text-right text-[11px] text-zinc-400 tabular-nums select-none dark:border-zinc-700">{{ $row['new'] }}</span>
    <span @class([
        'w-5 shrink-0 py-0.5 text-center select-none',
        'text-emerald-600 dark:text-emerald-400' => $row['type'] === 'added',
        'text-rose-600 dark:text-rose-400' => $row['type'] === 'removed',
        'text-zinc-300 dark:text-zinc-600' => $row['type'] === 'unchanged',
    ])>{{ $row['type'] === 'added' ? '+' : ($row['type'] === 'removed' ? '−' : ' ') }}</span>
    <span class="min-w-0 flex-1 py-0.5 pr-3 whitespace-pre-wrap">{{ $row['value'] }}</span>
</div>
