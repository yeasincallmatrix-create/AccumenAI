<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HR-3 — Employee Document Management.
 *
 * Extends the generic Step 46/51 document infrastructure for HR:
 * - Adds document_number/reference column to documents.
 * - Seeds HR-specific document categories scoped to hr-employee.
 * - Adds HR document permissions (hr.document.view/manage/verify).
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        ['module' => 'hr', 'name' => 'HR Document View', 'slug' => 'hr.document.view'],
        ['module' => 'hr', 'name' => 'HR Document Manage', 'slug' => 'hr.document.manage'],
        ['module' => 'hr', 'name' => 'HR Document Verify', 'slug' => 'hr.document.verify'],
    ];

    private const GRANTS = [
        'institute-owner' => ['hr.document.view', 'hr.document.manage', 'hr.document.verify'],
        'institute-admin' => ['hr.document.view', 'hr.document.manage', 'hr.document.verify'],
        'branch-manager' => ['hr.document.view', 'hr.document.manage', 'hr.document.verify'],
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('documents', 'document_number')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->string('document_number', 100)->nullable()->after('title');
                $table->index(['institute_id', 'document_number'], 'idx_documents_number');
            });
        }

        $this->seedHrCategories();
        $this->seedPermissions();
    }

    private function seedHrCategories(): void
    {
        $categories = [
            ['name' => 'NID / Passport', 'slug' => 'hr-nid-passport', 'entity_types' => json_encode(['hr-employee']), 'is_active' => true, 'sort_order' => 10, 'is_required' => true, 'expiry_applicable' => true, 'verification_required' => true],
            ['name' => 'Photograph', 'slug' => 'hr-photograph', 'entity_types' => json_encode(['hr-employee']), 'is_active' => true, 'sort_order' => 20, 'is_required' => true, 'expiry_applicable' => false, 'verification_required' => false],
            ['name' => 'CV / Resume', 'slug' => 'hr-cv-resume', 'entity_types' => json_encode(['hr-employee']), 'is_active' => true, 'sort_order' => 30, 'is_required' => true, 'expiry_applicable' => false, 'verification_required' => false],
            ['name' => 'Educational Certificate', 'slug' => 'hr-educational-certificate', 'entity_types' => json_encode(['hr-employee']), 'is_active' => true, 'sort_order' => 40, 'is_required' => false, 'expiry_applicable' => false, 'verification_required' => true],
            ['name' => 'Professional Certificate', 'slug' => 'hr-professional-certificate', 'entity_types' => json_encode(['hr-employee']), 'is_active' => true, 'sort_order' => 50, 'is_required' => false, 'expiry_applicable' => true, 'verification_required' => true],
            ['name' => 'Appointment Letter', 'slug' => 'hr-appointment-letter', 'entity_types' => json_encode(['hr-employee']), 'is_active' => true, 'sort_order' => 60, 'is_required' => true, 'expiry_applicable' => false, 'verification_required' => true],
            ['name' => 'Employment Contract', 'slug' => 'hr-employment-contract', 'entity_types' => json_encode(['hr-employee']), 'is_active' => true, 'sort_order' => 70, 'is_required' => true, 'expiry_applicable' => true, 'verification_required' => true],
            ['name' => 'Joining Documents', 'slug' => 'hr-joining-documents', 'entity_types' => json_encode(['hr-employee']), 'is_active' => true, 'sort_order' => 80, 'is_required' => false, 'expiry_applicable' => false, 'verification_required' => false],
            ['name' => 'Resignation Letter', 'slug' => 'hr-resignation-letter', 'entity_types' => json_encode(['hr-employee']), 'is_active' => true, 'sort_order' => 90, 'is_required' => false, 'expiry_applicable' => false, 'verification_required' => true],
            ['name' => 'Termination Letter', 'slug' => 'hr-termination-letter', 'entity_types' => json_encode(['hr-employee']), 'is_active' => true, 'sort_order' => 100, 'is_required' => false, 'expiry_applicable' => false, 'verification_required' => true],
            ['name' => 'Experience Letter', 'slug' => 'hr-experience-letter', 'entity_types' => json_encode(['hr-employee']), 'is_active' => true, 'sort_order' => 110, 'is_required' => false, 'expiry_applicable' => false, 'verification_required' => true],
            ['name' => 'Other HR Document', 'slug' => 'hr-other', 'entity_types' => json_encode(['hr-employee']), 'is_active' => true, 'sort_order' => 999, 'is_required' => false, 'expiry_applicable' => false, 'verification_required' => false],
        ];

        foreach ($categories as $cat) {
            $exists = DB::table('document_categories')->where('slug', $cat['slug'])->exists();
            if (! $exists) {
                DB::table('document_categories')->insert(array_merge($cat, [
                    'code' => $cat['slug'],
                    'description' => $cat['name'].' for HR employees',
                    'lifecycle_stage' => null,
                    'allowed_file_types' => null,
                    'max_file_size_kb' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    private function seedPermissions(): void
    {
        $permissionIds = DB::table('permissions')->pluck('id', 'slug');

        foreach (self::PERMISSIONS as $permission) {
            if (! $permissionIds->has($permission['slug'])) {
                DB::table('permissions')->insert($permission);
            }
        }

        $permissionIds = DB::table('permissions')->pluck('id', 'slug');
        $roleIds = DB::table('roles')->pluck('id', 'slug');

        $existing = DB::table('role_permissions')->get()->map(fn ($rp) => $rp->role_id.':'.$rp->permission_id)->all();
        $pairs = [];
        foreach (self::GRANTS as $roleSlug => $permissionSlugs) {
            $roleId = $roleIds[$roleSlug] ?? null;
            if ($roleId === null) {
                continue;
            }
            foreach ($permissionSlugs as $permissionSlug) {
                $permissionId = $permissionIds[$permissionSlug] ?? null;
                if ($permissionId === null) {
                    continue;
                }
                $key = $roleId.':'.$permissionId;
                if (! in_array($key, $existing, true)) {
                    $pairs[] = ['role_id' => $roleId, 'permission_id' => $permissionId];
                    $existing[] = $key;
                }
            }
        }

        if ($pairs !== []) {
            DB::table('role_permissions')->insert($pairs);
        }
    }

    public function down(): void
    {
        DB::table('document_categories')->whereIn('slug', [
            'hr-nid-passport','hr-photograph','hr-cv-resume','hr-educational-certificate',
            'hr-professional-certificate','hr-appointment-letter','hr-employment-contract',
            'hr-joining-documents','hr-resignation-letter','hr-termination-letter',
            'hr-experience-letter','hr-other',
        ])->delete();

        $permissionIds = DB::table('permissions')->whereIn('slug', ['hr.document.view','hr.document.manage','hr.document.verify'])->pluck('id')->all();
        if ($permissionIds !== []) {
            DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }
        DB::table('permissions')->whereIn('slug', ['hr.document.view','hr.document.manage','hr.document.verify'])->delete();

        if (Schema::hasColumn('documents', 'document_number')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropIndex('idx_documents_number');
                $table->dropColumn('document_number');
            });
        }
    }
};
