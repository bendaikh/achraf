<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('managed_documents', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentable');
            $table->string('section_key', 64);
            $table->string('category', 64)->default('primary');
            $table->string('document_type_label')->nullable();
            $table->string('reference')->nullable()->index();
            $table->date('document_date')->nullable()->index();
            $table->string('display_name');
            $table->unsignedInteger('sequence')->default(1);
            $table->string('source', 32)->default('upload');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('imported_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['section_key', 'document_date', 'reference'], 'managed_docs_section_date_ref_idx');
            $table->index(['documentable_type', 'documentable_id', 'category'], 'managed_docs_owner_category_idx');
        });

        Schema::create('managed_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('managed_document_id')->constrained('managed_documents')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->string('source', 32)->default('upload');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['managed_document_id', 'version_number'], 'managed_doc_versions_unique');
        });

        Schema::table('managed_documents', function (Blueprint $table) {
            $table->foreignId('current_version_id')
                ->nullable()
                ->after('sequence')
                ->constrained('managed_document_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('managed_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_version_id');
        });

        Schema::dropIfExists('managed_document_versions');
        Schema::dropIfExists('managed_documents');
    }
};
