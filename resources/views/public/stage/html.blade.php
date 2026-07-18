<div class="flex h-[60vh] flex-col lg:h-full">
    <iframe
        src="{{ $current->sandboxUrl() }}"
        title="{{ $current->title }}"
        class="h-full w-full flex-1 border-0 bg-white"
        sandbox="allow-scripts allow-forms allow-popups allow-same-origin"
        referrerpolicy="no-referrer"></iframe>
</div>
