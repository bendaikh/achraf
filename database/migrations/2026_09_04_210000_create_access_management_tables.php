<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collaborators', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32); // salarie|freelance|prestataire|stagiaire|autre
            $table->string('last_name');
            $table->string('first_name');
            $table->string('photo_path')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('job_title')->nullable();
            $table->string('department')->nullable();
            $table->string('team')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('collaborators')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->unique()->constrained('employees')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('actif'); // actif|inactif
            $table->boolean('is_commercial')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['last_name', 'first_name']);
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('module'); // ventes|achats|stock|tiers|finance|rh|administration|sensible
            $table->string('resource')->nullable();
            $table->string('action')->nullable(); // voir|creer|modifier|valider|annuler|supprimer|exporter
            $table->string('label');
            $table->string('group_label')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['module', 'sort_order']);
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->unique(['role_id', 'permission_id']);
        });

        Schema::create('permission_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->boolean('granted')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'permission_id']);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('description');
            $table->boolean('is_template')->default(true)->after('is_system');
            $table->unsignedInteger('sort_order')->default(0)->after('is_template');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('collaborator_id')->nullable()->after('id')->constrained('collaborators')->nullOnDelete();
            $table->foreignId('primary_role_id')->nullable()->after('collaborator_id')->constrained('roles')->nullOnDelete();
            $table->string('status', 20)->default('actif')->after('password'); // actif|inactif|suspendu
            $table->string('data_scope', 32)->default('own')->after('status'); // own|team|warehouses|all
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            // Reserved for future auth (invitation / 2FA) — not used yet
            $table->string('invitation_token')->nullable()->after('last_login_at');
            $table->timestamp('invitation_sent_at')->nullable()->after('invitation_token');
            $table->timestamp('activated_at')->nullable()->after('invitation_sent_at');
            $table->boolean('two_factor_enabled')->default(false)->after('activated_at');
            $table->text('two_factor_secret')->nullable()->after('two_factor_enabled');

            $table->unique('collaborator_id');
            $table->index('status');
            $table->index('data_scope');
        });

        Schema::create('user_warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->unique(['user_id', 'warehouse_id']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // connexion|creation|modification|validation|...
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('document_ref')->nullable();
            $table->string('summary')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['action', 'created_at']);
            $table->index('created_at');
        });

        // Extensible commission foundation (UI in later phase)
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 32); // percent_ca|fixed|percent_margin|product|category
            $table->string('base', 32)->default('ca_ht'); // ca_ht|ca_ttc|collected|margin|fixed
            $table->decimal('rate', 10, 4)->nullable();
            $table->decimal('fixed_amount', 12, 2)->nullable();
            $table->string('trigger', 32)->default('delivered_paid'); // invoice_validated|delivered|paid|delivered_paid
            $table->boolean('is_active')->default(true);
            $table->json('filters')->nullable(); // future: product/category scopes
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collaborator_id')->constrained('collaborators')->cascadeOnDelete();
            $table->foreignId('commission_rule_id')->nullable()->constrained('commission_rules')->nullOnDelete();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('document_ref')->nullable();
            $table->decimal('base_amount', 14, 2)->default(0);
            $table->decimal('rate', 10, 4)->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('status', 32)->default('a_venir'); // a_venir|acquise|validee|payee|annulee|regularisee
            $table->date('earned_at')->nullable();
            $table->date('validated_at')->nullable();
            $table->date('paid_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('commissions')->nullOnDelete(); // regularisations
            $table->timestamps();

            $table->index(['collaborator_id', 'status']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('commercial_reassignments', function (Blueprint $table) {
            $table->id();
            $table->string('document_type');
            $table->unsignedBigInteger('document_id');
            $table->string('document_ref')->nullable();
            $table->foreignId('from_collaborator_id')->nullable()->constrained('collaborators')->nullOnDelete();
            $table->foreignId('to_collaborator_id')->constrained('collaborators')->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['document_type', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_reassignments');
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('user_warehouses');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('collaborator_id');
            $table->dropConstrainedForeignId('primary_role_id');
            $table->dropColumn([
                'status',
                'data_scope',
                'last_login_at',
                'invitation_token',
                'invitation_sent_at',
                'activated_at',
                'two_factor_enabled',
                'two_factor_secret',
            ]);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['is_system', 'is_template', 'sort_order']);
        });

        Schema::dropIfExists('permission_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('collaborators');
    }
};
