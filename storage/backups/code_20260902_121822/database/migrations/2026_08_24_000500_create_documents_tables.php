<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 46 — Generic Document Management core.
 *
 * A single tenant-safe documents store that any entity (Student, Teacher,
 * Staff, Course, Batch, Institute, CRM Lead/Contact/Organization, Invoice,
 * Payment, Certificate, …) can link to via a polymorphic relationship. Files
 * are stored on the application's public disk (relative paths), never in
 * BLOB columns. document_categories provides reusable, entity-scoped types.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')
                ->nullable()
                ->constrained('institutes')
                ->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 120);
            $table->json('entity_types')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['institute_id'], 'idx_document_categories_institute');
            $table->index(['is_active', 'sort_order'], 'idx_document_categories_active_order');
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->nullOnDelete();
            $table->string('documentable_type', 120);
            $table->unsignedBigInteger('documentable_id');
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('document_categories')
                ->nullOnDelete();
            $table->string('title', 200)->nullable();
            $table->text('description')->nullable();
            $table->string('original_filename', 255);
            $table->string('file_path', 500);
            $table->string('disk', 50)->default('public');
            $table->string('mime_type', 120)->nullable();
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('institute_users')
                ->nullOnDelete();
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['institute_id'], 'idx_documents_institute');
            $table->index(['branch_id'], 'idx_documents_branch');
            $table->index(['category_id'], 'idx_documents_category');
            $table->index(['documentable_type', 'documentable_id'], 'idx_documents_documentable');
            $table->index(['status'], 'idx_documents_status');
        });

        $this->seedCategories();
    }

    private function seedCategories(): void
    {
        if (DB::table('document_categories')->exists()) {
            return;
        }

        DB::table('document_categories')->insert([
            ['name' => 'Birth Certificate', 'slug' => 'birth-certificate', 'entity_types' => json_encode(['student']), 'is_active' => true, 'sort_order' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'NID', 'slug' => 'nid', 'entity_types' => json_encode(['student', 'staff', 'teacher']), 'is_active' => true, 'sort_order' => 20, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Passport', 'slug' => 'passport', 'entity_types' => json_encode(['student', 'staff', 'teacher']), 'is_active' => true, 'sort_order' => 30, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academic Certificate', 'slug' => 'academic-certificate', 'entity_types' => json_encode(['student', 'certificate']), 'is_active' => true, 'sort_order' => 40, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Photo', 'slug' => 'photo', 'entity_types' => json_encode(['student', 'staff', 'teacher']), 'is_active' => true, 'sort_order' => 50, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Resume / CV', 'slug' => 'resume', 'entity_types' => json_encode(['staff', 'teacher']), 'is_active' => true, 'sort_order' => 60, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Contract', 'slug' => 'contract', 'entity_types' => json_encode(['staff', 'teacher', 'course']), 'is_active' => true, 'sort_order' => 70, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Payment Receipt', 'slug' => 'payment-receipt', 'entity_types' => json_encode(['payment', 'invoice']), 'is_active' => true, 'sort_order' => 80, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Invoice', 'slug' => 'invoice', 'entity_types' => json_encode(['invoice']), 'is_active' => true, 'sort_order' => 90, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Certificate', 'slug' => 'certificate', 'entity_types' => json_encode(['certificate']), 'is_active' => true, 'sort_order' => 100, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Trade License', 'slug' => 'trade-license', 'entity_types' => json_encode(['institute']), 'is_active' => true, 'sort_order' => 110, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Other', 'slug' => 'other', 'entity_types' => null, 'is_active' => true, 'sort_order' => 999, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_categories');
    }
};
