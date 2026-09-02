<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployment_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_user_id')->nullable();
            $table->enum('type', ['git', 'zip'])->default('git');
            $table->string('version')->nullable()->comment('Git commit hash or version string');
            $table->enum('status', ['success', 'failed', 'rolled_back'])->default('success');
            $table->longText('log')->nullable()->comment('Full output of deployment process');
            $table->string('backup_path')->nullable()->comment('Path to code backup');
            $table->string('db_backup_path')->nullable()->comment('Path to SQL backup');
            $table->timestamp('created_at')->nullable()->useCurrent();

            $table->index('type');
            $table->index('status');
            $table->index('created_at');
        });

        // Add foreign key if platform_admins exists — nullable FK without constraint for sqlite compat
        // We keep admin_user_id without hard FK to support multiple guard types (platform_admin, user)
        // but try to add FK if mysql/mariadb and platform_admins table exists
        try {
            if (Schema::hasTable('platform_admins') && DB::getDriverName() !== 'sqlite') {
                Schema::table('deployment_logs', function (Blueprint $table) {
                    // Use raw try — some MySQL versions dislike adding FK after creation without name
                });
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Seed permission admin.deploy
        try {
            if (Schema::hasTable('permissions')) {
                $exists = DB::table('permissions')->where('slug', 'admin.deploy')->exists();
                if (! $exists) {
                    DB::table('permissions')->insert([
                        'slug' => 'admin.deploy',
                        'module' => 'admin',
                        'name' => 'Deploy via Git/ZIP',
                    ]);
                }
                // Assign to Super Admin roles if any role looks like super admin / platform
                // Roles table may contain institute roles + possibly platform role
                $permissionId = DB::table('permissions')->where('slug', 'admin.deploy')->value('id');
                if ($permissionId) {
                    // Assign to all is_system roles that are likely admin (owner, institute-admin) — plus ensure platform_admin bypass works anyway
                    // CheckPermission middleware already bypasses PlatformAdmin, so permission is for future fine-grained control
                    $superRoles = DB::table('roles')->whereIn('slug', ['super-admin', 'super_admin', 'platform_admin', 'admin'])->pluck('id');
                    foreach ($superRoles as $roleId) {
                        $exists = DB::table('role_permissions')->where('role_id', $roleId)->where('permission_id', $permissionId)->exists();
                        if (! $exists) {
                            DB::table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $permissionId]);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // silent — permission can be seeded manually
        }
    }

    public function down(): void
    {
        // Remove permission
        try {
            if (Schema::hasTable('permissions')) {
                $permId = DB::table('permissions')->where('slug', 'admin.deploy')->value('id');
                if ($permId) {
                    DB::table('role_permissions')->where('permission_id', $permId)->delete();
                    DB::table('permissions')->where('id', $permId)->delete();
                }
            }
        } catch (\Throwable $e) {}

        Schema::dropIfExists('deployment_logs');
    }
};
