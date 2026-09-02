<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ExamTabsViewTest extends TestCase
{
    use DatabaseTransactions;

    private InstituteUser $staff;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();

        $institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        $role = Role::where('slug', 'institute-owner')->firstOrFail();
        $this->staff = InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => $role->id,
            'email' => 'tabs-owner@example.test',
            'phone' => '01700009999',
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);
    }

    public function test_exams_index_renders_both_tabs(): void
    {
        $this->actingAs($this->staff, 'institute_user')
            ->get('/exams')
            ->assertOk()
            ->assertSee('tab=exams', false)
            ->assertSee('tab=results', false)
            ->assertSee('name="batch_id"', false);

        $this->actingAs($this->staff, 'institute_user')
            ->get('/exams?tab=results')
            ->assertOk()
            ->assertSee('tab=exams', false)
            ->assertSee('tab=results', false)
            ->assertSee('name="result_batch_id"', false);
    }
}