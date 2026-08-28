<?php

namespace App\Http\Controllers\Concerns;

use App\Support\IntelligentSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait FiltersIndexTables
{
    /** @var list<int> */
    public const TABLE_PER_PAGE_OPTIONS = [10, 25, 50, 100];

    protected function resolvePerPage(Request $request, int $default = 15): int
    {
        $perPage = (int) $request->input('per_page', $default);

        return in_array($perPage, self::TABLE_PER_PAGE_OPTIONS, true) ? $perPage : $default;
    }

    protected function paginateTable(Builder $query, Request $request, int $default = 15)
    {
        return $query->paginate($this->resolvePerPage($request, $default))->withQueryString();
    }

    protected function applyTableSearch(Builder $query, Request $request, array $columns, string $param = 'search'): Builder
    {
        $term = trim((string) $request->input($param, ''));
        if ($term === '') {
            return $query;
        }

        return IntelligentSearch::constrain($query, $columns, $term);
    }

    protected function applyTableDateRange(
        Builder $query,
        Request $request,
        string $column,
        string $fromParam = 'date_from',
        ?string $toParam = 'date_to',
    ): Builder {
        if ($request->filled($fromParam)) {
            $query->whereDate($column, '>=', $request->input($fromParam));
        }

        if ($toParam && $request->filled($toParam)) {
            $query->whereDate($column, '<=', $request->input($toParam));
        }

        return $query;
    }

    protected function applyTableFilter(Builder $query, Request $request, string $column, string $param): Builder
    {
        if ($request->filled($param)) {
            $query->where($column, $request->input($param));
        }

        return $query;
    }

    /**
     * Apply a whitelist-based column sort from the request.
     *
     * @param  array<string, string>  $allowed  Map of public sort keys to database columns
     */
    protected function applyTableSort(
        Builder $query,
        Request $request,
        array $allowed,
        string $defaultSort,
        string $defaultDirection = 'desc',
        string $sortParam = 'sort',
        string $directionParam = 'direction',
    ): Builder {
        [, $direction, $column] = $this->resolveTableSort(
            $request,
            $allowed,
            $defaultSort,
            $defaultDirection,
            $sortParam,
            $directionParam,
        );

        $relevanceSql = null;
        foreach ($query->getQuery()->orders ?? [] as $order) {
            if (is_array($order) && str_contains((string) ($order['sql'] ?? ''), 'intelligent_search_rank')) {
                $relevanceSql = $order['sql'];
                break;
            }
        }
        // reorder() clears order bindings; re-apply via orderByRaw so ? placeholders stay bound.
        $orderBindings = $relevanceSql !== null
            ? ($query->getQuery()->bindings['order'] ?? [])
            : [];

        $query->reorder();
        if ($relevanceSql !== null) {
            $query->orderByRaw($relevanceSql, $orderBindings);
        }

        if ($this->isDateSortColumn($column)) {
            $this->applyDateOnlyOrder($query, $column, $direction);
        } else {
            $query->orderBy($column, $direction);
        }

        $keyName = $query->getModel()->getQualifiedKeyName();
        if ($column !== $keyName && $column !== $query->getModel()->getKeyName()) {
            $query->orderBy($keyName, $direction);
        }

        return $query;
    }

    protected function isDateSortColumn(string $column): bool
    {
        $basename = str_contains($column, '.') ? substr($column, strrpos($column, '.') + 1) : $column;

        return (bool) preg_match('/(_date|_at)$/', $basename);
    }

    protected function applyDateOnlyOrder(Builder $query, string $column, string $direction): void
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';
        $qualified = str_contains($column, '.')
            ? $column
            : $query->getModel()->getTable().'.'.$column;
        $wrapped = $query->getGrammar()->wrap($qualified);
        $driver = $query->getConnection()->getDriverName();
        $dateExpr = in_array($driver, ['sqlite', 'pgsql'], true)
            ? "date({$wrapped})"
            : "DATE({$wrapped})";

        $query->orderByRaw("{$dateExpr} is null, {$dateExpr} {$direction}");
    }

    /**
     * @param  array<string, string>  $allowed
     * @return array{0: string, 1: string, 2: string} Sort key, direction, database column
     */
    protected function resolveTableSort(
        Request $request,
        array $allowed,
        string $defaultSort,
        string $defaultDirection = 'desc',
        string $sortParam = 'sort',
        string $directionParam = 'direction',
    ): array {
        $sort = (string) $request->input($sortParam, $defaultSort);
        if (! array_key_exists($sort, $allowed)) {
            $sort = array_key_exists($defaultSort, $allowed)
                ? $defaultSort
                : array_key_first($allowed);
        }

        $direction = strtolower((string) $request->input($directionParam, $defaultDirection));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = in_array($defaultDirection, ['asc', 'desc'], true) ? $defaultDirection : 'desc';
        }

        return [$sort, $direction, $allowed[$sort]];
    }
}
