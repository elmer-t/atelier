<?php

namespace App\Support;

/**
 * A line diff between two Revisions (#15), grouped into the sections a reader
 * actually needs: every changed run with a few lines of context either side, and
 * the untouched runs between them kept separate so the view can collapse them.
 *
 * Sits on top of {@see LineDiffer}, which stays free of presentation concerns
 * because the agent Feedback digest reads it too (#17).
 *
 * @phpstan-type LineType 'added'|'removed'|'unchanged'
 * @phpstan-type DiffRow array{type: LineType, value: string, old: int|null, new: int|null}
 * @phpstan-type DiffSection array{changed: bool, header: string|null, rows: list<DiffRow>}
 */
class RevisionDiff
{
    /** Unchanged lines kept either side of a change, so it reads in context. */
    public const CONTEXT_LINES = 3;

    public function __construct(private LineDiffer $lines) {}

    /**
     * The whole comparison in one pass: the diff as alternating unchanged and
     * changed sections in document order, plus the line counts a Creator reads
     * before deciding whether to open it at all.
     *
     * A changed section carries a `@@ -old +new @@` header naming the lines it
     * covers; an unchanged section is the material between two changes, which the
     * view offers rather than shows.
     *
     * One call rather than two, because the underlying longest-common-subsequence
     * is quadratic in the number of lines and both answers come off the same walk.
     *
     * @return array{sections: list<DiffSection>, added: int, removed: int}
     */
    public function compare(string $old, string $new, int $context = self::CONTEXT_LINES): array
    {
        $rows = $this->rows($old, $new);
        $types = array_column($rows, 'type');

        return [
            'sections' => $this->sections($rows, $context),
            'added' => count(array_keys($types, 'added', true)),
            'removed' => count(array_keys($types, 'removed', true)),
        ];
    }

    /**
     * @param  list<DiffRow>  $rows
     * @return list<DiffSection>
     */
    private function sections(array $rows, int $context): array
    {
        /** @var array<int, true> $keep Indices within `$context` lines of a change. */
        $keep = [];

        foreach ($rows as $index => $row) {
            if ($row['type'] === 'unchanged') {
                continue;
            }

            $first = max(0, $index - $context);
            $last = min(count($rows) - 1, $index + $context);

            for ($near = $first; $near <= $last; $near++) {
                $keep[$near] = true;
            }
        }

        $sections = [];
        $current = null;

        foreach ($rows as $index => $row) {
            $changed = isset($keep[$index]);

            if ($current === null || $current['changed'] !== $changed) {
                if ($current !== null) {
                    $sections[] = $this->close($current);
                }

                $current = ['changed' => $changed, 'rows' => []];
            }

            $current['rows'][] = $row;
        }

        if ($current !== null) {
            $sections[] = $this->close($current);
        }

        return $sections;
    }

    /**
     * Diff rows numbered on the side they exist on, with each changed run ordered
     * removals-first so a rewritten line reads as "was, now".
     *
     * @return list<DiffRow>
     */
    private function rows(string $old, string $new): array
    {
        $rows = [];
        $oldNumber = 0;
        $newNumber = 0;

        foreach ($this->removalsFirst($this->lines->diff($old, $new)) as $row) {
            $rows[] = [
                'type' => $row['type'],
                'value' => $row['value'],
                'old' => $row['type'] === 'added' ? null : ++$oldNumber,
                'new' => $row['type'] === 'removed' ? null : ++$newNumber,
            ];
        }

        return $rows;
    }

    /**
     * Reorder every changed run so removals lead. LineDiffer backtracks from the
     * end of both documents, so it can emit an addition before the removal it
     * replaced — which reads backwards in a unified view.
     *
     * @param  list<array{type: LineType, value: string}>  $rows
     * @return list<array{type: LineType, value: string}>
     */
    private function removalsFirst(array $rows): array
    {
        // Indices rather than rows, so the reordering cannot blur the row shape.
        $order = [];
        $run = [];

        $reordered = function (array $run) use ($rows): array {
            $ofType = fn (string $type): array => array_values(array_filter(
                $run,
                fn (int $index): bool => $rows[$index]['type'] === $type,
            ));

            return [...$ofType('removed'), ...$ofType('added')];
        };

        foreach ($rows as $index => $row) {
            if ($row['type'] !== 'unchanged') {
                $run[] = $index;

                continue;
            }

            $order = [...$order, ...$reordered($run), $index];
            $run = [];
        }

        $order = [...$order, ...$reordered($run)];

        return array_map(fn (int $index): array => $rows[$index], $order);
    }

    /**
     * @param  array{changed: bool, rows: list<DiffRow>}  $section
     * @return DiffSection
     */
    private function close(array $section): array
    {
        return [
            'changed' => $section['changed'],
            'header' => $section['changed'] ? $this->header($section['rows']) : null,
            'rows' => $section['rows'],
        ];
    }

    /**
     * The unified-diff position marker for a changed section, naming where it sits
     * in each Revision: `@@ -<first old line>,<count> +<first new line>,<count> @@`.
     *
     * @param  list<DiffRow>  $rows
     */
    private function header(array $rows): string
    {
        return '@@ -'.$this->range(array_column($rows, 'old'))
            .' +'.$this->range(array_column($rows, 'new')).' @@';
    }

    /**
     * `<first line>,<count>` for one side of a section, skipping the lines that do
     * not exist on that side.
     *
     * @param  list<int|null>  $numbers
     */
    private function range(array $numbers): string
    {
        $present = array_values(array_filter($numbers, fn (?int $number): bool => $number !== null));

        return $present === [] ? '0,0' : min($present).','.count($present);
    }
}
