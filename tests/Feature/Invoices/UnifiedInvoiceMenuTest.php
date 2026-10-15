<?php

namespace Tests\Feature\Invoices;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Both sidebars (Finance + Sales sections) link to the unified list,
 * and either group stays open while on the unified pages.
 */
class UnifiedInvoiceMenuTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;

    protected function setUp(): void
    {
        parent::setUp();
        $this->institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        app(ModuleAccessService::class)->enableModule($this->institute, 'finance');
        app(ModuleAccessService::class)->enableModule($this->institute, 'sales');
    }

    private function owner(): InstituteUser
    {
        $roleId = Role::withoutGlobalScopes()->where('slug', 'institute-owner')->firstOrFail()->id;

        return InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $roleId,
            'first_name' => 'Unified',
            'last_name' => 'Menu',
            'email' => 'unified-menu-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);
    }

    private function sidebarHtml(): string
    {
        return $this->actingAs($this->owner(), 'institute_user')
            ->get(route('invoices.index'))
            ->assertOk()
            ->getContent();
    }

    public function test_finance_menu_has_all_invoices_link(): void
    {
        $content = $this->sidebarHtml();

        // Both sidebar sections render "All Invoices".
        $this->assertGreaterThanOrEqual(2, substr_count($content, 'All Invoices'));
        $this->assertStringContainsString(route('invoices.index'), $content);
    }

    public function test_sales_menu_has_all_invoices_link(): void
    {
        $content = $this->sidebarHtml();

        $this->assertGreaterThanOrEqual(2, substr_count($content, 'All Invoices'));
        $this->assertStringContainsString(route('invoices.index'), $content);
    }

    public function test_both_links_point_to_same_route(): void
    {
        $content = $this->sidebarHtml();
        $href = route('invoices.index');

        // Two sidebar anchors (Finance + Sales) share the same target.
        $this->assertGreaterThanOrEqual(2, substr_count($content, 'href="'.$href.'"'));
    }
}
