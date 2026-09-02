<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = Schema::getColumnListing('students');

        Schema::table('students', function (Blueprint $table) use ($columns) {
            if (! in_array('created_by', $columns)) {
                $table->unsignedBigInteger('created_by')->nullable()->after('admission_assigned_user_id');
            }
            if (! in_array('approved_by', $columns)) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('created_by');
            }
            if (! in_array('approved_at', $columns)) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (! in_array('rejected_by', $columns)) {
                $table->unsignedBigInteger('rejected_by')->nullable()->after('approved_at');
            }
            if (! in_array('rejected_at', $columns)) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }
        });

        // Add indexes if not already present
        $indexes = array_column(DB::select("SHOW INDEX FROM students"), 'Key_name');
        Schema::table('students', function (Blueprint $table) use ($indexes) {
            if (! in_array('students_created_by_idx', $indexes)) {
                $table->index('created_by', 'students_created_by_idx');
            }
            if (! in_array('students_approved_by_idx', $indexes)) {
                $table->index('approved_by', 'students_approved_by_idx');
            }
            if (! in_array('students_rejected_by_idx', $indexes)) {
                $table->index('rejected_by', 'students_rejected_by_idx');
            }
        });

        // Seed admission.approve permission
        $permissionId = DB::table('permissions')->where('slug', 'admission.approve')->value('id');
        if ($permissionId === null) {
            $permissionId = DB::table('permissions')->insertGetId([
                'slug' => 'admission.approve',
                'name' => 'Admission Approve',
                'module' => 'education',
            ]);
        }

        // Grant to institute-owner, institute-admin, branch-manager
        $roleIds = DB::table('roles')
            ->whereIn('slug', ['institute-owner', 'institute-admin', 'branch-manager'])
            ->pluck('id')
            ->toArray();

        foreach ($roleIds as $roleId) {
            $exists = DB::table('role_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->exists();
            if (! $exists) {
                DB::table('role_permissions')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $indexes = array_column(DB::select("SHOW INDEX FROM students"), 'Key_name');
        Schema::table('students', function (Blueprint $table) use ($indexes) {
            if (in_array('students_created_by_idx', $indexes)) {
                $table->dropIndex('students_created_by_idx');
            }
            if (in_array('students_approved_by_idx', $indexes)) {
                $table->dropIndex('students_approved_by_idx');
            }
            if (in_array('students_rejected_by_idx', $indexes)) {
                $table->dropIndex('students_rejected_by_idx');
            }
            $table->dropColumn(['created_by', 'approved_by', 'approved_at', 'rejected_by', 'rejected_at']);
        });

        DB::table('role_permissions')->where('permission_id', function ($q) {
            $q->select('id')->from('permissions')->where('slug', 'admission.approve');
        })->delete();
        DB::table('permissions')->where('slug', 'admission.approve')->delete();
    }
};
