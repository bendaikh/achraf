<?php

namespace App\Http\Controllers\Concerns;

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
        if (! $request->filled($param)) {
            return $query;
        }

        $search = '%'.$request->input($param).'%';

        $query->where(function (Builder $q) use ($columns, $search) {
            foreach ($columns as $column) {
                if (str_contains($column, '.')) {
                    [$relation, $field] = explode('.', $column, 2);
                    $q->orWhereHas($relation, function (Builder $relationQuery) use ($field, $search) {
                        $relationQuery->where($field, 'like', $search);
                    });
                } else {
                    $q->orWhere($column, 'like', $search);
                }
            }
        });

        return $query;
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
}
