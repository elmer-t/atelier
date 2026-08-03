<?php

namespace Database\Seeders;

use App\Enums\ArtifactType;
use App\Enums\UserRole;
use App\Models\Artifact;
use App\Models\Project;
use App\Models\User;
use App\Support\Artifacts\MarkdownRevisionWriter;
use Illuminate\Database\Seeder;

/**
 * PROTOTYPE / DEMO DATA — safe to wipe.
 *
 * The compare view has nothing to judge without a real history, and the shipped
 * data has one Revision per Artifact. This invents a plausible past for two of the
 * Harbor District documents: edits alternating between the Creator and the Agent,
 * chosen to exercise every case a diff has to survive — a paragraph rewritten a few
 * words at a time, a list item inserted mid-sequence (renumbering everything after
 * it), a whole section appended, a two-word fix, and long runs of untouched prose.
 *
 * History is built *backwards* from whatever body is live right now, by undoing one
 * save at a time. So the current Revision is byte-identical to what was there
 * before, no Client-facing text moves, and Comment anchors keep resolving.
 *
 *   php artisan db:seed --class=PrototypeRevisionsSeeder
 */
class PrototypeRevisionsSeeder extends Seeder
{
    public function run(): void
    {
        $project = Project::query()->where('title', 'Harbor District Masterplan')->first()
            ?? Project::query()->orderBy('id')->firstOrFail();

        $creator = User::query()->where('role', UserRole::Creator)->orderBy('id')->firstOrFail();
        $agent = User::query()->where('role', UserRole::Agent)->orderBy('id')->first() ?? $creator;

        $writer = app(MarkdownRevisionWriter::class);

        foreach ($this->rewinds() as $title => $rewinds) {
            $artifact = $project->artifacts()
                ->where('type', ArtifactType::Markdown)
                ->where('title', $title)
                ->first();

            if ($artifact === null) {
                $this->command->warn("  skipped {$title} — not in this project");

                continue;
            }

            $bodies = $this->rewind($artifact, $rewinds);

            // Start from a clean chain so re-running does not pile up near-duplicates.
            $artifact->update(['current_revision_id' => null]);
            $artifact->revisions()->delete();

            // Oldest first, roughly one save every few hours over the past few days.
            $minutesAgo = count($bodies) * 340;

            foreach ($bodies as $index => $body) {
                // $rewinds[$i] undoes the save that produced $bodies[$i + 1].
                $author = ($rewinds[$index - 1]['by'] ?? 'creator') === 'agent' ? $agent : $creator;

                $revision = $writer->update($artifact, $body, $author);
                $revision?->forceFill(['created_at' => now()->subMinutes($minutesAgo)])->save();

                $minutesAgo = max(0, $minutesAgo - 340);
            }

            $this->command->info("  {$title}: ".$artifact->revisions()->count().' revisions');
        }
    }

    /**
     * Replay the undo list against the live body, newest save first, and hand back
     * the bodies oldest first. A step whose text no longer matches is reported and
     * skipped rather than silently producing a bogus diff.
     *
     * @param  list<array{by: string, undo: callable(string): string}>  $rewinds
     * @return list<string>
     */
    private function rewind(Artifact $artifact, array $rewinds): array
    {
        $body = (string) $artifact->body;
        $bodies = [$body];

        foreach (array_reverse($rewinds) as $rewind) {
            $undone = ($rewind['undo'])($body);

            if ($undone === $body) {
                $this->command->warn("  {$artifact->title}: an undo step no longer matches the body — skipped");

                continue;
            }

            array_unshift($bodies, $undone);
            $body = $undone;
        }

        return $bodies;
    }

    /**
     * Per Artifact, the saves to undo — oldest save first, each described by who made
     * it and how to reverse it.
     *
     * @return array<string, list<array{by: string, undo: callable(string): string}>>
     */
    private function rewinds(): array
    {
        return [
            'Vision & Design Principles' => [
                // A principle inserted mid-list, renumbering everything below it —
                // the case a line diff reports worst.
                [
                    'by' => 'creator',
                    'undo' => fn (string $b): string => str_replace(
                        [
                            '## Five principles',
                            "3. **Build for weather that is coming, not weather that was.** Ground floors sit above the 2100 flood line and the whole district drains toward planted basins rather than pipes.\n",
                            '4. **Mix uses on every block.**',
                            '5. **Keep what works.**',
                        ],
                        [
                            '## Four principles',
                            '',
                            '3. **Mix uses on every block.**',
                            '4. **Keep what works.**',
                        ],
                        $b,
                    ),
                ],

                // The Agent tightening prose: three paragraphs, a handful of words each.
                [
                    'by' => 'agent',
                    'undo' => fn (string $b): string => str_replace(
                        [
                            'its working waterfront',
                            'a mixed, resilient neighbourhood',
                            'every hour of the day, not a showpiece that empties at dusk.',
                            'ground floor lively and let the district change one building at a time.',
                            'weather honestly, planting should look a little wild, and the lighting',
                            'warm and low rather than corporate and even.',
                        ],
                        [
                            'its waterfront',
                            'a mixed neighbourhood',
                            'every hour of the day.',
                            'ground floor lively.',
                            'weather honestly and the lighting',
                            'warm and low.',
                        ],
                        $b,
                    ),
                ],

                // A whole closing paragraph appended.
                [
                    'by' => 'creator',
                    'undo' => fn (string $b): string => rtrim(preg_replace(
                        '/\n+We are explicitly avoiding a single architectural language\..*$/s',
                        '',
                        $b,
                    ) ?? $b)."\n",
                ],

                // The smallest change a compare view still has to make findable.
                [
                    'by' => 'agent',
                    'undo' => fn (string $b): string => str_replace(
                        'reads as a single project by a single hand',
                        'reads as one project by one hand',
                        $b,
                    ),
                ],
            ],

            'Public Realm & Waterfront' => [
                // Two numbers and a planting list, far apart in a long document.
                [
                    'by' => 'creator',
                    'undo' => fn (string $b): string => str_replace(
                        [
                            'the full 1.4 kilometres',
                            'broad timber terraces',
                            'hackberry, hornbeam, and black pine',
                        ],
                        [
                            'the full 1.1 kilometres',
                            'narrow timber terraces',
                            'hackberry and hornbeam',
                        ],
                        $b,
                    ),
                ],

                // A section appended at the end, after a long untouched run.
                [
                    'by' => 'agent',
                    'undo' => fn (string $b): string => rtrim(preg_replace(
                        '/\n+## Open question\n.*$/s',
                        '',
                        $b,
                    ) ?? $b)."\n",
                ],
            ],
        ];
    }
}
