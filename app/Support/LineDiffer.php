<?php

namespace App\Support;

/**
 * A pure line-level diff between two strings, used to show how one Revision differs
 * from another (#15) and to describe a human edit in the agent Feedback digest (#17).
 * Classic longest-common-subsequence over lines; no schema or model coupling.
 */
class LineDiffer
{
    /**
     * @return list<array{type: 'added'|'removed'|'unchanged', value: string}>
     */
    public function diff(string $old, string $new): array
    {
        $a = $old === '' ? [] : explode("\n", $old);
        $b = $new === '' ? [] : explode("\n", $new);

        $lcs = $this->lcsTable($a, $b);

        $rows = [];
        $this->walk($lcs, $a, $b, count($a), count($b), $rows);

        return array_reverse($rows);
    }

    /**
     * A unified-style textual diff (+/- prefixed lines) for the digest.
     */
    public function unified(string $old, string $new): string
    {
        $lines = array_map(function (array $row): string {
            return match ($row['type']) {
                'added' => '+'.$row['value'],
                'removed' => '-'.$row['value'],
                default => ' '.$row['value'],
            };
        }, $this->diff($old, $new));

        return implode("\n", $lines);
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return array<int, array<int, int>>
     */
    private function lcsTable(array $a, array $b): array
    {
        $m = count($a);
        $n = count($b);
        $table = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));

        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                $table[$i][$j] = $a[$i - 1] === $b[$j - 1]
                    ? $table[$i - 1][$j - 1] + 1
                    : max($table[$i - 1][$j], $table[$i][$j - 1]);
            }
        }

        return $table;
    }

    /**
     * @param  array<int, array<int, int>>  $lcs
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @param  list<array{type: 'added'|'removed'|'unchanged', value: string}>  $rows
     */
    private function walk(array $lcs, array $a, array $b, int $i, int $j, array &$rows): void
    {
        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $a[$i - 1] === $b[$j - 1]) {
                $rows[] = ['type' => 'unchanged', 'value' => $a[$i - 1]];
                $i--;
                $j--;
            } elseif ($j > 0 && ($i === 0 || $lcs[$i][$j - 1] >= $lcs[$i - 1][$j])) {
                $rows[] = ['type' => 'added', 'value' => $b[$j - 1]];
                $j--;
            } elseif ($i > 0) {
                $rows[] = ['type' => 'removed', 'value' => $a[$i - 1]];
                $i--;
            } else {
                break;
            }
        }
    }
}
