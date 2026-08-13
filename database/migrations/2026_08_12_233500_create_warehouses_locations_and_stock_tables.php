<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        Schema::create('stock_movements', function (Blueprint $table) {
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
        });

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'warehouse_id')) {
                $table->foreignId('warehouse_id')->nullable()->after('depot')->constrained('warehouses')->nullOnDelete();
            }
            if (! Schema::hasColumn('products', 'warehouse_location_id')) {
                $table->foreignId('warehouse_location_id')->nullable()->after('warehouse_id')->constrained('warehouse_locations')->nullOnDelete();
            }
        });

        // Seed default warehouse + migrate free-text depot/location values.
        $now = now();
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

        $warehouseMap = ['principal' => $primaryId, 'dépôt principal' => $primaryId, 'depot principal' => $primaryId];

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

        $products = DB::table('products')->select('id', 'depot', 'location', 'stock_quantity', 'stock_reserved', 'item_kind')->get();
        foreach ($products as $product) {
            $warehouseId = $primaryId;
            if ($product->depot) {
                $key = mb_strtolower(trim((string) $product->depot));
                $warehouseId = $warehouseMap[$key] ?? $primaryId;
            }

            $locationId = null;
            if ($product->location) {
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

            DB::table('products')->where('id', $product->id)->update([
                'warehouse_id' => $warehouseId,
                'warehouse_location_id' => $locationId,
            ]);

            if (($product->item_kind ?? 'stocked') === 'stocked') {
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
};
