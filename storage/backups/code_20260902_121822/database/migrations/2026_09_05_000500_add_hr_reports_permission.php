<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        ['module'=>'hr','name'=>'HR Reports View','slug'=>'hr.reports.view'],
    ];
    private const GRANTS = [
        'institute-owner' => ['hr.reports.view'],
        'institute-admin' => ['hr.reports.view'],
        'branch-manager' => ['hr.reports.view'],
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
