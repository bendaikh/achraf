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
}
