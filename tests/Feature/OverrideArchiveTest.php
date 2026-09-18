<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteModuleOverride;
use App\Models\ModuleRegistry;
use App\Models\PlatformAdmin;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OverrideArchiveTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('institute_module_overrides_archive')) {
            Schema::create('institute_module_overrides_archive', function ($table) {
                $table->id();
                $table->unsignedBigInteger('original_id');
                $table->unsignedBigInteger('institute_id');
                $table->string('module_key', 60);
                $table->tinyInteger('enabled');
                $table->unsignedBigInteger('overridden_by')->nullable();
                $table->string('reason', 255)->nullable();
                $table->timestamp('archived_at')->useCurrent();
                $table->unsignedBigInteger('archived_by')->nullable();
                $table->string('archive_reason', 60)->nullable();
                $table->unsignedBigInteger('old_package_id')->nullable();
                $table->unsignedBigInteger('new_package_id')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index('institute_id', 'idx_institute');
                $table->index('archived_at', 'idx_archived_at');
            });
        }
    }

    private function institute(int $packageId): Institute
    {
        // Use 'other' industry to avoid syncIndustryModule creating overrides
        return Institute::create([
            'name' => 'Archive '.uniqid(),
            'slug' => 'archive-'.uniqid(),
            'status' => 'active',
            'package_id' => $packageId,
            'industry' => 'other',
            'sub_industry' => null,
            'country' => 'Bangladesh',
        ]);
    }

    private function platformAdmin(): PlatformAdmin
    {
        return PlatformAdmin::firstOrReuseForTests([
            'email' => 'archive-admin-'.uniqid().'@test.local',
            'password_hash' => bcrypt('secret'),
            'status' => 'active',
        ]);
    }

    private function ensureModule(string $key): void
    {
        ModuleRegistry::updateOrCreate(
            ['key' => $key],
            ['name' => ucfirst($key), 'status' => 'active']
        );
    }

    private function createOverride(Institute $inst, string $moduleKey, bool $enabled = true, ?int $userId = null): InstituteModuleOverride
    {
        return InstituteModuleOverride::create([
            'institute_id'  => $inst->id,
            'module_key'    => $moduleKey,
            'enabled'       => $enabled,
            'overridden_by' => $userId,
            'reason'        => 'test override',
        ]);
    }

    private function archiveTable(): string
    {
        return 'institute_module_overrides_archive';
    }

    private function archiveCount(Institute $inst): int
    {
        return DB::table($this->archiveTable())
            ->where('institute_id', $inst->id)
            ->count();
    }

    private function liveCount(Institute $inst): int
    {
        return InstituteModuleOverride::where('institute_id', $inst->id)->count();
    }

    // ───────────────────────────────────────────────────────────
    // 1. changePackage() archives with archive_reason='package_change'
    // ───────────────────────────────────────────────────────────
    public function test_change_package_archives_overrides_with_correct_reason(): void
    {
        $this->ensureModule('hr');
        $this->ensureModule('crm');
        $this->ensureModule('accounting');

        $free = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['free'])->firstOrFail();
        $basic = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['basic'])->firstOrFail();

        $inst = $this->institute($free->id);
        $admin = $this->platformAdmin();

        // Create 3 overrides for non-industry modules
        $o1 = $this->createOverride($inst, 'hr', true, $admin->id);
        $o2 = $this->createOverride($inst, 'crm', false, $admin->id);
        $o3 = $this->createOverride($inst, 'accounting', true, $admin->id);

        $this->assertEquals(3, $this->liveCount($inst));
        $this->assertEquals(0, $this->archiveCount($inst));

        // Change package — should archive all 3 overrides
        app(ModuleAccessService::class)->changePackage(
            $inst, $free->id, $basic->id, $admin->id
        );

        // Live table should be empty for this institute
        $this->assertEquals(0, $this->liveCount($inst));

        // Archive table should have 3 rows
        $this->assertEquals(3, $this->archiveCount($inst));

        // Each archive row carries correct metadata
        $archives = DB::table($this->archiveTable())
            ->where('institute_id', $inst->id)
            ->get();

        foreach ($archives as $arch) {
            $this->assertEquals('package_change', $arch->archive_reason);
            $this->assertEquals($admin->id, $arch->archived_by);
            $this->assertEquals($free->id, $arch->old_package_id);
            $this->assertEquals($basic->id, $arch->new_package_id);
            $this->assertNotNull($arch->archived_at);
        }

        // Verify original_id, module_key, enabled preserved
        $moduleKeys = $archives->pluck('module_key')->sort()->values()->toArray();
        $this->assertEquals(['accounting', 'crm', 'hr'], $moduleKeys);

        $hrArchive = $archives->firstWhere('module_key', 'hr');
        $this->assertEquals($o1->id, $hrArchive->original_id);
        $this->assertEquals(1, $hrArchive->enabled);

        $crmArchive = $archives->firstWhere('module_key', 'crm');
        $this->assertEquals($o2->id, $crmArchive->original_id);
        $this->assertEquals(0, $crmArchive->enabled);
    }

    // ───────────────────────────────────────────────────────────
    // 2. Empty case: no overrides → no error, returns 0
    // ───────────────────────────────────────────────────────────
    public function test_change_package_with_no_overrides_does_nothing(): void
    {
        $free = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['free'])->firstOrFail();
        $basic = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['basic'])->firstOrFail();

        $inst = $this->institute($free->id);
        $admin = $this->platformAdmin();

        $this->assertEquals(0, $this->liveCount($inst));

        app(ModuleAccessService::class)->changePackage(
            $inst, $free->id, $basic->id, $admin->id
        );

        $this->assertEquals(0, $this->liveCount($inst));
        $this->assertEquals(0, $this->archiveCount($inst));
    }

    // ───────────────────────────────────────────────────────────
    // 3. Archive does not shadow new overrides
    // ───────────────────────────────────────────────────────────
    public function test_archived_overrides_do_not_shadow_new_overrides(): void
    {
        $this->ensureModule('hr');

        $free = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['free'])->firstOrFail();
        $basic = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['basic'])->firstOrFail();

        $inst = $this->institute($free->id);
        $admin = $this->platformAdmin();

        // Create an override for 'hr' (enabled=true), then archive it
        $this->createOverride($inst, 'hr', true, $admin->id);
        app(ModuleAccessService::class)->changePackage(
            $inst, $free->id, $basic->id, $admin->id
        );

        $this->assertEquals(1, $this->archiveCount($inst));

        // Now create a NEW override for the same module_key (disabled)
        $this->createOverride($inst, 'hr', false, $admin->id);

        $this->assertEquals(1, $this->liveCount($inst));
        $this->assertEquals(1, $this->archiveCount($inst));

        // The new override should be the one in resolveEnabled()
        $service = app(ModuleAccessService::class);
        $enabled = $service->resolveEnabled($inst);
        $this->assertArrayHasKey('hr', $enabled);
        // New override is disabled, so 'hr' should be false
        $this->assertFalse($enabled['hr'], 'New override (disabled) should shadow the archived one');
    }

    // ───────────────────────────────────────────────────────────
    // 4. Archive preserves overridden_by and reason
    // ───────────────────────────────────────────────────────────
    public function test_archive_preserves_overridden_by_and_reason(): void
    {
        $this->ensureModule('hr');

        $free = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['free'])->firstOrFail();
        $basic = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['basic'])->firstOrFail();

        $inst = $this->institute($free->id);
        $admin = $this->platformAdmin();

        $this->createOverride($inst, 'hr', true, $admin->id);

        app(ModuleAccessService::class)->changePackage(
            $inst, $free->id, $basic->id, $admin->id
        );

        $archived = DB::table($this->archiveTable())
            ->where('institute_id', $inst->id)
            ->where('module_key', 'hr')
            ->first();

        $this->assertEquals($admin->id, $archived->overridden_by);
        $this->assertEquals('test override', $archived->reason);
    }
}
