<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * For models that declare their sortable columns in a SORTABLE constant (list<string>)
 * and the default in DEFAULT_SORT; a missing constant fails at runtime, not in PHPStan.
 * A sort value is a column name, prefixed with "-" for descending order.
 */
trait Sortable
{
    /**
     * Every accepted sort value, for validation.
     *
     * @return list<string>
     */
    public static function sortValues(): array
    {
        return [...static::SORTABLE, ...array_map(fn (string $column): string => '-'.$column, static::SORTABLE)];
    }

    /**
     * Ties are broken by id in the same direction, so pagination never repeats or skips a row.
     *
     * @param  Builder<static>  $query
     */
    public function scopeSorted(Builder $query, string $sort): void
    {
        // The column ends up in SQL, so anything outside the whitelist is refused even if validation was skipped.
        if (! in_array($sort, static::sortValues(), true)) {
            throw new InvalidArgumentException("Unsupported sort [{$sort}].");
        }

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        $query
            ->orderBy($query->qualifyColumn(ltrim($sort, '-')), $direction)
            ->orderBy($query->qualifyColumn('id'), $direction);
    }
}
