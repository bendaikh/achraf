<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('warehouses')) {
            Schema::create('warehouses', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->string('address')->nullable();
                $table->string('city')->nullable();
                $table->string('status')->default('active'); // active | inactive
                $table->boolean('is_primary')->default(false);
                $table->text('comment')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('warehouse_locations')) {
            Schema::create('warehouse_locations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
                $table->string('code');
                $table->string('name')->nullable();
                $table->string('zone')->nullable();
                $table->string('status')->default('active'); // active | inactive
                $table->timestamps();

                $table->unique(['warehouse_id', 'code']);
            });
        }

        // Upgrade legacy depots / stock_locations schema if present.
        if (Schema::hasTable('depots') && DB::table('warehouses')->count() === 0) {
            $depots = DB::table('depots')->orderBy('id')->get();
            foreach ($depots as $depot) {
                DB::table('warehouses')->insert([
                    'id' => $depot->id,
                    'name' => $depot->name,
                    'code' => $depot->code,
                    'address' => $depot->address,
                    'city' => $depot->city,
                    'status' => ((int) ($depot->is_active ?? 1) === 1) ? 'active' : 'inactive',
                    'is_primary' => (bool) ($depot->is_primary ?? false),
                    'comment' => $depot->comment,
                    'created_at' => $depot->created_at,
                    'updated_at' => $depot->updated_at,
                ]);
            }

            $maxId = (int) DB::table('warehouses')->max('id');
            if ($maxId > 0) {
                DB::statement('ALTER TABLE warehouses AUTO_INCREMENT = '.($maxId + 1));
            }
        }

        if (Schema::hasTable('stock_locations') && DB::table('warehouse_locations')->count() === 0) {
            $locations = DB::table('stock_locations')->orderBy('id')->get();
            foreach ($locations as $location) {
                DB::table('warehouse_locations')->insert([
                    'id' => $location->id,
                    'warehouse_id' => $location->depot_id,
                    'code' => $location->code,
                    'name' => $location->name,
                    'zone' => $location->zone,
                    'status' => ((int) ($location->is_active ?? 1) === 1) ? 'active' : 'inactive',
                    'created_at' => $location->created_at,
                    'updated_at' => $location->updated_at,
                ]);
            }

            $maxId = (int) DB::table('warehouse_locations')->max('id');
            if ($maxId > 0) {
                DB::statement('ALTER TABLE warehouse_locations AUTO_INCREMENT = '.($maxId + 1));
            }
        }

        if (! Schema::hasTable('product_stocks')) {
            Schema::create('product_stocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
                $table->foreignId('warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
                $table->integer('quantity')->default(0);
                $table->unsignedInteger('reserved')->default(0);
                $table->timestamps();

                $table->unique(['product_id', 'warehouse_id', 'warehouse_location_id'], 'product_stocks_unique_slot');
            });
        } elseif (Schema::hasColumn('product_stocks', 'depot_id')) {
            Schema::table('product_stocks', function (Blueprint $table) {
                $table->dropForeign(['depot_id']);
                $table->dropForeign(['stock_location_id']);
                $table->dropForeign(['product_id']);
            });

            // Drop legacy unique index if present (name varies by earlier migration).
            $this->dropIndexIfExists('product_stocks', 'product_stocks_unique_balance');
            $this->dropIndexIfExists('product_stocks', 'product_stocks_unique_slot');

            Schema::table('product_stocks', function (Blueprint $table) {
                $table->renameColumn('depot_id', 'warehouse_id');
                $table->renameColumn('stock_location_id', 'warehouse_location_id');
            });

            Schema::table('product_stocks', function (Blueprint $table) {
                $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
                $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
                $table->foreign('warehouse_location_id')->references('id')->on('warehouse_locations')->nullOnDelete();
                $table->unique(['product_id', 'warehouse_id', 'warehouse_location_id'], 'product_stocks_unique_slot');
            });
        }

        if (! Schema::hasTable('stock_movements')) {
            Schema::create('stock_movements', function (Blueprint $table) {
                $this->defineStockMovementsSchema($table);
            });
        } elseif (Schema::hasColumn('stock_movements', 'depot_id')) {
            // Legacy empty/old schema — recreate to match warehouse model.
            Schema::drop('stock_movements');
            Schema::create('stock_movements', function (Blueprint $table) {
                $this->defineStockMovementsSchema($table);
            });
        }

        if (Schema::hasColumn('products', 'depot_id') && ! Schema::hasColumn('products', 'warehouse_id')) {
            $this->dropForeignKeyIfExists('products', 'depot_id');
            $hasStockLocation = Schema::hasColumn('products', 'stock_location_id');
            if ($hasStockLocation) {
                $this->dropForeignKeyIfExists('products', 'stock_location_id');
            }

            Schema::table('products', function (Blueprint $table) use ($hasStockLocation) {
                $table->renameColumn('depot_id', 'warehouse_id');
                if ($hasStockLocation) {
                    $table->renameColumn('stock_location_id', 'warehouse_location_id');
                }
            });

            Schema::table('products', function (Blueprint $table) {
                $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
                if (Schema::hasColumn('products', 'warehouse_location_id')) {
                    $table->foreign('warehouse_location_id')->references('id')->on('warehouse_locations')->nullOnDelete();
                }
            });
        } else {
            Schema::table('products', function (Blueprint $table) {
                if (! Schema::hasColumn('products', 'warehouse_id')) {
                    $table->foreignId('warehouse_id')->nullable()->after('depot')->constrained('warehouses')->nullOnDelete();
                }
                if (! Schema::hasColumn('products', 'warehouse_location_id')) {
                    $table->foreignId('warehouse_location_id')->nullable()->after('warehouse_id')->constrained('warehouse_locations')->nullOnDelete();
                }
            });
        }

        // Seed default warehouse + migrate free-text depot/location values when no warehouse data yet.
        $now = now();
        if (DB::table('warehouses')->count() === 0) {
            $primaryId = DB::table('warehouses')->insertGetId([
                'name' => 'Dépôt principal',
                'code' => 'PRINCIPAL',
                'address' => null,
                'city' => null,
                'status' => 'active',
                'is_primary' => true,
                'comment' => 'Dépôt créé automatiquement',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $primaryId = DB::table('warehouses')->where('is_primary', true)->value('id')
                ?? DB::table('warehouses')->orderBy('id')->value('id');
        }

        $warehouseMap = ['principal' => $primaryId, 'dépôt principal' => $primaryId, 'depot principal' => $primaryId];
        foreach (DB::table('warehouses')->get(['id', 'name', 'code']) as $warehouse) {
            $warehouseMap[mb_strtolower(trim((string) $warehouse->name))] = $warehouse->id;
            $warehouseMap[mb_strtolower(trim((string) $warehouse->code))] = $warehouse->id;
        }

        $depots = DB::table('products')
            ->whereNotNull('depot')
            ->where('depot', '!=', '')
            ->distinct()
            ->pluck('depot');

        foreach ($depots as $depotName) {
            $key = mb_strtolower(trim((string) $depotName));
            if (isset($warehouseMap[$key])) {
                continue;
            }

            $codeBase = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '-', $key) ?: 'DEPOT');
            $code = substr($codeBase, 0, 30);
            $suffix = 1;
            while (DB::table('warehouses')->where('code', $code)->exists()) {
                $code = substr($codeBase, 0, 26).'-'.$suffix;
                $suffix++;
            }

            $id = DB::table('warehouses')->insertGetId([
                'name' => $depotName,
                'code' => $code,
                'address' => null,
                'city' => null,
                'status' => 'active',
                'is_primary' => false,
                'comment' => 'Migré depuis le champ libre dépôt produit',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $warehouseMap[$key] = $id;
        }

        $locationMap = []; // warehouse_id|code => id
        foreach (DB::table('warehouse_locations')->get(['id', 'warehouse_id', 'code']) as $location) {
            $locationMap[$location->warehouse_id.'|'.mb_strtolower(trim((string) $location->code))] = $location->id;
        }

        $products = DB::table('products')->select('id', 'depot', 'location', 'warehouse_id', 'warehouse_location_id', 'stock_quantity', 'stock_reserved', 'item_kind')->get();
        foreach ($products as $product) {
            $warehouseId = $product->warehouse_id ?: $primaryId;
            if (! $product->warehouse_id && $product->depot) {
                $key = mb_strtolower(trim((string) $product->depot));
                $warehouseId = $warehouseMap[$key] ?? $primaryId;
            }

            $locationId = $product->warehouse_location_id;
            if (! $locationId && $product->location) {
                $locCode = trim((string) $product->location);
                $mapKey = $warehouseId.'|'.mb_strtolower($locCode);
                if (! isset($locationMap[$mapKey])) {
                    $locationMap[$mapKey] = DB::table('warehouse_locations')->insertGetId([
                        'warehouse_id' => $warehouseId,
                        'code' => mb_substr($locCode, 0, 100),
                        'name' => $locCode,
                        'zone' => null,
                        'status' => 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
                $locationId = $locationMap[$mapKey];
            }

            $needsProductUpdate = ((int) ($product->warehouse_id ?? 0) !== (int) $warehouseId)
                || ((int) ($product->warehouse_location_id ?? 0) !== (int) ($locationId ?? 0));

            if ($needsProductUpdate) {
                DB::table('products')->where('id', $product->id)->update([
                    'warehouse_id' => $warehouseId,
                    'warehouse_location_id' => $locationId,
                ]);
            }

            if (($product->item_kind ?? 'stocked') !== 'stocked') {
                continue;
            }

            $existingStock = DB::table('product_stocks')
                ->where('product_id', $product->id)
                ->where('warehouse_id', $warehouseId)
                ->when(
                    $locationId === null,
                    fn ($q) => $q->whereNull('warehouse_location_id'),
                    fn ($q) => $q->where('warehouse_location_id', $locationId)
                )
                ->exists();

            if (! $existingStock) {
                $qty = (int) ($product->stock_quantity ?? 0);
                DB::table('product_stocks')->insert([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouseId,
                    'warehouse_location_id' => $locationId,
                    'quantity' => $qty,
                    'reserved' => (int) ($product->stock_reserved ?? 0),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $stockSettings = [
            ['key' => 'stock_low_threshold', 'value' => '3', 'description' => 'Seuil de stock faible par défaut'],
            ['key' => 'stock_minimum_default', 'value' => '0', 'description' => 'Stock minimum par défaut'],
            ['key' => 'stock_allow_negative', 'value' => '0', 'description' => 'Autoriser stock négatif'],
            ['key' => 'stock_multi_warehouse', 'value' => '1', 'description' => 'Gestion multi-dépôts'],
            ['key' => 'stock_valuation_method', 'value' => '', 'description' => 'Méthode de valorisation du stock'],
            ['key' => 'stock_control_enabled', 'value' => '1', 'description' => 'Contrôle de stock activé'],
        ];

        foreach ($stockSettings as $setting) {
            $exists = DB::table('settings')->where('key', $setting['key'])->exists();
            if (! $exists) {
                DB::table('settings')->insert(array_merge($setting, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }

        Schema::dropIfExists('stock_locations');
        Schema::dropIfExists('depots');
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'warehouse_location_id')) {
                $table->dropConstrainedForeignId('warehouse_location_id');
            }
            if (Schema::hasColumn('products', 'warehouse_id')) {
                $table->dropConstrainedForeignId('warehouse_id');
            }
        });

        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('product_stocks');
        Schema::dropIfExists('warehouse_locations');
        Schema::dropIfExists('warehouses');
    }

    private function defineStockMovementsSchema(Blueprint $table): void
    {
        $table->id();
        $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
        $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
        $table->foreignId('warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
        $table->integer('quantity'); // signed: + entry, - exit
        $table->string('type'); // purchase, sale, customer_return, supplier_return, inventory_adjustment, transfer_out, transfer_in, manual_in, manual_out
        $table->timestamp('moved_at');
        $table->string('document_type')->nullable();
        $table->unsignedBigInteger('document_id')->nullable();
        $table->string('document_reference')->nullable();
        $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
        $table->foreignId('transfer_group_id')->nullable()->index();
        $table->text('notes')->nullable();
        $table->timestamps();

        $table->index(['product_id', 'moved_at']);
        $table->index(['type', 'moved_at']);
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        $exists = DB::selectOne(
            'SELECT COUNT(*) AS c
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        );

        if ((int) ($exists->c ?? 0) > 0) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        }
    }

    private function dropForeignKeyIfExists(string $table, string $column): void
    {
        $fk = DB::selectOne(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1',
            [$table, $column]
        );

        if ($fk?->CONSTRAINT_NAME) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
        }
    }
};
