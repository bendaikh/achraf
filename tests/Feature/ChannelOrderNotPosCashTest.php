<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\FinancialMovement;
use App\Models\PosSale;
use App\Models\User;
use App\Support\OrderSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelOrderNotPosCashTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_history_excludes_channel_orders(): void
    {
        $user = User::factory()->create();

        $pos = PosSale::create([
            'ticket_number' => 'POS-ONLY',
            'sold_at' => now(),
            'currency' => 'dh - MAD',
            'subtotal' => 50,
            'discount' => 0,
            'tax_total' => 10,
            'total' => 60,
            'payment_method' => 'cash',
            'status' => PosSale::STATUS_COMPLETED,
            'user_id' => $user->id,
        ]);

        $shopify = PosSale::create([
            'ticket_number' => 'SH-1',
            'sold_at' => now(),
            'currency' => 'dh - MAD',
            'subtotal' => 100,
            'discount' => 0,
            'tax_total' => 20,
            'total' => 120,
            'payment_method' => 'cash',
            'status' => PosSale::STATUS_COMPLETED,
            'source' => OrderSource::SHOPIFY,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('pos.sales.index'));
        $response->assertOk();
        $response->assertSee('POS-ONLY');
        $response->assertDontSee('SH-1');
        $this->assertTrue($pos->isTruePos());
        $this->assertTrue($shopify->isChannelOrder());
    }

    public function test_expense_with_invoice_stays_pending_without_cash_movement(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('expenses-with-invoice.store'), [
            'designation' => 'Achat matériel',
            'expense_date' => '2026-09-18',
            'amount' => 1500,
            'currency' => 'dh - MAD',
            'tax_type' => 'NO TAXE',
        ])->assertRedirect(route('expenses-with-invoice.index'));

        $expense = Expense::query()->where('designation', 'Achat matériel')->first();
        $this->assertNotNull($expense);
        $this->assertSame(Expense::PAYMENT_PENDING, $expense->payment_status);
        $this->assertDatabaseMissing('financial_movements', [
            'source_type' => Expense::class,
            'source_id' => $expense->id,
        ]);
        $this->assertSame(0, FinancialMovement::query()->count());
    }

    public function test_login_page_hides_default_credentials(): void
    {
        $response = $this->get(route('login'));
        $response->assertOk();
        $response->assertDontSee('superadmin@achraf.com');
        $response->assertDontSee('Identifiants par défaut');
        $response->assertDontSee('/ <strong>password</strong>', false);
    }
}
