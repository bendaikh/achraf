<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Completes payment reconciliation schema (order fulfillments, payment imports,
 * and payment tracking columns on invoice payment tables).
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

        if (! Schema::hasTable('order_trackings')) {
            Schema::create('order_trackings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pos_sale_id')->constrained('pos_sales')->cascadeOnDelete();
                $table->string('tracking_number')->nullable()->index();
                $table->string('carrier')->nullable();
                $table->string('shopify_fulfillment_id')->nullable()->index();
                $table->string('status')->nullable()->index();
                $table->timestamp('shopify_created_at')->nullable();
                $table->timestamp('shopify_updated_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payment_imports')) {
            Schema::create('payment_imports', function (Blueprint $table) {
                $table->id();
                $table->string('scope')->default('sales')->index();
                $table->string('status')->default('draft')->index();
                $table->string('file_name');
                $table->string('file_path');
                $table->string('file_type')->nullable();
                $table->string('file_hash', 64)->nullable()->index();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('uploaded_at')->nullable();
                $table->unsignedInteger('total_rows')->default(0);
                $table->unsignedInteger('matched_count')->default(0);
                $table->unsignedInteger('ambiguous_count')->default(0);
                $table->unsignedInteger('not_found_count')->default(0);
                $table->unsignedInteger('duplicate_count')->default(0);
                $table->date('payment_date')->nullable();
                $table->string('payment_method')->nullable();
                $table->string('payment_reference')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('validated_at')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('payment_imports', function (Blueprint $table) {
                if (! Schema::hasColumn('payment_imports', 'scope')) {
                    $table->string('scope')->default('sales')->after('id');
                }
                if (! Schema::hasColumn('payment_imports', 'file_hash')) {
                    $table->string('file_hash', 64)->nullable()->index()->after('file_path');
                }
            });
        }

        if (! Schema::hasTable('payment_import_rows')) {
            Schema::create('payment_import_rows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_import_id')->constrained('payment_imports')->cascadeOnDelete();
                $table->unsignedInteger('row_number')->default(0);
                $table->string('file_reference')->nullable()->index();
                $table->string('file_tracking')->nullable()->index();
                $table->string('file_order_ref')->nullable()->index();
                $table->decimal('file_amount', 12, 2)->nullable();
                $table->json('file_raw')->nullable();
                $table->string('normalized_lookup')->nullable()->index();
                $table->string('match_status')->nullable()->index();
                $table->string('amount_status')->nullable();
                $table->decimal('expected_amount', 12, 2)->nullable();
                $table->decimal('amount_variance', 12, 2)->nullable();
                $table->decimal('override_amount', 12, 2)->nullable();
                $table->foreignId('pos_sale_id')->nullable()->constrained('pos_sales')->nullOnDelete();
                $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
                $table->foreignId('supplier_invoice_id')->nullable()->constrained('supplier_invoices')->nullOnDelete();
                $table->foreignId('order_tracking_id')->nullable()->constrained('order_trackings')->nullOnDelete();
                $table->string('resolved_tracking')->nullable();
                $table->json('candidate_matches')->nullable();
                $table->boolean('allow_overpayment')->default(false);
                $table->boolean('include_in_validation')->default(true);
                $table->boolean('exclude')->default(false);
                $table->unsignedBigInteger('duplicate_payment_id')->nullable();
                $table->unsignedBigInteger('invoice_payment_id')->nullable();
                $table->unsignedBigInteger('supplier_invoice_payment_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        } else {
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
                if (! Schema::hasColumn('invoice_payments', 'source')) {
                    $table->string('source')->default('manual')->after('notes');
                }
                if (! Schema::hasColumn('invoice_payments', 'tracking_number')) {
                    $table->string('tracking_number')->nullable()->index()->after('source');
                }
                if (! Schema::hasColumn('invoice_payments', 'carrier')) {
                    $table->string('carrier')->nullable()->after('tracking_number');
                }
                if (! Schema::hasColumn('invoice_payments', 'created_by')) {
                    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('invoice_payments', 'pos_sale_id')) {
                    $table->foreignId('pos_sale_id')->nullable()->after('invoice_id')->constrained('pos_sales')->nullOnDelete();
                }
                if (! Schema::hasColumn('invoice_payments', 'order_tracking_id') && Schema::hasTable('order_trackings')) {
                    $table->foreignId('order_tracking_id')->nullable()->constrained('order_trackings')->nullOnDelete();
                }
                if (! Schema::hasColumn('invoice_payments', 'payment_import_id') && Schema::hasTable('payment_imports')) {
                    $table->foreignId('payment_import_id')->nullable()->constrained('payment_imports')->nullOnDelete();
                }
                if (! Schema::hasColumn('invoice_payments', 'payment_import_row_id')) {
                    $table->unsignedBigInteger('payment_import_row_id')->nullable();
                }
                if (! Schema::hasColumn('invoice_payments', 'dedupe_key')) {
                    $table->string('dedupe_key')->nullable()->unique();
                }
                if (! Schema::hasColumn('invoice_payments', 'allow_overpayment')) {
                    $table->boolean('allow_overpayment')->default(false);
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
                if (! Schema::hasColumn('supplier_invoice_payments', 'payment_import_id') && Schema::hasTable('payment_imports')) {
                    $table->foreignId('payment_import_id')->nullable()->after('user_id')->constrained('payment_imports')->nullOnDelete();
                }
                if (! Schema::hasColumn('supplier_invoice_payments', 'payment_import_row_id')) {
                    $table->unsignedBigInteger('payment_import_row_id')->nullable();
                }
                if (! Schema::hasColumn('supplier_invoice_payments', 'dedupe_key')) {
                    $table->string('dedupe_key')->nullable()->unique();
                }
                if (! Schema::hasColumn('supplier_invoice_payments', 'allow_overpayment')) {
                    $table->boolean('allow_overpayment')->default(false);
                }
            });
        }
    }

    public function down(): void
    {
        // Non-destructive: keep existing reconciliation data.
    }
};
