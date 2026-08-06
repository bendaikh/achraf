<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // Informations générales
            $table->string('legal_name')->nullable()->after('name');
            $table->string('trade_name')->nullable()->after('legal_name');
            $table->string('whatsapp')->nullable()->after('phone');
            $table->string('website')->nullable()->after('email');

            // Informations juridiques
            $table->string('rc')->nullable()->after('fiscal_identifier');
            $table->string('rc_city')->nullable()->after('rc');
            $table->string('tp')->nullable()->after('rc_city');
            $table->string('legal_form')->nullable()->after('tp');
            $table->date('company_created_at')->nullable()->after('legal_form');

            // Contact principal
            $table->string('contact_name')->nullable()->after('company_created_at');
            $table->string('contact_role')->nullable()->after('contact_name');
            $table->string('contact_phone')->nullable()->after('contact_role');
            $table->string('contact_mobile')->nullable()->after('contact_phone');
            $table->string('contact_email')->nullable()->after('contact_mobile');

            // Coordonnées bancaires
            $table->string('bank_name')->nullable()->after('contact_email');
            $table->string('bank_account_holder')->nullable()->after('bank_name');
            $table->string('rib')->nullable()->after('bank_account_holder');
            $table->string('iban')->nullable()->after('rib');
            $table->string('swift_bic')->nullable()->after('iban');

            // Conditions commerciales
            $table->string('payment_method')->nullable()->after('swift_bic');
            $table->string('payment_terms')->nullable()->after('payment_method');
            $table->string('currency')->nullable()->default('MAD')->after('payment_terms');
            $table->decimal('discount_percent', 8, 2)->nullable()->after('currency');
            $table->decimal('min_order_amount', 12, 2)->nullable()->after('discount_percent');
            $table->unsignedInteger('delivery_lead_days')->nullable()->after('min_order_amount');

            // Gestion interne
            $table->string('status')->default('actif')->after('delivery_lead_days');
            $table->string('category')->nullable()->after('status');
            $table->foreignId('internal_owner_id')->nullable()->after('category')->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable()->after('internal_owner_id');

            // Documents joints
            $table->string('rc_document_path')->nullable()->after('notes');
            $table->string('ice_attestation_path')->nullable()->after('rc_document_path');
            $table->string('if_attestation_path')->nullable()->after('ice_attestation_path');
            $table->string('rib_document_path')->nullable()->after('if_attestation_path');
            $table->string('contract_path')->nullable()->after('rib_document_path');
            $table->string('catalog_path')->nullable()->after('contract_path');
            $table->string('price_list_path')->nullable()->after('catalog_path');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('internal_owner_id');
            $table->dropColumn([
                'legal_name',
                'trade_name',
                'whatsapp',
                'website',
                'rc',
                'rc_city',
                'tp',
                'legal_form',
                'company_created_at',
                'contact_name',
                'contact_role',
                'contact_phone',
                'contact_mobile',
                'contact_email',
                'bank_name',
                'bank_account_holder',
                'rib',
                'iban',
                'swift_bic',
                'payment_method',
                'payment_terms',
                'currency',
                'discount_percent',
                'min_order_amount',
                'delivery_lead_days',
                'status',
                'category',
                'notes',
                'rc_document_path',
                'ice_attestation_path',
                'if_attestation_path',
                'rib_document_path',
                'contract_path',
                'catalog_path',
                'price_list_path',
            ]);
        });
    }
};
