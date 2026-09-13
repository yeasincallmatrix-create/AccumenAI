<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_registry', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('name', 100);
            $table->enum('type', ['core', 'industry'])->default('core');
            $table->string('description', 255)->nullable();
            $table->json('dependencies')->nullable()->comment('Array of module keys this module depends on');
            $table->tinyInteger('sort_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('package_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('subscription_packages')->cascadeOnDelete();
            $table->string('module_key', 60);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['package_id', 'module_key']);
        });

        Schema::create('institute_module_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
            $table->string('module_key', 60);
            $table->boolean('enabled');
            $table->unsignedBigInteger('overridden_by')->nullable()->comment('User or PlatformAdmin ID');
            $table->string('reason', 255)->nullable();
            $table->timestamps();
            $table->unique(['institute_id', 'module_key']);
        });

        Schema::create('module_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->nullable()->constrained()->nullOnDelete();
            $table->string('module_key', 60);
            $table->string('action', 60)->comment('enabled, disabled, override_added, override_removed, package_changed');
            $table->unsignedBigInteger('actor_id')->nullable()->comment('User or PlatformAdmin ID');
            $table->string('previous_state', 60)->nullable();
            $table->string('new_state', 60)->nullable();
            $table->foreignId('package_id')->nullable()->constrained('subscription_packages')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Seed the module registry with all known modules
        DB::table('module_registry')->insert([
            ['key' => 'crm',          'name' => 'CRM',          'type' => 'core',      'description' => 'Customer relationship management', 'dependencies' => null, 'sort_order' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'accounting',   'name' => 'Accounting',   'type' => 'core',      'description' => 'Financial accounting & ledger',      'dependencies' => null, 'sort_order' => 2, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'finance',      'name' => 'Finance',      'type' => 'core',      'description' => 'Finance management & invoicing',      'dependencies' => null, 'sort_order' => 3, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'inventory',    'name' => 'Inventory',    'type' => 'core',      'description' => 'Inventory & stock management',        'dependencies' => null, 'sort_order' => 4, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'hr',           'name' => 'HR',           'type' => 'core',      'description' => 'Human resources management',         'dependencies' => null, 'sort_order' => 5, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'sales',        'name' => 'Sales',        'type' => 'core',      'description' => 'Sales pipeline & quotes',            'dependencies' => null, 'sort_order' => 6, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'purchase',     'name' => 'Purchase',     'type' => 'core',      'description' => 'Purchase orders & procurement',      'dependencies' => null, 'sort_order' => 7, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'reports',      'name' => 'Reports',      'type' => 'core',      'description' => 'Analytics & reporting',              'dependencies' => null, 'sort_order' => 8, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'notifications','name' => 'Notifications','type' => 'core',      'description' => 'In-app & push notifications',       'dependencies' => null, 'sort_order' => 9, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'ai',           'name' => 'AI',           'type' => 'core',      'description' => 'AI assistant & tools',              'dependencies' => null, 'sort_order' => 10, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'education',    'name' => 'Education',    'type' => 'industry',  'description' => 'Education management (students, exams, results, certificates)', 'dependencies' => null, 'sort_order' => 20, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Seed default package-module mappings
        // Free: basic modules
        $freeId = DB::table('subscription_packages')->where('slug', 'free')->value('id');
        $starterId = DB::table('subscription_packages')->where('slug', 'starter')->value('id');
        $proId = DB::table('subscription_packages')->where('slug', 'professional')->value('id');
        $enterpriseId = DB::table('subscription_packages')->where('slug', 'enterprise')->value('id');

        $now = now();
        $inserts = [];

        if ($freeId) {
            foreach (['crm', 'notifications'] as $m) {
                $inserts[] = ['package_id' => $freeId, 'module_key' => $m, 'enabled' => true, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        if ($starterId) {
            foreach (['crm', 'finance', 'reports', 'notifications', 'education'] as $m) {
                $inserts[] = ['package_id' => $starterId, 'module_key' => $m, 'enabled' => true, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        if ($proId) {
            foreach (['crm', 'finance', 'accounting', 'reports', 'notifications', 'ai', 'education', 'sales'] as $m) {
                $inserts[] = ['package_id' => $proId, 'module_key' => $m, 'enabled' => true, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        if ($enterpriseId) {
            foreach (['crm', 'finance', 'accounting', 'inventory', 'hr', 'reports', 'notifications', 'ai', 'education', 'sales', 'purchase'] as $m) {
                $inserts[] = ['package_id' => $enterpriseId, 'module_key' => $m, 'enabled' => true, 'created_at' => $now, 'updated_at' => $now];
            }
        }

        if ($inserts) {
            DB::table('package_modules')->insert($inserts);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('module_access_logs');
        Schema::dropIfExists('institute_module_overrides');
        Schema::dropIfExists('package_modules');
        Schema::dropIfExists('module_registry');
    }
};
