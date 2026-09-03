<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserTablePreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableColumnPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_and_reset_table_column_preferences(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson(route('table-columns.update', 'products'), [
            'viewport' => 'desktop',
            'config' => [
                'order' => ['select', 'ref', 'nom', 'statut', 'actions'],
                'visible' => [
                    'select' => true,
                    'ref' => true,
                    'nom' => true,
                    'statut' => true,
                    'actions' => true,
                    'source' => false,
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('config.visible.source', false);

        $this->assertDatabaseHas('user_table_preferences', [
            'user_id' => $user->id,
            'table_key' => 'products',
            'viewport' => 'desktop',
        ]);

        $otherUser = User::factory()->create();
        $otherPrefs = $this->actingAs($otherUser)->getJson(route('table-columns.show', 'products'));
        $otherPrefs->assertOk();
        $this->assertNotSame(
            false,
            $otherPrefs->json('desktop.visible.source'),
            'Other users must not inherit saved preferences.'
        );

        $this->actingAs($user)->postJson(route('table-columns.reset', 'products'), [
            'viewport' => 'desktop',
        ])->assertOk();

        $this->assertDatabaseMissing('user_table_preferences', [
            'user_id' => $user->id,
            'table_key' => 'products',
            'viewport' => 'desktop',
        ]);
    }

    public function test_locked_columns_stay_visible_when_saved(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson(route('table-columns.update', 'products'), [
            'viewport' => 'desktop',
            'config' => [
                'visible' => [
                    'ref' => false,
                    'actions' => false,
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('config.visible.ref', true);
        $response->assertJsonPath('config.visible.actions', true);
    }

    public function test_actions_column_stays_at_the_end_when_new_columns_are_merged(): void
    {
        $user = User::factory()->create();

        UserTablePreference::create([
            'user_id' => $user->id,
            'table_key' => 'expenses-with-invoice',
            'viewport' => 'desktop',
            'config' => [
                'order' => ['select', 'reference', 'date', 'total', 'statut', 'actions'],
                'visible' => [],
                'widths' => [],
            ],
        ]);

        $order = $this->actingAs($user)
            ->getJson(route('table-columns.show', 'expenses-with-invoice'))
            ->assertOk()
            ->json('desktop.order');

        $this->assertSame('select', $order[0]);
        $this->assertSame('actions', $order[array_key_last($order)]);
        $this->assertContains('designation', $order);
        $this->assertContains('categorie', $order);
        $this->assertLessThan(
            array_search('actions', $order, true),
            array_search('designation', $order, true)
        );
    }
}
