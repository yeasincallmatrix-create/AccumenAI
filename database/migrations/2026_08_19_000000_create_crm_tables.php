<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM Core — industry-neutral customer relationship management foundation.
 *
 * - Every tenant-owned record is institute-scoped (TenantScoped) and may be
 *   branch-scoped (BranchScoped): branch_id NULL = whole-institute visibility.
 * - Lookup catalogs (crm_contact_types, crm_lead_statuses, crm_lead_sources)
 *   are multi-tenant shared catalogs (no institute_id), seeded below.
 * - crm_notes / crm_activities use a minimal polymorphic
 *   subject_type + subject_id (contact|organization|lead) with a composite
 *   index for timeline lookups. Ownership is validated in the service layer.
 * - Soft deletes everywhere. Duplicate protection is enforced at the service
 *   level (not via DB unique constraints) so soft-deleted rows never block
 *   legitimate re-creation; indexes keep the lookups fast.
 * - MySQL 8 production design: explicit composite indexes matching the
 *   tenant/branch/status/assigned-user query paths.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_contact_types')) {
            Schema::create('crm_contact_types', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 60)->unique();
                $table->string('name', 120);
                $table->string('description', 500)->nullable();
                $table->unsignedInteger('display_order')->default(0);
                $table->string('status', 20)->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('crm_lead_statuses')) {
            Schema::create('crm_lead_statuses', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 60)->unique();
                $table->string('name', 120);
                $table->string('color', 20)->nullable();
                $table->boolean('is_default')->default(false);
                $table->unsignedInteger('display_order')->default(0);
                $table->string('status', 20)->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('crm_lead_sources')) {
            Schema::create('crm_lead_sources', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 60)->unique();
                $table->string('name', 120);
                $table->unsignedInteger('display_order')->default(0);
                $table->string('status', 20)->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('crm_organizations')) {
            Schema::create('crm_organizations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('name', 191);
                $table->string('email', 191)->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('website', 191)->nullable();
                $table->string('industry', 120)->nullable();
                $table->text('description')->nullable();
                $table->string('address_line1', 255)->nullable();
                $table->string('city', 100)->nullable();
                $table->string('state', 100)->nullable();
                $table->string('postal_code', 20)->nullable();
                $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
                $table->boolean('is_customer')->default(false);
                $table->boolean('is_prospect')->default(false);
                $table->date('customer_since')->nullable();
                $table->foreignId('assigned_user_id')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->string('status', 20)->default('active');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['institute_id', 'branch_id'], 'crm_org_institute_branch_idx');
                $table->index('branch_id', 'crm_org_branch_idx');
                $table->index('assigned_user_id', 'crm_org_assigned_idx');
                $table->index('email', 'crm_org_email_idx');
                $table->index('status', 'crm_org_status_idx');
                $table->index('name', 'crm_org_name_idx');
            });
        }

        if (! Schema::hasTable('crm_contacts')) {
            Schema::create('crm_contacts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->foreignId('contact_type_id')->nullable()->constrained('crm_contact_types')->nullOnDelete();
                $table->string('salutation', 20)->nullable();
                $table->string('first_name', 120);
                $table->string('last_name', 120);
                $table->string('email', 191)->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('phone_alt', 30)->nullable();
                $table->string('whatsapp', 30)->nullable();
                $table->foreignId('organization_id')->nullable()->constrained('crm_organizations')->nullOnDelete();
                $table->string('designation', 120)->nullable();
                $table->string('address_line1', 255)->nullable();
                $table->string('city', 100)->nullable();
                $table->string('state', 100)->nullable();
                $table->string('postal_code', 20)->nullable();
                $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
                $table->boolean('is_customer')->default(false);
                $table->boolean('is_prospect')->default(false);
                $table->date('customer_since')->nullable();
                $table->foreignId('source_id')->nullable()->constrained('crm_lead_sources')->nullOnDelete();
                $table->foreignId('assigned_user_id')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->string('status', 20)->default('active');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['institute_id', 'branch_id'], 'crm_contact_institute_branch_idx');
                $table->index('branch_id', 'crm_contact_branch_idx');
                $table->index('contact_type_id', 'crm_contact_type_idx');
                $table->index('organization_id', 'crm_contact_org_idx');
                $table->index('assigned_user_id', 'crm_contact_assigned_idx');
                $table->index('email', 'crm_contact_email_idx');
                $table->index('status', 'crm_contact_status_idx');
                $table->index('first_name', 'crm_contact_first_name_idx');
                $table->index('last_name', 'crm_contact_last_name_idx');
            });
        }

        if (! Schema::hasTable('crm_leads')) {
            Schema::create('crm_leads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->foreignId('status_id')->nullable()->constrained('crm_lead_statuses')->nullOnDelete();
                $table->foreignId('source_id')->nullable()->constrained('crm_lead_sources')->nullOnDelete();
                $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
                $table->foreignId('organization_id')->nullable()->constrained('crm_organizations')->nullOnDelete();
                $table->string('first_name', 120);
                $table->string('last_name', 120);
                $table->string('email', 191)->nullable();
                $table->string('phone', 30)->nullable();
                $table->text('interest_summary')->nullable();
                $table->decimal('value_amount', 14, 2)->nullable();
                $table->foreignId('assigned_user_id')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->dateTime('converted_at')->nullable();
                $table->foreignId('converted_contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['institute_id', 'branch_id'], 'crm_lead_institute_branch_idx');
                $table->index('branch_id', 'crm_lead_branch_idx');
                $table->index('status_id', 'crm_lead_status_idx');
                $table->index('source_id', 'crm_lead_source_idx');
                $table->index('assigned_user_id', 'crm_lead_assigned_idx');
                $table->index('email', 'crm_lead_email_idx');
                $table->index('contact_id', 'crm_lead_contact_idx');
            });
        }

        if (! Schema::hasTable('crm_notes')) {
            Schema::create('crm_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('subject_type', 40);
                $table->unsignedBigInteger('subject_id');
                $table->text('body');
                $table->foreignId('created_by')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['institute_id', 'branch_id'], 'crm_note_institute_branch_idx');
                $table->index('branch_id', 'crm_note_branch_idx');
                $table->index(['subject_type', 'subject_id'], 'crm_note_subject_idx');
            });
        }

        if (! Schema::hasTable('crm_activities')) {
            Schema::create('crm_activities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('subject_type', 40);
                $table->unsignedBigInteger('subject_id');
                $table->string('type', 40)->default('note');
                $table->string('summary', 255);
                $table->text('description')->nullable();
                $table->dateTime('activity_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->foreignId('assigned_user_id')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['institute_id', 'branch_id'], 'crm_activity_institute_branch_idx');
                $table->index('branch_id', 'crm_activity_branch_idx');
                $table->index(['subject_type', 'subject_id'], 'crm_activity_subject_idx');
                $table->index('activity_at', 'crm_activity_at_idx');
                $table->index('assigned_user_id', 'crm_activity_assigned_idx');
                $table->index('type', 'crm_activity_type_idx');
            });
        }

        if (! Schema::hasTable('crm_tasks')) {
            Schema::create('crm_tasks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('subject_type', 40)->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->string('priority', 20)->default('normal');
                $table->string('status', 20)->default('open');
                $table->dateTime('due_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->foreignId('assigned_user_id')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['institute_id', 'branch_id'], 'crm_task_institute_branch_idx');
                $table->index('branch_id', 'crm_task_branch_idx');
                $table->index(['subject_type', 'subject_id'], 'crm_task_subject_idx');
                $table->index('status', 'crm_task_status_idx');
                $table->index('assigned_user_id', 'crm_task_assigned_idx');
                $table->index('due_at', 'crm_task_due_idx');
            });
        }

        $now = now()->toDateTimeString();

        $contactTypes = [
            ['slug' => 'individual', 'name' => 'Individual', 'description' => 'A single person', 'display_order' => 1],
            ['slug' => 'organization', 'name' => 'Organization', 'description' => 'A company or institution contact', 'display_order' => 2],
            ['slug' => 'customer', 'name' => 'Customer', 'description' => 'An existing paying customer', 'display_order' => 3],
            ['slug' => 'patient', 'name' => 'Patient', 'description' => 'A patient receiving care', 'display_order' => 4],
            ['slug' => 'client', 'name' => 'Client', 'description' => 'A client of the business', 'display_order' => 5],
            ['slug' => 'supplier', 'name' => 'Supplier', 'description' => 'A vendor or supplier', 'display_order' => 6],
            ['slug' => 'vendor', 'name' => 'Vendor', 'description' => 'A service or product vendor', 'display_order' => 7],
        ];

        foreach ($contactTypes as $type) {
            $exists = DB::table('crm_contact_types')->where('slug', $type['slug'])->exists();
            if (! $exists) {
                DB::table('crm_contact_types')->insert($type + ['status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        $leadStatuses = [
            ['slug' => 'new', 'name' => 'New', 'color' => '#0d6efd', 'is_default' => true, 'display_order' => 1],
            ['slug' => 'contacted', 'name' => 'Contacted', 'color' => '#0dcaf0', 'is_default' => false, 'display_order' => 2],
            ['slug' => 'qualified', 'name' => 'Qualified', 'color' => '#6f42c1', 'is_default' => false, 'display_order' => 3],
            ['slug' => 'proposal', 'name' => 'Proposal', 'color' => '#fd7e14', 'is_default' => false, 'display_order' => 4],
            ['slug' => 'won', 'name' => 'Won', 'color' => '#198754', 'is_default' => false, 'display_order' => 5],
            ['slug' => 'lost', 'name' => 'Lost', 'color' => '#dc3545', 'is_default' => false, 'display_order' => 6],
        ];

        foreach ($leadStatuses as $status) {
            $exists = DB::table('crm_lead_statuses')->where('slug', $status['slug'])->exists();
            if (! $exists) {
                DB::table('crm_lead_statuses')->insert($status + ['status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        $leadSources = [
            ['slug' => 'website', 'name' => 'Website', 'display_order' => 1],
            ['slug' => 'referral', 'name' => 'Referral', 'display_order' => 2],
            ['slug' => 'walk_in', 'name' => 'Walk-in', 'display_order' => 3],
            ['slug' => 'social_media', 'name' => 'Social Media', 'display_order' => 4],
            ['slug' => 'cold_call', 'name' => 'Cold Call', 'display_order' => 5],
            ['slug' => 'email_campaign', 'name' => 'Email Campaign', 'display_order' => 6],
            ['slug' => 'advertisement', 'name' => 'Advertisement', 'display_order' => 7],
            ['slug' => 'partner', 'name' => 'Partner', 'display_order' => 8],
            ['slug' => 'other', 'name' => 'Other', 'display_order' => 99],
        ];

        foreach ($leadSources as $source) {
            $exists = DB::table('crm_lead_sources')->where('slug', $source['slug'])->exists();
            if (! $exists) {
                DB::table('crm_lead_sources')->insert($source + ['status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_tasks');
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('crm_notes');
        Schema::dropIfExists('crm_leads');
        Schema::dropIfExists('crm_contacts');
        Schema::dropIfExists('crm_organizations');
        Schema::dropIfExists('crm_lead_sources');
        Schema::dropIfExists('crm_lead_statuses');
        Schema::dropIfExists('crm_contact_types');
    }
};
