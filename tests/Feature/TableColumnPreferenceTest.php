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
}
