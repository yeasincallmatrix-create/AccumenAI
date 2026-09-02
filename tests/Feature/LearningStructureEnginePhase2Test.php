<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\IndustryTemplateMapping;
use App\Models\Institute;
use App\Models\InstituteSetting;
use App\Models\StructureNode;
use App\Models\StructureTemplate;
use App\Models\StructureTemplateLevel;
use App\Services\AcademicStructureService;
use App\Services\LearningStructureResolver;
use App\Services\LearningStructureService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LearningStructureEnginePhase2Test extends TestCase
{
    // No RefreshDatabase — monetix_test was seeded via schema import; we clean manually per test

    private function makeInstitute(array $overrides = []): Institute
    {
        $country = Country::first() ?? Country::create(['name' => 'Bangladesh', 'iso2' => 'BD', 'status' => true]);
        $defaults = [
            'name' => 'Test Institute ' . uniqid(),
            'slug' => 'test-' . uniqid(),
            'country_id' => $country->id,
            'industry' => 'education',
            'sub_industry' => 'school',
            'country' => $country->name,
        ];
        return Institute::create(array_merge($defaults, $overrides));
    }

    private function makeBranch(Institute $institute): Branch
    {
        return Branch::withoutGlobalScope('institute')->create([
            'institute_id' => $institute->id,
            'name' => 'Branch ' . uniqid(),
            'status' => 'active',
        ]);
    }

    // ---------- Template resolution ----------

    public function test_school_resolves_to_school_template(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'school']);
        $tpl = app(LearningStructureResolver::class)->resolveTemplate($inst)['template'];
        $this->assertEquals('school', $tpl->code);
    }

    public function test_college_resolves(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'college']);
        $this->assertEquals('college', app(LearningStructureResolver::class)->resolveTemplate($inst)['template']->code);
    }

    public function test_university_resolves(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'university']);
        $this->assertEquals('university', app(LearningStructureResolver::class)->resolveTemplate($inst)['template']->code);
    }

    public function test_training_institute_resolves(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'computer_it_training_institute']);
        $this->assertEquals('training_institute', app(LearningStructureResolver::class)->resolveTemplate($inst)['template']->code);
    }

    public function test_martial_arts_resolves(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'martial_arts']);
        $this->assertEquals('martial_arts_belt', app(LearningStructureResolver::class)->resolveTemplate($inst)['template']->code);
    }

    public function test_dance_academy_resolves(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'dance_academy']);
        $this->assertEquals('dance_academy', app(LearningStructureResolver::class)->resolveTemplate($inst)['template']->code);
    }

    public function test_music_academy_resolves(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'music_academy']);
        $this->assertEquals('music_academy', app(LearningStructureResolver::class)->resolveTemplate($inst)['template']->code);
    }

    public function test_sports_academy_resolves(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'sports_academy']);
        $this->assertEquals('sports_academy', app(LearningStructureResolver::class)->resolveTemplate($inst)['template']->code);
    }

    public function test_language_academy_resolves(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'language_academy']);
        $this->assertEquals('language_academy', app(LearningStructureResolver::class)->resolveTemplate($inst)['template']->code);
    }

    public function test_coaching_center_resolves(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'coaching_centre']);
        $this->assertEquals('coaching_center', app(LearningStructureResolver::class)->resolveTemplate($inst)['template']->code);
    }

    public function test_country_specific_mapping_overrides_global(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $country = Country::first();
        $otherTpl = StructureTemplate::where('code', 'college')->first();
        // Ensure clean state
        IndustryTemplateMapping::where('country_id', $country->id)->where('sub_industry', 'school')->delete();
        $map = IndustryTemplateMapping::create([
            'industry' => 'education', 'sub_industry' => 'school', 'country_id' => $country->id,
            'structure_template_id' => $otherTpl->id, 'priority' => 5, 'status' => true,
        ]);
        $inst = $this->makeInstitute(['country_id' => $country->id, 'sub_industry' => 'school']);
        $tpl = app(LearningStructureResolver::class)->resolveTemplate($inst)['template'];
        $this->assertEquals('college', $tpl->code, 'country specific should override global');
        $map->delete();
    }

    public function test_explicit_structure_template_id_overrides_mapping(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'school']);
        $university = StructureTemplate::where('code', 'university')->first();
        InstituteSetting::withoutGlobalScope('institute')->updateOrCreate(['institute_id' => $inst->id], ['structure_template_id' => $university->id]);
        $inst->refresh();
        $tpl = app(LearningStructureResolver::class)->resolveTemplate($inst)['template'];
        $this->assertEquals('university', $tpl->code);
        $this->assertEquals('explicit', app(LearningStructureResolver::class)->resolveTemplate($inst)['source']);
    }

    public function test_null_template_falls_back_to_mapping(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'school']);
        $this->assertNull($inst->settings);
        $tpl = app(LearningStructureResolver::class)->resolveTemplate($inst)['template'];
        $this->assertEquals('school', $tpl->code);
        $this->assertEquals('mapping', app(LearningStructureResolver::class)->resolveTemplate($inst)['source']);
    }

    public function test_university_4_level_hierarchy_resolves(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'university']);
        $svc = app(LearningStructureService::class);
        $tpl = StructureTemplate::where('code', 'university')->first();
        $levels = $tpl->levels()->orderBy('level_order')->get();
        // Create chain: Faculty -> Department -> Program -> Semester
        TenantContext::set($inst->id);
        $n1 = $svc->createNode($inst, ['template_id' => $tpl->id, 'level_order' => 1, 'name' => 'Science']);
        $n2 = $svc->createNode($inst, ['template_id' => $tpl->id, 'level_order' => 2, 'name' => 'CS', 'parent_node_id' => $n1->id]);
        $n3 = $svc->createNode($inst, ['template_id' => $tpl->id, 'level_order' => 3, 'name' => 'BSc', 'parent_node_id' => $n2->id]);
        $n4 = $svc->createNode($inst, ['template_id' => $tpl->id, 'level_order' => 4, 'name' => 'Sem 1', 'parent_node_id' => $n3->id]);
        $resolved = app(LearningStructureResolver::class)->resolve($inst);
        $this->assertCount(4, $resolved['levels']);
        $this->assertEquals('Faculty', $resolved['levels'][0]['label']);
        $this->assertEquals('Semester', $resolved['levels'][3]['label']);
        // Check tree
        $tree = $resolved['levels'][0]['nodes'];
        $this->assertCount(1, $tree);
        $this->assertEquals($n1->id, $tree[0]['id']);
        $this->assertCount(1, $tree[0]['children']);
        $this->assertEquals($n2->id, $tree[0]['children'][0]['id']);
        TenantContext::clear();
    }

    public function test_parent_child_hierarchy_resolves_correctly(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'school']);
        $tpl = StructureTemplate::where('code', 'school')->first();
        TenantContext::set($inst->id);
        $svc = app(LearningStructureService::class);
        $c1 = $svc->createNode($inst, ['level_order' => 1, 'name' => 'Class 1']);
        $sA = $svc->createNode($inst, ['level_order' => 2, 'name' => 'Sec A', 'parent_node_id' => $c1->id]);
        $nodes = app(LearningStructureResolver::class)->getNodesForLevel($inst, 2, $c1->id);
        $this->assertCount(1, $nodes);
        $this->assertEquals($sA->id, $nodes->first()->id);
        TenantContext::clear();
    }

    public function test_invalid_skipped_level_parent_is_rejected(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'university']);
        $tpl = StructureTemplate::where('code', 'university')->first();
        TenantContext::set($inst->id);
        $svc = app(LearningStructureService::class);
        $n1 = $svc->createNode($inst, ['template_id' => $tpl->id, 'level_order' => 1, 'name' => 'Fac']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $svc->createNode($inst, ['template_id' => $tpl->id, 'level_order' => 3, 'name' => 'Program', 'parent_node_id' => $n1->id]);
        TenantContext::clear();
    }

    public function test_circular_parent_relationship_is_rejected(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'school']);
        TenantContext::set($inst->id);
        $svc = app(LearningStructureService::class);
        $c1 = $svc->createNode($inst, ['level_order' => 1, 'name' => 'Class 1']);
        $s1 = $svc->createNode($inst, ['level_order' => 2, 'name' => 'Sec A', 'parent_node_id' => $c1->id]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $svc->updateNode($inst, $c1->id, ['parent_node_id' => $s1->id]);
        TenantContext::clear();
    }

    public function test_institute_a_cannot_read_institute_b_nodes(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $a = $this->makeInstitute(['sub_industry' => 'school']);
        $b = $this->makeInstitute(['sub_industry' => 'school']);
        TenantContext::set($a->id);
        $svc = app(LearningStructureService::class);
        $nodeA = $svc->createNode($a, ['level_order' => 1, 'name' => 'Class A']);
        TenantContext::clear();
        TenantContext::set($b->id);
        $this->assertNull($svc->getNode($b, $nodeA->id));
        $nodesB = $svc->getNodes($b);
        $this->assertCount(0, $nodesB);
        TenantContext::clear();
    }

    public function test_institute_a_cannot_use_institute_b_parent(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $a = $this->makeInstitute(['sub_industry' => 'school']);
        $b = $this->makeInstitute(['sub_industry' => 'school']);
        TenantContext::set($a->id);
        $svc = app(LearningStructureService::class);
        $parentA = $svc->createNode($a, ['level_order' => 1, 'name' => 'Class A']);
        TenantContext::clear();
        TenantContext::set($b->id);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $svc->createNode($b, ['level_order' => 2, 'name' => 'Sec', 'parent_node_id' => $parentA->id]);
        TenantContext::clear();
    }

    public function test_institute_a_cannot_use_institute_b_private_template(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $a = $this->makeInstitute(['sub_industry' => 'school']);
        $b = $this->makeInstitute(['sub_industry' => 'school']);
        $private = StructureTemplate::create(['name' => 'Private', 'code' => 'private_' . uniqid(), 'is_global' => false, 'institute_id' => $a->id, 'status' => true]);
        StructureTemplateLevel::create(['template_id' => $private->id, 'level_order' => 1, 'label' => 'X', 'label_key' => 'class', 'required' => true, 'has_values' => true]);
        $svc = app(LearningStructureService::class);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $svc->assignTemplateToInstitute($b, $private);
    }

    public function test_shared_nodes_visible_to_branch(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'school']);
        $branch = $this->makeBranch($inst);
        TenantContext::set($inst->id);
        $svc = app(LearningStructureService::class);
        $shared = $svc->createNode($inst, ['level_order' => 1, 'name' => 'Shared Class']);
        $this->assertNull($shared->branch_id);
        $nodes = app(LearningStructureResolver::class)->resolve($inst, $branch->id)['levels'][0]['nodes'];
        $ids = collect($nodes)->pluck('id')->all();
        $this->assertContains($shared->id, $ids);
        TenantContext::clear();
    }

    public function test_branch_specific_nodes_visible_only_to_that_branch(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'school']);
        $b1 = $this->makeBranch($inst);
        $b2 = $this->makeBranch($inst);
        TenantContext::set($inst->id);
        $svc = app(LearningStructureService::class);
        $n1 = $svc->createNode($inst, ['level_order' => 1, 'name' => 'B1 Class', 'branch_id' => $b1->id]);
        $resB1 = app(LearningStructureResolver::class)->resolve($inst, $b1->id);
        $resB2 = app(LearningStructureResolver::class)->resolve($inst, $b2->id);
        $idsB1 = collect($resB1['levels'][0]['nodes'])->pluck('id')->all();
        $idsB2 = collect($resB2['levels'][0]['nodes'])->pluck('id')->all();
        $this->assertContains($n1->id, $idsB1);
        $this->assertNotContains($n1->id, $idsB2);
        TenantContext::clear();
    }

    public function test_cross_branch_node_access_rejected(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'school']);
        $b1 = $this->makeBranch($inst);
        $b2 = $this->makeBranch($inst);
        TenantContext::set($inst->id);
        $svc = app(LearningStructureService::class);
        $shared = $svc->createNode($inst, ['level_order' => 1, 'name' => 'Shared']);
        $b1Node = $svc->createNode($inst, ['level_order' => 2, 'name' => 'Sec', 'parent_node_id' => $shared->id, 'branch_id' => $b1->id]);
        // b2 trying to use b1's node as parent should fail via branch check
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $svc->createNode($inst, ['level_order' => 2, 'name' => 'CrossChild', 'parent_node_id' => $b1Node->id, 'branch_id' => $b2->id]);
        TenantContext::clear();
    }

    public function test_create_root_node(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'school']);
        TenantContext::set($inst->id);
        $node = app(LearningStructureService::class)->createNode($inst, ['level_order' => 1, 'name' => 'Class 5']);
        $this->assertEquals(1, $node->level_order);
        $this->assertNull($node->parent_node_id);
        TenantContext::clear();
    }

    public function test_create_valid_child(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'school']);
        TenantContext::set($inst->id);
        $svc = app(LearningStructureService::class);
        $p = $svc->createNode($inst, ['level_order' => 1, 'name' => 'Class 1']);
        $c = $svc->createNode($inst, ['level_order' => 2, 'name' => 'Sec A', 'parent_node_id' => $p->id]);
        $this->assertEquals($p->id, $c->parent_node_id);
        TenantContext::clear();
    }

    public function test_update_node(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'school']);
        TenantContext::set($inst->id);
        $svc = app(LearningStructureService::class);
        $n = $svc->createNode($inst, ['level_order' => 1, 'name' => 'Old']);
        $updated = $svc->updateNode($inst, $n->id, ['name' => 'New']);
        $this->assertEquals('New', $updated->name);
        TenantContext::clear();
    }

    public function test_move_node(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'school']);
        TenantContext::set($inst->id);
        $svc = app(LearningStructureService::class);
        $c1 = $svc->createNode($inst, ['level_order' => 1, 'name' => 'Class 1']);
        $c2 = $svc->createNode($inst, ['level_order' => 1, 'name' => 'Class 2']);
        $s = $svc->createNode($inst, ['level_order' => 2, 'name' => 'Sec', 'parent_node_id' => $c1->id]);
        $moved = $svc->moveNode($inst, $s->id, $c2->id);
        $this->assertEquals($c2->id, $moved->parent_node_id);
        TenantContext::clear();
    }

    public function test_reorder_nodes(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'school']);
        TenantContext::set($inst->id);
        $svc = app(LearningStructureService::class);
        $a = $svc->createNode($inst, ['level_order' => 1, 'name' => 'A', 'display_order' => 1]);
        $b = $svc->createNode($inst, ['level_order' => 1, 'name' => 'B', 'display_order' => 2]);
        $c = $svc->createNode($inst, ['level_order' => 1, 'name' => 'C', 'display_order' => 3]);
        $svc->reorderNodes($inst, [$c->id, $a->id, $b->id]);
        $ordered = StructureNode::withoutGlobalScope('institute')->whereIn('id', [$a->id, $b->id, $c->id])->orderBy('display_order')->pluck('id')->all();
        $this->assertEquals([$c->id, $a->id, $b->id], $ordered);
        TenantContext::clear();
    }

    public function test_deactivate_delete_unused_node(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'school']);
        TenantContext::set($inst->id);
        $svc = app(LearningStructureService::class);
        $n = $svc->createNode($inst, ['level_order' => 1, 'name' => 'Temp']);
        $svc->deleteNode($inst, $n->id);
        $this->assertNull(StructureNode::withoutGlobalScope('institute')->find($n->id));
        TenantContext::clear();
    }

    public function test_historical_placement_prevents_destructive_deletion(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'school']);
        TenantContext::set($inst->id);
        $svc = app(LearningStructureService::class);
        $node = $svc->createNode($inst, ['level_order' => 1, 'name' => 'Class X']);
        // Create minimal placement + bridge
        $student = \App\Models\Student::withoutGlobalScope('institute')->create([
            'institute_id' => $inst->id,
            'branch_id' => null,
            'first_name' => 'Test',
            'last_name' => 'Student',
            'status' => 'active',
            'admission_date' => now()->toDateString(),
            'student_id_number' => 'S' . uniqid(),
        ]);
        $year = \App\Models\AcademicYear::withoutGlobalScope('institute')->create([
            'institute_id' => $inst->id,
            'name' => '2026-' . uniqid(),
            'code' => 'AY' . uniqid(),
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => true,
        ]);
        $placement = \App\Models\StudentAcademicPlacement::withoutGlobalScope('institute')->create([
            'institute_id' => $inst->id,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'status' => 'active',
        ]);
        DB::table('student_placement_nodes')->insert([
            'student_academic_placement_id' => $placement->id,
            'level_order' => 1,
            'node_id' => $node->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $svc->deleteNode($inst, $node->id);
        $after = StructureNode::withoutGlobalScope('institute')->find($node->id);
        $this->assertNotNull($after, 'node should be deactivated not deleted');
        $this->assertFalse((bool) $after->status);
        TenantContext::clear();
    }

    public function test_existing_academic_structure_service_remains_unchanged(): void
    {
        $svc = app(AcademicStructureService::class);
        $this->assertTrue(method_exists($svc, 'resolve'));
        $this->assertTrue(method_exists($svc, 'systemsForCountry'));
        $this->assertTrue(method_exists($svc, 'academicUnitLabel'));
    }

    public function test_existing_academic_placement_flow_remains_functional(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry' => 'school']);
        $student = \App\Models\Student::withoutGlobalScope('institute')->create([
            'institute_id' => $inst->id,
            'first_name' => 'Place',
            'last_name' => 'Test',
            'status' => 'active',
            'admission_date' => now()->toDateString(),
            'student_id_number' => 'S' . uniqid(),
        ]);
        $year = \App\Models\AcademicYear::withoutGlobalScope('institute')->create([
            'institute_id' => $inst->id,
            'name' => '2026-2-' . uniqid(),
            'code' => 'AY' . uniqid(),
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => true,
        ]);
        $placement = \App\Models\StudentAcademicPlacement::withoutGlobalScope('institute')->create([
            'institute_id' => $inst->id,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'status' => 'active',
        ]);
        $this->assertNotNull($placement->id);
        $this->assertEquals($inst->id, $placement->institute_id);
    }
}
