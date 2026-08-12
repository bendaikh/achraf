<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Completes payment reconciliation schema against the existing
 * 2026_08_07 tables (payment_imports, payment_import_rows, order_trackings).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_fulfillments')) {
            Schema::create('order_fulfillments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pos_sale_id')->constrained('pos_sales')->cascadeOnDelete();
                $table->string('shopify_order_id')->nullable()->index();
                $table->string('shopify_fulfillment_id')->nullable();
                $table->string('tracking_number')->nullable()->index();
                $table->string('tracking_company')->nullable();
                $table->string('tracking_url')->nullable();
                $table->string('status')->nullable()->index();
                $table->timestamp('shopify_created_at')->nullable();
                $table->timestamp('shopify_updated_at')->nullable();
                $table->json('raw_payload')->nullable();
                $table->timestamps();
                $table->unique(['pos_sale_id', 'shopify_fulfillment_id'], 'order_fulfillments_sale_fulfillment_unique');
            });
        }

        if (Schema::hasTable('payment_imports')) {
            Schema::table('payment_imports', function (Blueprint $table) {
                if (! Schema::hasColumn('payment_imports', 'scope')) {
                    $table->string('scope')->default('sales')->after('id');
                }
                if (! Schema::hasColumn('payment_imports', 'file_hash')) {
                    $table->string('file_hash', 64)->nullable()->index()->after('file_path');
                }
                if (! Schema::hasColumn('payment_imports', 'original_filename') && Schema::hasColumn('payment_imports', 'file_name')) {
                    // keep file_name; accessors map original_filename
                }
            });
        }

        if (Schema::hasTable('payment_import_rows')) {
            Schema::table('payment_import_rows', function (Blueprint $table) {
                if (! Schema::hasColumn('payment_import_rows', 'file_order_ref')) {
                    $table->string('file_order_ref')->nullable()->index()->after('file_tracking');
                }
                if (! Schema::hasColumn('payment_import_rows', 'file_raw')) {
                    $table->json('file_raw')->nullable()->after('file_amount');
                }
                if (! Schema::hasColumn('payment_import_rows', 'amount_status')) {
                    $table->string('amount_status')->nullable()->after('match_status');
                }
                if (! Schema::hasColumn('payment_import_rows', 'resolved_tracking')) {
                    $table->string('resolved_tracking')->nullable()->after('order_tracking_id');
                }
                if (! Schema::hasColumn('payment_import_rows', 'supplier_invoice_id')) {
                    $table->foreignId('supplier_invoice_id')->nullable()->after('invoice_id')->constrained()->nullOnDelete();
                }
                if (! Schema::hasColumn('payment_import_rows', 'supplier_invoice_payment_id')) {
                    $table->unsignedBigInteger('supplier_invoice_payment_id')->nullable()->after('invoice_payment_id');
                }
                if (! Schema::hasColumn('payment_import_rows', 'exclude')) {
                    $table->boolean('exclude')->default(false)->after('include_in_validation');
                }
            });
        }

        if (Schema::hasTable('invoice_payments')) {
            Schema::table('invoice_payments', function (Blueprint $table) {
                if (! Schema::hasColumn('invoice_payments', 'tracking_number')) {
                    $table->string('tracking_number')->nullable()->index()->after('source');
                }
                if (! Schema::hasColumn('invoice_payments', 'carrier')) {
                    $table->string('carrier')->nullable()->after('tracking_number');
                }
                if (! Schema::hasColumn('invoice_payments', 'user_id') && ! Schema::hasColumn('invoice_payments', 'created_by')) {
                    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('supplier_invoice_payments')) {
            Schema::table('supplier_invoice_payments', function (Blueprint $table) {
                if (! Schema::hasColumn('supplier_invoice_payments', 'source')) {
                    $table->string('source')->default('manual')->after('notes');
                }
                if (! Schema::hasColumn('supplier_invoice_payments', 'tracking_number')) {
                    $table->string('tracking_number')->nullable()->index()->after('source');
                }
                if (! Schema::hasColumn('supplier_invoice_payments', 'user_id')) {
                    $table->foreignId('user_id')->nullable()->after('tracking_number')->constrained()->nullOnDelete();
                }
                if (! Schema::hasColumn('supplier_invoice_payments', 'payment_import_id')) {
                    $table->foreignId('payment_import_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
                }
                if (! Schema::hasColumn('supplier_invoice_payments', 'payment_import_row_id')) {
                    $table->unsignedBigInteger('payment_import_row_id')->nullable()->after('payment_import_id');
                }
                if (! Schema::hasColumn('supplier_invoice_payments', 'dedupe_key')) {
                    $table->string('dedupe_key')->nullable()->unique()->after('payment_import_row_id');
                }
                if (! Schema::hasColumn('supplier_invoice_payments', 'allow_overpayment')) {
                    $table->boolean('allow_overpayment')->default(false)->after('dedupe_key');
                }
            });
        }
    }

    public function down(): void
    {
        // Non-destructive: keep existing reconciliation data.
    }
};
