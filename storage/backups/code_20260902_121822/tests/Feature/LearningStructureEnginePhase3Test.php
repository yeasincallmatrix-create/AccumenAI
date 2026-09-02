<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Membership;
use App\Models\StructureNode;
use App\Models\StructureTemplate;
use App\Models\User;
use App\Services\AcademicStructureService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LearningStructureEnginePhase3Test extends TestCase
{
    private function makeInstitute(array $overrides = []): Institute
    {
        $country = Country::first() ?? Country::create(['name' => 'Bangladesh', 'iso2' => 'BD', 'status' => true]);
        $defaults = [
            'name' => 'Phase3 ' . uniqid(),
            'slug' => 'p3-' . uniqid(),
            'country_id' => $country->id,
            'industry' => 'education',
            'sub_industry' => 'school',
            'country' => $country->name,
        ];
        return Institute::create(array_merge($defaults, $overrides));
    }

    private function makeOwnerWithMembership(Institute $institute): InstituteUser
    {
        $role = \App\Models\Role::where('slug', 'institute-owner')->first();
        if (! $role) {
            $role = \App\Models\Role::first();
        }
        if (! $role) {
            $role = \App\Models\Role::create(['name' => 'Institute Owner', 'slug' => 'institute-owner', 'status' => 'active']);
        }
        $uid = uniqid();
        return InstituteUser::withoutGlobalScope('institute')->create([
            'institute_id' => $institute->id,
            'role_id' => $role->id,
            'first_name' => 'Owner',
            'last_name' => $uid,
            'email' => 'owner' . $uid . '@test.com',
            'phone' => '01' . substr(str_replace('.', '', microtime(true)), -9),
            'password_hash' => 'password',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    public function test_authenticated_institute_can_load_structure_options(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'university']);
        $user = $this->makeOwnerWithMembership($inst);
        $this->actingAs($user, 'institute_user');
        TenantContext::set($inst->id);
        $res = $this->get(route('academic.structure.options'));
        TenantContext::clear();
        $res->assertStatus(200);
        $res->assertJson(['success' => true]);
        $json = $res->json('data');
        $this->assertEquals('university', $json['template']['code']);
        $this->assertCount(4, $json['levels']);
    }

    public function test_unauthenticated_user_denied(): void
    {
        $res = $this->get(route('academic.structure.options'));
        // Should redirect to login (guest)
        $this->assertTrue(in_array($res->status(), [302, 401]));
    }

    public function test_correct_template_returned_for_school(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'school']);
        $user = $this->makeOwnerWithMembership($inst);
        $this->actingAs($user, 'institute_user');
        TenantContext::set($inst->id);
        $res = $this->get(route('academic.structure.options'));
        TenantContext::clear();
        $res->assertStatus(200);
        $this->assertEquals('school', $res->json('data.template.code'));
        $this->assertEquals(2, count($res->json('data.levels')));
    }

    public function test_correct_n_level_metadata_returned_for_training_institute(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'computer_it_training_institute']);
        $user = $this->makeOwnerWithMembership($inst);
        $this->actingAs($user, 'institute_user');
        TenantContext::set($inst->id);
        $res = $this->get(route('academic.structure.options'));
        TenantContext::clear();
        $this->assertEquals('training_institute', $res->json('data.template.code'));
        $levels = $res->json('data.levels');
        $this->assertCount(2, $levels);
        $this->assertEquals('Course', $levels[0]['label']);
        $this->assertEquals('Batch', $levels[1]['label']);
    }

    public function test_level_1_nodes_returned(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'school']);
        $user = $this->makeOwnerWithMembership($inst);
        $this->actingAs($user, 'institute_user');
        TenantContext::set($inst->id);
        // Create nodes via service
        $svc = app(\App\Services\LearningStructureService::class);
        $n1 = $svc->createNode($inst, ['level_order' => 1, 'name' => 'Class 1']);
        $n2 = $svc->createNode($inst, ['level_order' => 1, 'name' => 'Class 2']);
        $res = $this->getJson(route('academic.structure.nodes', ['level_order' => 1]));
        TenantContext::clear();
        $res->assertStatus(200);
        $res->assertJson(['success' => true]);
        $ids = collect($res->json('data.options'))->pluck('id')->all();
        $this->assertContains($n1->id, $ids);
        $this->assertContains($n2->id, $ids);
    }

    public function test_child_nodes_returned_for_valid_parent(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'school']);
        $user = $this->makeOwnerWithMembership($inst);
        $this->actingAs($user, 'institute_user');
        TenantContext::set($inst->id);
        $svc = app(\App\Services\LearningStructureService::class);
        $parent = $svc->createNode($inst, ['level_order' => 1, 'name' => 'Class 1']);
        $child = $svc->createNode($inst, ['level_order' => 2, 'name' => 'Sec A', 'parent_node_id' => $parent->id]);
        $res = $this->getJson(route('academic.structure.nodes', ['level_order' => 2, 'parent_node_id' => $parent->id]));
        TenantContext::clear();
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.options'));
        $this->assertEquals($child->id, $res->json('data.options.0.id'));
    }

    public function test_invalid_parent_rejected(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'school']);
        $user = $this->makeOwnerWithMembership($inst);
        $this->actingAs($user, 'institute_user');
        TenantContext::set($inst->id);
        $res = $this->getJson(route('academic.structure.nodes', ['level_order' => 2, 'parent_node_id' => 999999]));
        TenantContext::clear();
        $res->assertStatus(422);
    }

    public function test_cross_tenant_parent_rejected(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $a = $this->makeInstitute(['sub_industry' => 'school']);
        $b = $this->makeInstitute(['sub_industry' => 'school']);
        $userB = $this->makeOwnerWithMembership($b);
        $this->actingAs($userB, 'institute_user');
        TenantContext::set($a->id);
        $svc = app(\App\Services\LearningStructureService::class);
        $parentA = $svc->createNode($a, ['level_order' => 1, 'name' => 'Class A']);
        TenantContext::clear();
        TenantContext::set($b->id);
        $res = $this->getJson(route('academic.structure.nodes', ['level_order' => 2, 'parent_node_id' => $parentA->id]));
        TenantContext::clear();
        $res->assertStatus(422);
    }

    public function test_cross_tenant_node_never_returned(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $a = $this->makeInstitute(['sub_industry' => 'school']);
        $b = $this->makeInstitute(['sub_industry' => 'school']);
        TenantContext::set($a->id);
        $svc = app(\App\Services\LearningStructureService::class);
        $nodeA = $svc->createNode($a, ['level_order' => 1, 'name' => 'Class A']);
        TenantContext::clear();
        $userB = $this->makeOwnerWithMembership($b);
        $this->actingAs($userB, 'institute_user');
        TenantContext::set($b->id);
        $res = $this->getJson(route('academic.structure.nodes', ['level_order' => 1]));
        TenantContext::clear();
        $ids = collect($res->json('data.options'))->pluck('id')->all();
        $this->assertNotContains($nodeA->id, $ids);
    }

    public function test_cross_branch_node_rejected(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'school']);
        $b1 = Branch::withoutGlobalScope('institute')->create(['institute_id' => $inst->id, 'name' => 'B1', 'status' => 'active']);
        $b2 = Branch::withoutGlobalScope('institute')->create(['institute_id' => $inst->id, 'name' => 'B2', 'status' => 'active']);
        $user = $this->makeOwnerWithMembership($inst);
        $this->actingAs($user, 'institute_user');
        TenantContext::set($inst->id);
        $svc = app(\App\Services\LearningStructureService::class);
        $shared = $svc->createNode($inst, ['level_order' => 1, 'name' => 'Shared']);
        $b1Child = $svc->createNode($inst, ['level_order' => 2, 'name' => 'Sec B1', 'parent_node_id' => $shared->id, 'branch_id' => $b1->id]);
        // Request with b2 branch trying to fetch children of b1Child as parent? Actually nodes endpoint validates parent belongs to same template and exists, but branch check: if we request level 2 with parent=b1Child while branch=b2, should be rejected?
        // Our endpoint validates parent branch vs effective branch.
        $res = $this->getJson(route('academic.structure.nodes', ['level_order' => 2, 'parent_node_id' => $b1Child->id, 'branch_id' => $b2->id]));
        TenantContext::clear();
        // Should be 403 or 422 depending on implementation — we return 403 for cross-branch parent
        $this->assertTrue(in_array($res->status(), [403, 422]));
    }

    public function test_shared_branch_node_works(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'school']);
        $branch = Branch::withoutGlobalScope('institute')->create(['institute_id' => $inst->id, 'name' => 'Br', 'status' => 'active']);
        $user = $this->makeOwnerWithMembership($inst);
        $this->actingAs($user, 'institute_user');
        TenantContext::set($inst->id);
        $svc = app(\App\Services\LearningStructureService::class);
        $shared = $svc->createNode($inst, ['level_order' => 1, 'name' => 'Shared Class']);
        TenantContext::clear();
        TenantContext::set($inst->id);
        $res = $this->getJson(route('academic.structure.nodes', ['level_order' => 1, 'branch_id' => $branch->id]));
        TenantContext::clear();
        $ids = collect($res->json('data.options'))->pluck('id')->all();
        $this->assertContains($shared->id, $ids);
    }

    public function test_invalid_level_rejected(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'school']);
        $user = $this->makeOwnerWithMembership($inst);
        $this->actingAs($user, 'institute_user');
        TenantContext::set($inst->id);
        $res = $this->getJson(route('academic.structure.nodes', ['level_order' => 99]));
        TenantContext::clear();
        $res->assertStatus(422);
    }

    public function test_skipped_level_parent_rejected(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'university']);
        $user = $this->makeOwnerWithMembership($inst);
        $this->actingAs($user, 'institute_user');
        TenantContext::set($inst->id);
        $svc = app(\App\Services\LearningStructureService::class);
        $n1 = $svc->createNode($inst, ['level_order' => 1, 'name' => 'Fac']);
        TenantContext::clear();
        TenantContext::set($inst->id);
        // Try to get level 3 with parent at level 1 (should be level 2)
        $res = $this->getJson(route('academic.structure.nodes', ['level_order' => 3, 'parent_node_id' => $n1->id]));
        TenantContext::clear();
        $res->assertStatus(422);
    }

    public function test_endpoint_returns_children_correctly(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'school']);
        $user = $this->makeOwnerWithMembership($inst);
        $this->actingAs($user, 'institute_user');
        TenantContext::set($inst->id);
        $svc = app(\App\Services\LearningStructureService::class);
        $p = $svc->createNode($inst, ['level_order' => 1, 'name' => 'Class 1']);
        $c = $svc->createNode($inst, ['level_order' => 2, 'name' => 'Sec A', 'parent_node_id' => $p->id]);
        $res = $this->getJson(route('academic.structure.nodes', ['level_order' => 2, 'parent_node_id' => $p->id]));
        TenantContext::clear();
        $res->assertStatus(200);
        $this->assertEquals('Section', $res->json('data.label'));
        $this->assertCount(1, $res->json('data.options'));
    }

    public function test_empty_children_handled(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'school']);
        $user = $this->makeOwnerWithMembership($inst);
        $this->actingAs($user, 'institute_user');
        TenantContext::set($inst->id);
        $svc = app(\App\Services\LearningStructureService::class);
        $p = $svc->createNode($inst, ['level_order' => 1, 'name' => 'Class X']);
        $res = $this->getJson(route('academic.structure.nodes', ['level_order' => 2, 'parent_node_id' => $p->id]));
        TenantContext::clear();
        $res->assertStatus(200);
        $this->assertCount(0, $res->json('data.options'));
    }

    public function test_inactive_nodes_excluded(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'school']);
        $user = $this->makeOwnerWithMembership($inst);
        $this->actingAs($user, 'institute_user');
        TenantContext::set($inst->id);
        $svc = app(\App\Services\LearningStructureService::class);
        $n = $svc->createNode($inst, ['level_order' => 1, 'name' => 'Inactive', 'status' => false]);
        $res = $this->getJson(route('academic.structure.nodes', ['level_order' => 1]));
        TenantContext::clear();
        $ids = collect($res->json('data.options'))->pluck('id')->all();
        $this->assertNotContains($n->id, $ids);
    }

    public function test_existing_academic_structure_service_still_works(): void
    {
        $svc = app(AcademicStructureService::class);
        $this->assertTrue(method_exists($svc, 'resolve'));
        $inst = $this->makeInstitute(['sub_industry' => 'school']);
        $result = $svc->resolve($inst);
        $this->assertArrayHasKey('systems', $result);
    }
}


