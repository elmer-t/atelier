@props(['title', 'heading', 'code' => null])

{{--
    The shared shell for the public error pages. Link recipients — not developers —
    are the audience, so the copy stays plain and the framing generic (specs §10):
    a 404 must not betray whether a project was archived or never existed.
--}}
<x-public.layout :title="$title">
    <div class="flex min-h-screen items-center justify-center px-6 py-16">
        <div class="w-full max-w-md text-center">
            <p class="text-sm font-medium uppercase tracking-widest text-zinc-400 dark:text-zinc-500">
                {{ config('app.name', 'Atelier') }}
            </p>

            @if (filled($code))
                <p class="mt-8 text-6xl font-semibold tracking-tight text-zinc-200 dark:text-zinc-800">{{ $code }}</p>
            @endif

            <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ $heading }}</h1>

            <div class="mt-3 text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">
                {{ $slot }}
            </div>

            @isset($action)
                <div class="mt-8">
                    {{ $action }}
                </div>
            @endisset
        </div>
    </div>
</x-public.layout>
