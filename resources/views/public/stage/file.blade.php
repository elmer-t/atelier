@php
    $fileUrl = route('project.artifact.file', [$project, $current]);
    $downloadUrl = route('project.artifact.download', [$project, $current]);
    $isImage = str_starts_with((string) $current->mime_type, 'image/');
@endphp

<div class="flex h-[70vh] flex-col lg:h-full">
    <div class="flex items-center justify-between gap-3 border-b border-zinc-200 px-6 py-3 dark:border-zinc-800">
        <span class="truncate text-sm text-zinc-500 dark:text-zinc-400">{{ $current->original_filename ?? $current->title }}</span>
        <a href="{{ $downloadUrl }}"
           class="shrink-0 rounded-lg bg-zinc-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
            Download ↓
        </a>
    </div>

    @if ($isImage)
        <div class="flex flex-1 items-center justify-center overflow-auto bg-zinc-50 p-6 dark:bg-zinc-950">
            <img src="{{ $fileUrl }}" alt="{{ $current->title }}" class="max-h-full max-w-full object-contain" />
        </div>
    @else
        {{-- Browser renders inline (PDF, some office docs) or falls back to downloading. --}}
        <iframe
            src="{{ $fileUrl }}"
            title="{{ $current->title }}"
            class="w-full flex-1 border-0 bg-white"
            referrerpolicy="no-referrer"></iframe>
    @endif
</div>
