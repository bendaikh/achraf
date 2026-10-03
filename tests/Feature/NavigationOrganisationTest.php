<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Organisation du menu : Gestion ventes / Gestion financière clients / CRM-Équipe commerciale
 * et regroupement des onglets Gestion achats (Dépenses · Achats / Fournisseurs · Finance).
 */
class NavigationOrganisationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_finance_and_crm_modules_are_separated(): void
    {
        $user = User::factory()->create();
        $modules = collect(Navigation::modules($user))->keyBy('key');

        $this->assertSame(
            ['Commandes', 'Préparation / Picking', 'Retours clients / Scan', 'Devis', 'Bons de commande', 'Bons de livraison', 'Factures clients', 'Avoirs clients'],
            collect($modules['sales']['children'])->pluck('label')->all()
        );
        $this->assertSame(
            ['Gestion des paiements clients', 'Encaissements', 'Impayés / reste à payer', 'Rapprochements / trésorerie'],
            collect($modules['sales-finance']['children'])->pluck('label')->all()
        );
        $this->assertContains('Commissions', collect($modules['crm']['children'])->pluck('label')->all());
        $this->assertContains('Mon tableau commercial', collect($modules['crm']['children'])->pluck('label')->all());
        $this->assertNotContains('Gestion Paiement', collect($modules['sales']['children'])->pluck('label')->all());
        $this->assertArrayHasKey('pos', $modules->all());
    }

    public function test_purchase_tabs_are_grouped(): void
    {
        $user = User::factory()->create();
        $purchases = collect(Navigation::modules($user))->firstWhere('key', 'purchases');
        $groups = collect($purchases['children'])->groupBy('group')->map(fn ($g) => $g->pluck('label')->all())->all();

        $this->assertSame(['Dépense avec facture', 'Dépense sans facture'], $groups['Dépenses']);
        $this->assertSame(['Besoins d\'achat', 'BC fournisseur', 'Bon de livraison fournisseur', 'Bon de réception', 'Factures fournisseur', 'Avoirs fournisseur'], $groups['Achats / Fournisseurs']);
        $this->assertSame(['Gestion paiement', 'Export documents comptables'], $groups['Finance']);

        $this->actingAs($user)->get(route('receptions.index'))
            ->assertOk()
            ->assertSee('data-tab-group="Achats / Fournisseurs"', false)
            ->assertSee('data-tab-group="Finance"', false);
    }

    public function test_payment_pages_resolve_to_finance_module_and_invoices_stay_in_sales(): void
    {
        $user = User::factory()->create();
        $client = Client::create(['name' => 'Client nav']);
        $invoice = Invoice::create([
            'invoice_number' => 'FA-NAV-1', 'client_id' => $client->id, 'invoice_date' => now()->subDays(40),
            'due_date' => now()->subDays(10), 'currency' => 'dh - MAD', 'subtotal' => 100, 'discount' => 0,
            'adjustment' => 0, 'total' => 120, 'payment_status' => 'unpaid',
        ]);

        $this->assertSame('sales-finance', $this->moduleKeyFor($user, route('sales.payments.index')));
        $this->assertSame('sales-finance', $this->moduleKeyFor($user, route('invoices.payments.index', $invoice)));
        $this->assertSame('sales', $this->moduleKeyFor($user, route('invoices.show', $invoice)));
        $this->assertSame('sales', $this->moduleKeyFor($user, route('orders.index')));
        $this->assertSame('crm', $this->moduleKeyFor($user, route('access.commissions.index')));

        InvoicePayment::create([
            'invoice_id' => $invoice->id, 'payment_date' => now()->subDays(5), 'amount' => 20,
            'payment_method' => 'Espèces', 'source' => 'manual',
        ]);

        $this->actingAs($user)->get(route('sales-finance.receipts'))->assertOk()->assertSee('FA-NAV-1')->assertSee('20.00');
        $this->actingAs($user)->get(route('sales-finance.unpaid'))->assertOk()->assertSee('FA-NAV-1')->assertSee('100.00');
        $this->actingAs($user)->get(route('sales-finance.reconciliation', ['date_from' => now()->subMonth()->toDateString()]))
            ->assertOk()->assertSee('FA-NAV-1');
        $this->actingAs($user)->get('/finance-clients')->assertRedirect(route('sales.payments.index'));
    }

    private function moduleKeyFor(User $user, string $url): ?string
    {
        $this->actingAs($user);
        $request = Request::create($url);
        $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));

        return Navigation::activeModule(Navigation::modules($user), $request)['key'] ?? null;
    }
}
