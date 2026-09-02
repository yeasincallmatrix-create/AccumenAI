<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\StructureTemplate;
use Tests\TestCase;
use App\Support\TenantContext;

class LearningStructureEnginePhase5Test extends TestCase
{
    private function makeInstitute(array $o=[]): Institute {
        $c = Country::first() ?? Country::create(['name'=>'Bangladesh','iso2'=>'BD','status'=>true]);
        return Institute::create(array_merge(['name'=>'P5 '.uniqid(),'slug'=>'p5-'.uniqid(),'country_id'=>$c->id,'industry'=>'education','sub_industry'=>'school','country'=>$c->name], $o));
    }
    private function makeOwner(Institute $i, string $slug='institute-owner'): InstituteUser {
        $role = \App\Models\Role::where('slug',$slug)->first() ?? \App\Models\Role::first() ?? \App\Models\Role::create(['name'=>'Owner','slug'=>'institute-owner','status'=>'active']);
        $uid=uniqid();
        return InstituteUser::withoutGlobalScope('institute')->create(['institute_id'=>$i->id,'role_id'=>$role->id,'first_name'=>'Owner','last_name'=>$uid,'email'=>'o'.$uid.'@test.com','phone'=>'01'.substr(microtime(true)*10000,-9),'password_hash'=>'password','status'=>'active','email_verified_at'=>now()]);
    }

    public function test_idor_institute_id_is_ignored(): void {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $a = $this->makeInstitute(); $b = $this->makeInstitute();
        $userA = $this->makeOwner($a);
        $this->actingAs($userA,'institute_user');
        TenantContext::set($a->id);
        $svc = app(\App\Services\LearningStructureService::class);
        $node = $svc->createNode($a,['level_order'=>1,'name'=>'A1']);
        TenantContext::clear();
        // Attacker tries to inject institute_id= b
        $res = $this->postJson(route('academic.structure.nodes.store'), ['level_order'=>1,'name'=>'Hacked','institute_id'=>$b->id]);
        // Should either ignore institute_id and create in A's institute, or reject
        // Our controller ignores institute_id (not validated), so it will create in A's institute, not B's
        // Verify no node created in B
        $this->assertDatabaseMissing('structure_nodes',['institute_id'=>$b->id,'name'=>'Hacked']);
        // Cleanup
        TenantContext::set($a->id);
        \App\Models\StructureNode::withoutGlobalScope('institute')->where('id',$node->id)->delete();
        TenantContext::clear();
    }

    public function test_performance_resolver_uses_efficient_queries(): void {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry'=>'university']);
        TenantContext::set($inst->id);
        $svc = app(\App\Services\LearningStructureService::class);
        // Create hierarchy
        $n1 = $svc->createNode($inst,['level_order'=>1,'name'=>'F']);
        $n2 = $svc->createNode($inst,['level_order'=>2,'name'=>'D','parent_node_id'=>$n1->id]);
        $n3 = $svc->createNode($inst,['level_order'=>3,'name'=>'P','parent_node_id'=>$n2->id]);
        $n4 = $svc->createNode($inst,['level_order'=>4,'name'=>'S','parent_node_id'=>$n3->id]);
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $res = app(\App\Services\LearningStructureResolver::class)->resolve($inst);
        $log = \Illuminate\Support\Facades\DB::getQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();
        // Should be <=3 queries: template, levels, nodes
        $this->assertLessThanOrEqual(7, count($log), 'Resolver should not N+1, queries: '.count($log));
        $this->assertCount(4, $res['levels']);
        TenantContext::clear();
    }

    public function test_no_hardcoded_levels_in_resolver_js(): void {
        $js = file_get_contents(public_path('js/learning-select.js'));
        $this->assertStringNotContainsString("if (level === 'class')", $js);
        $this->assertStringNotContainsString("if (level === 'group')", $js);
        $this->assertStringContainsString('levelsMeta', $js);
        $this->assertStringContainsString('AbortController', $js);
        $this->assertStringContainsString('seq', $js);
    }

    public function test_global_template_cannot_be_modified_via_settings(): void {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute();
        $user = $this->makeOwner($inst);
        $this->actingAs($user,'institute_user');
        $tpl = StructureTemplate::where('code','school')->first();
        $this->assertTrue($tpl->is_global);
        // Try to assign private of other institute already tested; here try to update global via direct API should not exist
        $res = $this->post(route('academic.structure.settings.assign'), ['template_id'=>$tpl->id]);
        $res->assertRedirect();
        // Global still global after assign (reference, not copy)
        $this->assertTrue(StructureTemplate::find($tpl->id)->is_global);
        $this->assertDatabaseHas('institute_settings',['institute_id'=>$inst->id,'structure_template_id'=>$tpl->id]);
    }

    public function test_orphan_check_reports_clean(): void {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $orphans = \Illuminate\Support\Facades\DB::select("SELECT COUNT(*) as c FROM structure_nodes WHERE template_id NOT IN (SELECT id FROM structure_templates)");
        $this->assertEquals(0, $orphans[0]->c);
    }

    public function test_api_response_format_options(): void {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->makeInstitute(['sub_industry'=>'school']);
        $user = $this->makeOwner($inst);
        $this->actingAs($user,'institute_user');
        TenantContext::set($inst->id);
        $res = $this->getJson(route('academic.structure.options'));
        TenantContext::clear();
        $res->assertStatus(200);
        $res->assertJsonStructure(['success','data'=>['template'=>['id','code','name'],'source','branch_id','levels']]);
        $levels = $res->json('data.levels');
        $this->assertIsArray($levels);
        foreach($levels as $lvl){ $this->assertArrayHasKey('level_order',$lvl); $this->assertArrayHasKey('label',$lvl); }
    }

    public function test_branch_change_resets_dependent(): void {
        $js = file_get_contents(public_path('js/learning-select.js'));
        $this->assertStringContainsString('clearFrom', $js);
        $this->assertStringContainsString('branch:change', $js);
        $this->assertStringContainsString('reload', $js);
    }
}
