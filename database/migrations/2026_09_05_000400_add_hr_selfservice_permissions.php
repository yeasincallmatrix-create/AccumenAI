<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        ['module'=>'hr','name'=>'Self Service View','slug'=>'hr.self.view'],
        ['module'=>'hr','name'=>'Self Service Manage','slug'=>'hr.self.manage'],
        ['module'=>'hr','name'=>'Team View','slug'=>'hr.team.view'],
        ['module'=>'hr','name'=>'HR Dashboard View','slug'=>'hr.dashboard.view'],
        ['module'=>'hr','name'=>'Workflow View','slug'=>'hr.workflow.view'],
        ['module'=>'hr','name'=>'Workflow Manage','slug'=>'hr.workflow.manage'],
    ];

    private const GRANTS = [
        'institute-owner' => ['hr.self.view','hr.self.manage','hr.team.view','hr.dashboard.view','hr.workflow.view','hr.workflow.manage'],
        'institute-admin' => ['hr.self.view','hr.self.manage','hr.team.view','hr.dashboard.view','hr.workflow.view','hr.workflow.manage'],
        'branch-manager' => ['hr.self.view','hr.self.manage','hr.team.view','hr.dashboard.view','hr.workflow.view','hr.workflow.manage'],
        'teacher' => ['hr.self.view','hr.self.manage'],
        'receptionist' => ['hr.self.view','hr.self.manage'],
        'accountant' => ['hr.self.view'],
    ];

    public function up(): void
    {
        $ids = DB::table('permissions')->pluck('id','slug');
        foreach (self::PERMISSIONS as $p) if (!$ids->has($p['slug'])) DB::table('permissions')->insert($p);
        $ids = DB::table('permissions')->pluck('id','slug');
        $roles = DB::table('roles')->pluck('id','slug');
        $existing = DB::table('role_permissions')->get()->map(fn($r)=>$r->role_id.':'.$r->permission_id)->all();
        $pairs=[];
        foreach (self::GRANTS as $role=>$slugs) {
            $rid=$roles[$role]??null; if(!$rid) continue;
            foreach ($slugs as $slug) {
                $pid=$ids[$slug]??null; if(!$pid) continue;
                $k="$rid:$pid"; if(!in_array($k,$existing,true)) { $pairs[]=['role_id'=>$rid,'permission_id'=>$pid]; $existing[]=$k; }
            }
        }
        if($pairs) DB::table('role_permissions')->insert($pairs);
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('slug', collect(self::PERMISSIONS)->pluck('slug')->all())->pluck('id')->all();
        if($ids) DB::table('role_permissions')->whereIn('permission_id',$ids)->delete();
        DB::table('permissions')->whereIn('slug', collect(self::PERMISSIONS)->pluck('slug')->all())->delete();
    }
};
