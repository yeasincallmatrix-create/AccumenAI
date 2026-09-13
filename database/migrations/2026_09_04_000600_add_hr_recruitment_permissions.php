<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * HR-7 — Recruitment permissions.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        ['module' => 'hr', 'name' => 'Recruitment View', 'slug' => 'hr.recruitment.view'],
        ['module' => 'hr', 'name' => 'Recruitment Manage', 'slug' => 'hr.recruitment.manage'],
        ['module' => 'hr', 'name' => 'Requisition View', 'slug' => 'hr.requisition.view'],
        ['module' => 'hr', 'name' => 'Requisition Manage', 'slug' => 'hr.requisition.manage'],
        ['module' => 'hr', 'name' => 'Requisition Approve', 'slug' => 'hr.requisition.approve'],
        ['module' => 'hr', 'name' => 'Vacancy View', 'slug' => 'hr.vacancy.view'],
        ['module' => 'hr', 'name' => 'Vacancy Manage', 'slug' => 'hr.vacancy.manage'],
        ['module' => 'hr', 'name' => 'Application View', 'slug' => 'hr.application.view'],
        ['module' => 'hr', 'name' => 'Application Manage', 'slug' => 'hr.application.manage'],
        ['module' => 'hr', 'name' => 'Interview Manage', 'slug' => 'hr.interview.manage'],
        ['module' => 'hr', 'name' => 'Offer Manage', 'slug' => 'hr.offer.manage'],
        ['module' => 'hr', 'name' => 'Hiring Manage', 'slug' => 'hr.hiring.manage'],
    ];

    private const GRANTS = [
        'institute-owner' => ['hr.recruitment.view','hr.recruitment.manage','hr.requisition.view','hr.requisition.manage','hr.requisition.approve','hr.vacancy.view','hr.vacancy.manage','hr.application.view','hr.application.manage','hr.interview.manage','hr.offer.manage','hr.hiring.manage'],
        'institute-admin' => ['hr.recruitment.view','hr.recruitment.manage','hr.requisition.view','hr.requisition.manage','hr.requisition.approve','hr.vacancy.view','hr.vacancy.manage','hr.application.view','hr.application.manage','hr.interview.manage','hr.offer.manage','hr.hiring.manage'],
        'branch-manager' => ['hr.recruitment.view','hr.requisition.view','hr.vacancy.view','hr.application.view','hr.application.manage','hr.interview.manage','hr.offer.manage'],
        'teacher' => [],
        'receptionist' => [],
    ];

    public function up(): void
    {
        $ids = DB::table('permissions')->pluck('id','slug');
        foreach (self::PERMISSIONS as $p) if (! $ids->has($p['slug'])) DB::table('permissions')->insert($p);
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
