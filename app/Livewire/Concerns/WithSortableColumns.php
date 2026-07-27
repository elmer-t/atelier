<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Url;

/**
 * The sortable-table half of an admin index: `?sortBy=`/`?sortDirection=` state, a
 * header click that flips direction, and — the part that earns the trait — an
 * allow-list standing between the query string and the `order by` clause. A
 * hand-edited column name that is not declared falls back to the default rather
 * than reaching SQL.
 *
 * Implementers declare their columns in {@see self::sortableColumns()} and name the
 * `#[Computed]` result to forget in {@see self::sortableResultProperty()}, so a new
 * sort re-runs the query rather than re-rendering a stale page of rows.
 */
trait WithSortableColumns
{
    #[Url(except: 'created_at')]
    public string $sortBy = 'created_at';

    #[Url(except: 'desc')]
    public string $sortDirection = 'desc';

    /**
     * The columns this table may be sorted by, mapped to the direction a fresh
     * click starts at — names read best ascending, counts and dates newest-first.
     *
     * @return array<string, string>
     */
    abstract protected function sortableColumns(): array;

    /**
     * The name of the `#[Computed]` property holding the sorted rows.
     */
    abstract protected function sortableResultProperty(): string;

    /**
     * Sort by the given column, flipping the direction when it is already the
     * active one. Unknown columns are ignored.
     */
    public function sort(string $column): void
    {
        $columns = $this->sortableColumns();

        if (! array_key_exists($column, $columns)) {
            return;
        }

        $this->sortDirection = $this->sortBy === $column
            ? ($this->sortedDirection() === 'asc' ? 'desc' : 'asc')
            : $columns[$column];

        $this->sortBy = $column;

        $this->resetPage();
        $this->forgetSortedResult();
    }

    /**
     * The active sort column, falling back to the default when the query string
     * names something unsortable. The table headers read this rather than the
     * raw property so the highlighted column always matches the `order by`.
     */
    public function sortedColumn(): string
    {
        return array_key_exists($this->sortBy, $this->sortableColumns()) ? $this->sortBy : 'created_at';
    }

    /**
     * @return 'asc'|'desc'
     */
    public function sortedDirection(): string
    {
        return $this->sortDirection === 'asc' ? 'asc' : 'desc';
    }

    protected function forgetSortedResult(): void
    {
        unset($this->{$this->sortableResultProperty()});
    }
}
