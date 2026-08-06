<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('status')->default('actif')->after('client_type');
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->date('date_of_birth')->nullable()->after('last_name');
            $table->string('whatsapp')->nullable()->after('phone');
            $table->string('cin')->nullable()->after('fiscal_identifier');
            $table->string('cin_issue_city')->nullable()->after('cin');
            $table->string('rc')->nullable()->after('cin_issue_city');
            $table->text('notes')->nullable()->after('rc');
            $table->string('category')->nullable()->after('notes');
            $table->string('acquisition_source')->nullable()->after('category');
            $table->decimal('discount_percent', 5, 2)->nullable()->after('acquisition_source');
            $table->unsignedInteger('loyalty_points')->default(0)->after('discount_percent');
            $table->boolean('is_vip')->default(false)->after('loyalty_points');
            $table->string('preferred_payment_method')->nullable()->after('is_vip');
            $table->string('preferred_delivery_mode')->nullable()->after('preferred_payment_method');
            $table->string('currency', 10)->default('MAD')->after('preferred_delivery_mode');
            $table->string('purchase_frequency')->nullable()->after('currency');
            $table->decimal('order_ceiling', 12, 2)->nullable()->after('purchase_frequency');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'first_name',
                'last_name',
                'date_of_birth',
                'whatsapp',
                'cin',
                'cin_issue_city',
                'rc',
                'notes',
                'category',
                'acquisition_source',
                'discount_percent',
                'loyalty_points',
                'is_vip',
                'preferred_payment_method',
                'preferred_delivery_mode',
                'currency',
                'purchase_frequency',
                'order_ceiling',
            ]);
        });
    }
};
