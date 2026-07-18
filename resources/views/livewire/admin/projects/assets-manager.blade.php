<div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
    <div class="mb-4 flex items-center justify-between">
        <div>
            <flux:heading size="sm">Assets</flux:heading>
            <flux:subheading>Markdown, HTML mockups and files, in the order clients see them.</flux:subheading>
        </div>
        @unless ($showForm)
            <flux:button.group>
                <flux:button size="sm" icon="document-text" wire:click="startCreate('markdown')">Markdown</flux:button>
                <flux:button size="sm" icon="code-bracket" wire:click="startCreate('html')">HTML</flux:button>
                <flux:button size="sm" icon="paper-clip" wire:click="startCreate('file')">File</flux:button>
            </flux:button.group>
        @endunless
    </div>

    {{-- Asset list --}}
    @if ($this->assets->isEmpty())
        <flux:text class="text-zinc-400">No assets yet.</flux:text>
    @else
        <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @foreach ($this->assets as $asset)
                <li wire:key="asset-{{ $asset->id }}" class="flex items-center gap-3 py-2">
                    <flux:badge size="sm" :color="$asset->isHtml() ? 'purple' : ($asset->isFile() ? 'blue' : 'zinc')">
                        {{ $asset->type->label() }}
                    </flux:badge>
                    <div class="flex-1 truncate">
                        <span class="truncate text-sm">{{ $asset->title }}</span>
                        @if ($asset->isFile())
                            <span class="ml-1 text-xs text-zinc-400">
                                · {{ $asset->isDownload() ? 'download' : 'in stage' }}
                            </span>
                        @endif
                    </div>
                    <div class="flex items-center gap-0.5">
                        <flux:button size="xs" variant="ghost" icon="chevron-up"
                            wire:click="moveUp({{ $asset->id }})" :disabled="$loop->first" />
                        <flux:button size="xs" variant="ghost" icon="chevron-down"
                            wire:click="moveDown({{ $asset->id }})" :disabled="$loop->last" />
                        <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="startEdit({{ $asset->id }})" />
                        <flux:button size="xs" variant="ghost" icon="trash"
                            wire:click="deleteAsset({{ $asset->id }})"
                            wire:confirm="Delete this asset?" />
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- Create / edit form --}}
    @if ($showForm)
        <div class="mt-5 border-t border-zinc-200 pt-5 dark:border-zinc-700">
            <form wire:submit="save" class="space-y-4">
                <flux:heading size="sm">
                    {{ $editingAssetId ? 'Edit' : 'New' }}
                    {{ $formType === 'html' ? 'HTML page' : ($formType === 'file' ? 'file' : 'markdown page') }}
                </flux:heading>

                <flux:input wire:model="assetTitle" label="Title" placeholder="Design brief" />

                @if ($formType === 'markdown')
                    <flux:textarea wire:model="body" label="Markdown" rows="12" class="font-mono text-sm"
                        placeholder="# Heading&#10;&#10;Write markdown here…" />

                    <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800/50">
                        <flux:label>Insert image</flux:label>
                        <div class="mt-2 flex items-center gap-2">
                            <flux:input type="file" wire:model="image" accept="image/*" class="flex-1" />
                            <flux:button size="sm" wire:click="uploadImage" :disabled="! $image">Upload &amp; insert</flux:button>
                        </div>
                        <flux:error name="image" />
                        <flux:text size="sm" class="mt-1 text-zinc-400">Appends a markdown image reference to the body.</flux:text>
                    </div>

                    @unless ($editingAssetId)
                        <flux:input type="file" wire:model="mdFile" label="…or upload a .md file" accept=".md,.markdown,.txt" />
                        <flux:error name="mdFile" />
                    @endunless
                @elseif ($formType === 'html')
                    @if ($editingAssetId)
                        <flux:callout icon="information-circle" variant="secondary">
                            <flux:callout.text>Upload a new zip to replace the current bundle, or leave empty to keep it.</flux:callout.text>
                        </flux:callout>
                    @endif
                    <flux:input type="file" wire:model="zipFile" label="HTML bundle (.zip)" accept=".zip" />
                    <flux:error name="zipFile" />
                    <flux:input wire:model="entryFile" label="Entry file (optional)" placeholder="index.html"
                        description="Leave blank to auto-detect (index.html preferred)." />

                    <div wire:loading wire:target="zipFile,save">
                        <flux:text size="sm" class="text-zinc-400">Uploading &amp; unpacking…</flux:text>
                    </div>
                @else
                    @if ($editingAssetId)
                        <flux:callout icon="information-circle" variant="secondary">
                            <flux:callout.text>Upload a new file to replace the current one, or leave empty to keep it.</flux:callout.text>
                        </flux:callout>
                    @endif
                    <flux:input type="file" wire:model="file" label="File" />
                    <flux:error name="file" />

                    <flux:radio.group wire:model="placement" label="Placement" variant="segmented">
                        <flux:radio value="stage" label="Show in stage" />
                        <flux:radio value="download" label="Download only" />
                    </flux:radio.group>
                    <flux:text size="sm" class="text-zinc-400">
                        Staged files open in the viewer (browser-rendered); download-only files appear in the downloads list.
                    </flux:text>

                    <div wire:loading wire:target="file,save">
                        <flux:text size="sm" class="text-zinc-400">Uploading…</flux:text>
                    </div>
                @endif

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="resetForm" type="button">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Save</flux:button>
                </div>
            </form>
        </div>
    @endif
</div>
