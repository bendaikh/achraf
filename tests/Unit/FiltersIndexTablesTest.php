<?php

namespace Tests\Unit;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use Illuminate\Http\Request;
use Tests\TestCase;

class FiltersIndexTablesTest extends TestCase
{
    public function test_resolve_per_page_returns_allowed_value(): void
    {
        $resolver = new class
        {
            use FiltersIndexTables;

            public function resolve(Request $request, int $default = 15): int
            {
                return $this->resolvePerPage($request, $default);
            }
        };

        $request = Request::create('/', 'GET', ['per_page' => '50']);

        $this->assertSame(50, $resolver->resolve($request));
    }

    public function test_resolve_per_page_falls_back_to_default_for_invalid_value(): void
    {
        $resolver = new class
        {
            use FiltersIndexTables;

            public function resolve(Request $request, int $default = 15): int
            {
                return $this->resolvePerPage($request, $default);
            }
        };

        $request = Request::create('/', 'GET', ['per_page' => '999']);

        $this->assertSame(15, $resolver->resolve($request));
        $this->assertSame(20, $resolver->resolve($request, 20));
    }

    public function test_resolve_table_sort_uses_request_values_when_allowed(): void
    {
        $resolver = $this->makeSortResolver();

        $request = Request::create('/', 'GET', [
            'sort' => 'reception_date',
            'direction' => 'asc',
        ]);

        $this->assertSame(
            ['reception_date', 'asc', 'reception_date'],
            $resolver->resolve($request, ['reception_date' => 'reception_date'], 'reception_date', 'desc')
        );
    }

    public function test_resolve_table_sort_falls_back_for_invalid_sort_and_direction(): void
    {
        $resolver = $this->makeSortResolver();

        $request = Request::create('/', 'GET', [
            'sort' => 'hacked_column',
            'direction' => 'sideways',
        ]);

        $this->assertSame(
            ['reception_date', 'desc', 'reception_date'],
            $resolver->resolve($request, [
                'reception_date' => 'reception_date',
                'total' => 'total',
            ], 'reception_date', 'desc')
        );
    }

    public function test_resolve_table_sort_uses_defaults_when_params_missing(): void
    {
        $resolver = $this->makeSortResolver();

        $request = Request::create('/', 'GET');

        $this->assertSame(
            ['total', 'asc', 'total'],
            $resolver->resolve($request, [
                'reception_date' => 'reception_date',
                'total' => 'total',
            ], 'total', 'asc')
        );
    }

    private function makeSortResolver(): object
    {
        return new class
        {
            use FiltersIndexTables;

            public function resolve(
                Request $request,
                array $allowed,
                string $defaultSort,
                string $defaultDirection = 'desc',
            ): array {
                return $this->resolveTableSort($request, $allowed, $defaultSort, $defaultDirection);
            }
        };
    }
}
