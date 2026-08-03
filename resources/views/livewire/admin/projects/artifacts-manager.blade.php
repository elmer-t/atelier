<div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
    <div class="mb-4 flex items-center justify-between">
        <div>
            <flux:heading size="sm">Artifacts</flux:heading>
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

    {{-- Artifact list --}}
    @if ($this->artifacts->isEmpty())
        <flux:text class="text-zinc-400">No artifacts yet.</flux:text>
    @else
        <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @foreach ($this->artifacts as $artifact)
                <li wire:key="artifact-{{ $artifact->id }}" class="flex items-center gap-3 py-2">
                    <flux:badge size="sm" :color="$artifact->isHtml() ? 'purple' : ($artifact->isFile() ? 'blue' : 'zinc')">
                        {{ $artifact->type->label() }}
                    </flux:badge>
                    <div class="flex-1 truncate">
                        <span class="truncate text-sm">{{ $artifact->title }}</span>
                        @if ($artifact->isFile())
                            <span class="ml-1 text-xs text-zinc-400">
                                · {{ $artifact->isDownload() ? 'download' : 'in stage' }}
                            </span>
                        @endif
                    </div>
                    <div class="flex items-center gap-0.5">
                        <flux:button size="xs" variant="ghost" icon="chevron-up"
                            wire:click="moveUp({{ $artifact->id }})" :disabled="$loop->first" />
                        <flux:button size="xs" variant="ghost" icon="chevron-down"
                            wire:click="moveDown({{ $artifact->id }})" :disabled="$loop->last" />
                        <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="startEdit({{ $artifact->id }})" />
                        <flux:button size="xs" variant="ghost" icon="trash"
                            wire:click="deleteArtifact({{ $artifact->id }})"
                            wire:confirm="Delete this artifact?" />
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
                    {{ $editingArtifactId ? 'Edit' : 'New' }}
                    {{ $formType === 'html' ? 'HTML page' : ($formType === 'file' ? 'file' : 'markdown page') }}
                </flux:heading>

                <flux:input wire:model="artifactTitle" label="Title" placeholder="Design brief" />

                @if ($formType === 'markdown')
                    @php
                        $ordinals = $this->revisionOrdinals;
                        $railStates = $this->railStates;
                        $from = $this->compareFrom;
                        $to = $this->compareTo;
                        $comparison = $this->comparison;
                        // The panel is headed by the newer of the pair — the Revision whose
                        // save the comparison describes — or by the one being read alone.
                        $subject = $to ?? $from;
                    @endphp

                    {{-- The history panel takes the editor's own slot rather than covering
                         it, so reading an earlier Revision is one click away from typing
                         again. Livewire keeps the draft body while it is open. --}}
                    @if ($from === null)
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

                        @unless ($editingArtifactId)
                            <flux:input type="file" wire:model="mdFile" label="…or upload a .md file" accept=".md,.markdown,.txt" />
                            <flux:error name="mdFile" />
                        @endunless
                    @else
                        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700" data-test="revision-panel">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-zinc-200 px-3 py-2 dark:border-zinc-700">
                                <flux:heading size="sm">
                                    Revision {{ $ordinals[$from->id] }}@if ($to) → {{ $ordinals[$to->id] }}@endif
                                </flux:heading>

                                <flux:text size="sm" class="text-zinc-400">
                                    saved {{ $subject->created_at?->format('M j, H:i') }}
                                    ({{ $subject->created_at?->diffForHumans() }})
                                    by {{ $subject->author?->name ?? 'Unknown' }}
                                </flux:text>

                                @if ($comparison)
                                    <span class="font-mono text-xs">
                                        <span class="text-emerald-600 dark:text-emerald-400">+{{ $comparison['added'] }}</span>
                                        <span class="ml-1 text-rose-600 dark:text-rose-400">&minus;{{ $comparison['removed'] }}</span>
                                    </span>
                                @endif

                                <div class="ml-auto flex items-center gap-1">
                                    @if ($from->id !== $this->revisions->first()?->id)
                                        <flux:button size="xs" variant="ghost" icon="arrow-uturn-left" type="button"
                                            data-test="restore-revision"
                                            wire:click="restoreRevision({{ $from->id }})"
                                            wire:confirm="Restore Revision {{ $ordinals[$from->id] }}? It is added as a new Revision, so no history is lost.">
                                            Restore Revision {{ $ordinals[$from->id] }}
                                        </flux:button>
                                    @endif
                                    <flux:button size="xs" variant="ghost" icon="x-mark" type="button"
                                        data-test="close-revision-panel" wire:click="clearRevisionSelection">
                                        Close
                                    </flux:button>
                                </div>
                            </div>

                            @if ($comparison)
                                <div class="max-h-[32rem] overflow-auto font-mono text-[12.5px] leading-relaxed">
                                    @foreach ($comparison['sections'] as $section)
                                        @if ($section['changed'])
                                            <div class="bg-zinc-100 px-3 py-1 text-[11px] text-zinc-500 dark:bg-zinc-800">{{ $section['header'] }}</div>

                                            @foreach ($section['rows'] as $row)
                                                <x-admin.diff-line :row="$row" />
                                            @endforeach
                                        @else
                                            {{-- Untouched text is most of any document, so it is offered rather than shown. --}}
                                            <div x-data="{ open: false }" wire:key="unchanged-{{ $loop->index }}">
                                                <button type="button" x-on:click="open = true" x-show="! open"
                                                    class="flex w-full items-center gap-2 bg-sky-50/60 px-3 py-1 text-left text-[11px] text-sky-700 hover:bg-sky-100 dark:bg-sky-950/30 dark:text-sky-300 dark:hover:bg-sky-950/60">
                                                    <flux:icon.chevron-up-down variant="micro" />
                                                    Show {{ count($section['rows']) }} unchanged {{ Str::plural('line', count($section['rows'])) }}
                                                </button>

                                                <div x-show="open" x-collapse>
                                                    @foreach ($section['rows'] as $row)
                                                        <x-admin.diff-line :row="$row" />
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @else
                                {{-- One Revision picked: read it in full, unchanged (User Story 4). --}}
                                <pre class="max-h-[32rem] overflow-auto px-3 py-2 font-mono text-[12.5px] leading-relaxed whitespace-pre-wrap">{{ $from->body }}</pre>
                            @endif
                        </div>
                    @endif

                    {{-- Revision history (Creator-only; never shown to Clients) --}}
                    @if ($editingArtifactId && $this->revisions->isNotEmpty())
                        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700" data-test="revision-rail">
                            <div class="flex items-center gap-3 border-b border-zinc-200 px-3 py-2 dark:border-zinc-700">
                                <flux:heading size="sm">History</flux:heading>
                                <flux:text size="sm" class="text-zinc-400">
                                    {{ $this->revisions->count() }} {{ Str::plural('Revision', $this->revisions->count()) }}
                                </flux:text>
                            </div>

                            {{-- Oldest left, newest right. One click reads a revision, a second
                                 compares the two, a third starts again. --}}
                            <div class="overflow-x-auto px-3 py-3">
                                <div class="flex min-w-max items-stretch">
                                    @foreach ($this->revisions->reverse() as $revision)
                                        @php
                                            $state = $railStates[$revision->id];
                                        @endphp

                                        @unless ($loop->first)
                                            <div @class([
                                                'mt-3 h-0.5 w-6 shrink-0 self-start',
                                                'bg-sky-400' => $state['between'] || $state['part'] === 'to',
                                                'bg-zinc-200 dark:bg-zinc-700' => ! $state['between'] && $state['part'] !== 'to',
                                            ])></div>
                                        @endunless

                                        <button type="button" wire:key="rev-{{ $revision->id }}"
                                            data-test="pick-revision-{{ $revision->id }}"
                                            aria-pressed="{{ $state['picked'] ? 'true' : 'false' }}"
                                            wire:click="pickRevision({{ $revision->id }})"
                                            @class([
                                                'flex w-36 shrink-0 flex-col items-start rounded-lg px-2 py-1 text-left transition',
                                                'bg-sky-50 dark:bg-sky-950/40' => $state['picked'] || $state['between'],
                                                'hover:bg-zinc-50 dark:hover:bg-zinc-800/60' => ! $state['picked'] && ! $state['between'],
                                            ])>
                                            <span @class([
                                                'size-3 rounded-full ring-2 ring-offset-2 ring-offset-white dark:ring-offset-zinc-800',
                                                'bg-sky-500 ring-sky-500' => $state['picked'],
                                                'bg-white ring-sky-400 dark:bg-zinc-800' => $state['between'],
                                                'bg-zinc-300 ring-transparent dark:bg-zinc-600' => ! $state['picked'] && ! $state['between'],
                                            ])></span>

                                            <span class="mt-2 text-xs font-medium">
                                                Revision {{ $ordinals[$revision->id] }}
                                                @if ($loop->last)
                                                    <span class="text-emerald-600 dark:text-emerald-400">· current</span>
                                                @endif
                                            </span>
                                            <span class="w-full truncate text-[11px] text-zinc-400">{{ $revision->author?->name ?? 'Unknown' }}</span>
                                            <span class="text-[11px] text-zinc-400">{{ $revision->created_at?->format('M j, H:i') }}</span>

                                            @if ($state['part'])
                                                <span class="mt-1 rounded bg-sky-500 px-1 text-[10px] font-semibold text-white">
                                                    {{ $state['part'] }}
                                                </span>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>

                                <flux:text size="sm" class="mt-2 text-zinc-400">
                                    @if ($compareToId !== null)
                                        Click any Revision to start a new comparison.
                                    @elseif ($compareFromId !== null)
                                        Click a second Revision to compare the two, or this one again to close it.
                                    @else
                                        Click a Revision to read it. Click a second one to compare the two.
                                    @endif
                                </flux:text>
                            </div>
                        </div>
                    @endif
                @elseif ($formType === 'html')
                    @if ($editingArtifactId)
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
                    @if ($editingArtifactId)
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
