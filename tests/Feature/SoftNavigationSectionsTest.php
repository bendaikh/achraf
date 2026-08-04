<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SoftNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoftNavigationSectionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    public static function sectionRoutes(): array
    {
        return [
            'financial.index',
            'clients.index',
            'suppliers.index',
            'products.index',
            'stock.enligne.index',
            'stock.magasin.index',
            'orders.index',
            'quotes.index',
            'invoices.index',
            'settings.index',
            'expenses-with-invoice.index',
            'pos.sales.index',
        ];
    }

    public function test_soft_nav_returns_json_shell_for_major_sections(): void
    {
        $user = User::factory()->create();

        foreach (self::sectionRoutes() as $routeName) {
            $response = $this->actingAs($user)->get(route($routeName), [
                SoftNavigation::HEADER => '1',
                'Accept' => 'application/json',
            ]);

            $response->assertOk();
            $response->assertJsonStructure([
                'title',
                'page_title',
                'url',
                'html',
                'module',
                'tabs_html',
                'assets',
            ]);

            $this->assertStringNotContainsString(
                'app-shell-aside',
                (string) $response->json('html'),
                "Soft-nav HTML for {$routeName} should not include the sidebar shell."
            );
            $this->assertNotSame('', trim((string) $response->json('html')));
        }
    }

    public function test_dashboard_soft_nav_still_returns_dedicated_payload(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'), [
            SoftNavigation::HEADER => '1',
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('module', 'dashboard');
        $this->assertStringContainsString('dashboardPage', (string) $response->json('html'));
    }

    public function test_pos_soft_nav_preserves_alpine_event_attributes_and_scripts(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('pos.index'), [
            SoftNavigation::HEADER => '1',
            'Accept' => 'application/json',
        ]);

        $response->assertOk();

        $html = (string) $response->json('html');

        $this->assertStringContainsString('@click', $html);
        $this->assertStringContainsString('x-data="posRegister', $html);
        $this->assertStringContainsString('window.posRegister', $html);
        $this->assertStringNotContainsString('x-transition false', $html);
    }
}
