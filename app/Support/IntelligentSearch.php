<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class IntelligentSearch
{
    public const PRODUCT_COLUMNS = [
        'name',
        'ref',
        'barcode',
        'description',
        'variants.sku',
        'variants.barcode',
        'variants.title',
    ];

    /** @var list<string> */
    private const IDENTITY_FIELDS = [
        'ref',
        'sku',
        'barcode',
        'invoice_number',
        'ticket_number',
        'quote_number',
        'credit_note_number',
        'delivery_number',
        'reception_number',
        'order_number',
        'tracking_number',
        'code',
        'matricule',
        'external_id',
        'reference',
        'ice',
        'cin',
        'rc',
    ];

    /**
     * @param  list<string>  $columns  Local columns or relation paths (`client.name`, `posSale.fulfillments.tracking_number`)
     */
    public static function constrain(Builder $query, array $columns, string $term, bool $rank = true): Builder
    {
        $term = trim($term);
        if ($term === '' || $columns === []) {
            return $query;
        }

        $tokens = self::tokens($term);
        if ($tokens === []) {
            return $query;
        }

        $query->where(function (Builder $outer) use ($columns, $tokens) {
            foreach ($tokens as $token) {
                $outer->where(function (Builder $group) use ($columns, $token) {
                    foreach ($columns as $column) {
                        self::orMatchColumn($group, $column, $token);
                    }
                });
            }
        });

        if ($rank) {
            self::orderByRelevance($query, $columns, $term);
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    public static function tokens(string $term): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $parts = preg_split('/[^\p{L}\p{N}]+/u', $term) ?: [];
        $tokens = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $tokens[] = $part;
        }

        $tokens = array_values(array_unique($tokens));

        return array_slice($tokens, 0, 8);
    }

    public static function compact(string $value): string
    {
        $compact = preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($value)) ?? '';

        return $compact;
    }

    private static function orMatchColumn(Builder $group, string $column, string $token): void
    {
        if (str_contains($column, '.')) {
            $relation = substr($column, 0, (int) strrpos($column, '.'));
            $field = substr($column, ((int) strrpos($column, '.')) + 1);
            $group->orWhereHas($relation, function (Builder $relationQuery) use ($field, $token) {
                self::whereColumnMatches($relationQuery, $field, $token);
            });

            return;
        }

        $group->orWhere(function (Builder $inner) use ($column, $token) {
            self::whereColumnMatches($inner, $column, $token);
        });
    }

    private static function whereColumnMatches(Builder $query, string $column, string $token): void
    {
        $qualified = self::qualify($query, $column);
        $wrapped = $query->getGrammar()->wrap($qualified);
        $like = '%'.self::escapeLike($token).'%';
        $compactToken = self::compact($token);
        $compactLike = '%'.self::escapeLike($compactToken).'%';

        $query->where(function (Builder $q) use ($qualified, $wrapped, $like, $compactLike) {
            $q->where($qualified, 'like', $like)
                ->orWhereRaw(self::compactSql($wrapped).' LIKE ?', [$compactLike]);
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private static function orderByRelevance(Builder $query, array $columns, string $term): void
    {
        $local = array_values(array_filter($columns, fn (string $column) => ! str_contains($column, '.')));
        if ($local === []) {
            return;
        }

        $cases = [];
        $bindings = [];
        $lower = mb_strtolower($term);
        $compact = self::compact($term);
        $likePrefix = self::escapeLike($lower).'%';
        $likeContains = '%'.self::escapeLike($lower).'%';
        $compactPrefix = self::escapeLike($compact).'%';

        foreach ($local as $column) {
            $qualified = self::qualify($query, $column);
            $wrapped = $query->getGrammar()->wrap($qualified);
            $compactExpr = self::compactSql($wrapped);
            $containsScore = self::isIdentityField($column) ? 3 : 5;
            $cases[] = "CASE WHEN LOWER({$wrapped}) = ? THEN 0 WHEN LOWER({$wrapped}) LIKE ? THEN 1 WHEN {$compactExpr} = ? THEN 2 WHEN {$compactExpr} LIKE ? THEN 3 WHEN LOWER({$wrapped}) LIKE ? THEN {$containsScore} ELSE 80 END";
            $bindings[] = $lower;
            $bindings[] = $likePrefix;
            $bindings[] = $compact;
            $bindings[] = $compactPrefix;
            $bindings[] = $likeContains;
        }

        // MySQL LEAST() requires ≥2 args; skip the wrapper for a single local column.
        if (count($cases) === 1) {
            $sql = '/* intelligent_search_rank */ '.$cases[0];
        } else {
            $fn = in_array($query->getConnection()->getDriverName(), ['sqlite'], true) ? 'MIN' : 'LEAST';
            $sql = '/* intelligent_search_rank */ '.$fn.'('.implode(', ', $cases).')';
        }
        $query->orderByRaw($sql, $bindings);
    }

    private static function qualify(Builder $query, string $column): string
    {
        if (str_contains($column, '.')) {
            return $column;
        }

        return $query->getModel()->qualifyColumn($column);
    }

    private static function compactSql(string $wrappedColumn): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER({$wrappedColumn}), '-', ''), '/', ''), ' ', ''), '.', ''), '_', '')";
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private static function isIdentityField(string $column): bool
    {
        $basename = str_contains($column, '.') ? substr($column, (int) strrpos($column, '.') + 1) : $column;

        return in_array($basename, self::IDENTITY_FIELDS, true);
    }
}
