<?php

namespace Tests\Unit;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Models\Invoice;
use App\Models\Product;
use App\Support\IntelligentSearch;
use Illuminate\Http\Request;
use Tests\TestCase;

class IntelligentSearchTest extends TestCase
{
    public function test_tokens_split_on_punctuation_and_spaces(): void
    {
        $this->assertSame(['FA', '4058'], IntelligentSearch::tokens('FA 4058'));
        $this->assertSame(['Santa', '2024'], IntelligentSearch::tokens('Santa 2024'));
        $this->assertSame(['FA', '2026', '004058'], IntelligentSearch::tokens('FA-2026/004058'));
    }

    public function test_compact_strips_separators(): void
    {
        $this->assertSame('fa2026004058', IntelligentSearch::compact('FA-2026/004058'));
        $this->assertSame('fasttap', IntelligentSearch::compact('FAST-TAP'));
    }

    public function test_constrain_requires_every_token_and_matches_compact_numbers(): void
    {
        $query = Invoice::query();
        IntelligentSearch::constrain($query, ['invoice_number', 'client.name'], 'FA 4058');

        $sql = strtolower($query->toSql());

        $this->assertStringContainsString('invoice_number', $sql);
        $this->assertStringContainsString('replace(', $sql);
        $this->assertGreaterThanOrEqual(2, substr_count($sql, 'exists'));
        $bindings = collect($query->getBindings())->map(fn ($value) => (string) $value);

        $this->assertTrue($bindings->contains(fn (string $value) => str_contains(strtolower($value), 'fa')));
        $this->assertTrue($bindings->contains(fn (string $value) => str_contains($value, '4058')));
    }

    public function test_product_search_includes_variants_and_relevance_rank(): void
    {
        $query = Product::query();
        IntelligentSearch::constrain($query, IntelligentSearch::PRODUCT_COLUMNS, 'proline');

        $sql = strtolower($query->toSql());

        $this->assertStringContainsString('intelligent_search_rank', $sql);
        $this->assertStringContainsString('min(', $sql);
        $this->assertStringContainsString('variants', $sql);
        $this->assertStringContainsString('sku', $sql);
    }

    public function test_single_local_column_rank_does_not_wrap_least_or_min(): void
    {
        $query = Invoice::query();
        IntelligentSearch::constrain($query, ['invoice_number', 'client.name'], 'a');

        $sql = strtolower($query->toSql());

        $this->assertStringContainsString('intelligent_search_rank', $sql);
        $this->assertStringNotContainsString('least(', $sql);
        $this->assertStringNotContainsString('min(', $sql);
        $this->assertSame(substr_count($query->toSql(), '?'), count($query->getBindings()));
    }

    public function test_apply_table_search_keeps_relevance_ahead_of_date_sort(): void
    {
        $resolver = new class
        {
            use FiltersIndexTables;

            public function searchAndSort($query, Request $request, array $columns, array $allowed, string $default): void
            {
                $this->applyTableSearch($query, $request, $columns);
                $this->applyTableSort($query, $request, $allowed, $default, 'desc');
            }
        };

        $query = Invoice::query();
        $resolver->searchAndSort(
            $query,
            Request::create('/', 'GET', ['search' => '4058']),
            ['invoice_number', 'client.name'],
            ['invoice_date' => 'invoice_date'],
            'invoice_date',
        );

        $sql = strtolower($query->toSql());
        $rankPos = strpos($sql, 'intelligent_search_rank');
        $datePos = strpos($sql, 'invoice_date');

        $this->assertNotFalse($rankPos);
        $this->assertNotFalse($datePos);
        $this->assertLessThan($datePos, $rankPos);

        $placeholderCount = substr_count($query->toSql(), '?');
        $this->assertSame($placeholderCount, count($query->getBindings()));
    }
}
