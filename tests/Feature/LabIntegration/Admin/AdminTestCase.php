<?php

namespace Tests\Feature\LabIntegration\Admin;

use App\Models\Institute;
use App\Models\LabIntegration\LabAnalyzer;
use App\Models\Membership;
use App\Models\Role;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Support\Workspace;
use Database\Seeders\LabAnalyzerPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Shared setup for lab analyzer admin UI tests.
 *
 * Institute rides the 'advanced' package (with an active subscription) so
 * the medical.laboratory module + feature gates pass; owner membership
 * satisfies the permission middleware (owner is super-user in-institute).
 */
abstract class AdminTestCase extends TestCase
{
    use DatabaseTransactions;

    protected Institute $institute;
    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        (new LabAnalyzerPermissionSeeder)->run();

        $pkg = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['advanced'])->firstOrFail();

        $instId = DB::table('institutes')->insertGetId([
            'name' => 'Lab Admin Test '.uniqid(),
            'slug' => 'lab-admin-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
            'package_id' => $pkg->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('institute_subscriptions')->insert([
            'institute_id' => $instId,
            'package_id' => $pkg->id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);

        $this->institute = Institute::withoutGlobalScopes()->find($instId);

        $this->owner = User::factory()->create([
            'account_type' => 'owner',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        Membership::create([
            'user_id' => $this->owner->id,
            'institution_id' => $this->institute->id,
            'role_id' => Role::where('slug', 'institute-owner')->value('id'),
            'status' => 'active',
        ]);

        $this->actingAs($this->owner, 'web');
        Workspace::set($this->institute->id);
    }

    protected function analyzer(array $overrides = []): LabAnalyzer
    {
        return LabAnalyzer::create(array_merge([
            'institute_id' => $this->institute->id,
            'code' => 'ADM-'.strtoupper(uniqid()),
            'name' => 'Admin Analyzer',
            'instrument_type' => 'hematology',
            'protocol' => 'astm',
            'adapter_key' => 'sysmex_xn',
            'adapter_version' => 'v1',
            'connection_type' => 'tcp',
            'host' => '127.0.0.1',
            'port' => 5000,
            'is_enabled' => true,
            'status' => 'active',
        ], $overrides));
    }
}
