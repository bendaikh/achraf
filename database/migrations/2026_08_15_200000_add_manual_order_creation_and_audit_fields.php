<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->uuid('creation_token')->nullable()->unique()->after('ticket_number');
            $table->foreignId('created_by_user_id')->nullable()->after('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->after('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('assigned_employee_id')->nullable()->after('assigned_user_id')->index();
            $table->string('sales_channel', 32)->nullable()->after('source');
            $table->string('shopify_order_id', 64)->nullable()->unique()->after('external_id');
            $table->string('shopify_order_number', 64)->nullable()->after('shopify_order_id');
            $table->string('sync_status', 24)->default('not_synced')->after('shopify_order_number')->index();
            $table->text('sync_error')->nullable()->after('sync_status');
            $table->timestamp('sync_attempted_at')->nullable()->after('sync_error');
            $table->string('discount_type', 16)->nullable()->after('discount');
            $table->decimal('discount_value', 12, 2)->default(0)->after('discount_type');
            $table->string('discount_reason')->nullable()->after('discount_value');
            $table->decimal('shipping_amount', 12, 2)->default(0)->after('tax_total');
            $table->string('shipping_address')->nullable()->after('shipping_amount');
            $table->string('shipping_city')->nullable()->after('shipping_address');
            $table->string('shipping_postal_code', 32)->nullable()->after('shipping_city');
            $table->string('shipping_country', 64)->nullable()->after('shipping_postal_code');
            $table->string('shipping_method')->nullable()->after('shipping_country');
            $table->text('delivery_note')->nullable()->after('notes');
            $table->text('internal_note')->nullable()->after('delivery_note');
            $table->json('tags')->nullable()->after('internal_note');
        });

        Schema::table('pos_sale_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained('product_variants')->nullOnDelete();
            $table->string('variant_title')->nullable()->after('designation');
            $table->string('shopify_variant_id', 64)->nullable()->after('variant_title');
        });

        Schema::create('order_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_sale_id')->constrained('pos_sales')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 64)->index();
            $table->string('description');
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_activities');

        Schema::table('pos_sale_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_variant_id');
            $table->dropColumn(['variant_title', 'shopify_variant_id']);
        });

        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropConstrainedForeignId('assigned_user_id');
            $table->dropUnique(['creation_token']);
            $table->dropUnique(['shopify_order_id']);
            $table->dropColumn([
                'creation_token', 'assigned_employee_id', 'sales_channel',
                'shopify_order_id', 'shopify_order_number', 'sync_status',
                'sync_error', 'sync_attempted_at', 'discount_type',
                'discount_value', 'discount_reason', 'shipping_amount',
                'shipping_address', 'shipping_city', 'shipping_postal_code',
                'shipping_country', 'shipping_method', 'delivery_note',
                'internal_note', 'tags',
            ]);
        });
    }
};
