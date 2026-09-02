<?php
use Illuminate\Support\Facades\DB;
echo "--- education_systems cryptic ---\n";
foreach(DB::table('education_systems')->where('name','like','GE-%')->orWhere('name','like','%-6a91%')->get(['id','code','name','country_id']) as $r){
  echo "id={$r->id} code={$r->code} name={$r->name} country={$r->country_id}\n";
}
echo "--- academic_levels cryptic ---\n";
foreach(DB::table('academic_levels')->where('name','like','%-6a91%')->orWhere('code','like','%-6a91%')->limit(10)->get(['id','code','name','education_system_id']) as $r){
  echo "id={$r->id} code={$r->code} name={$r->name} sys={$r->education_system_id}\n";
}
echo "--- class_grades cryptic ---\n";
foreach(DB::table('class_grades')->where('name','like','%-6a91%')->orWhere('code','like','%-6a91%')->limit(10)->get(['id','code','name','academic_level_id']) as $r){
  echo "id={$r->id} code={$r->code} name={$r->name} lvl={$r->academic_level_id}\n";
}
echo "--- academic_groups cryptic ---\n";
foreach(DB::table('academic_groups')->where('name','like','%-6a91%')->orWhere('code','like','%-6a91%')->limit(10)->get(['id','code','name','class_grade_id']) as $r){
  echo "id={$r->id} code={$r->code} name={$r->name} class={$r->class_grade_id}\n";
}
echo "--- count cryptic pattern ---\n";
foreach(['education_systems','academic_levels','class_grades','academic_groups'] as $t){
  $c = DB::table($t)->where('name','like','%6a91%')->count();
  $c2 = DB::table($t)->where('code','like','%6a91%')->count();
  echo "$t name_like_6a91=$c code_like_6a91=$c2\n";
}
echo "--- specific ids mentioned ---\n";
foreach(['education_systems'=>55,'academic_levels'=>null,'class_grades'=>null] as $t=>$id){
  // check system 55 details
}
$r = DB::table('education_systems')->where('id',55)->first(['id','code','name','country_id','created_at','updated_at']);
print_r((array)$r);
echo "\n--- check audit logs for creation ---\n";
try{
  $logs = DB::table('audit_logs')->where('auditable_type','like','%EducationSystem%')->where('auditable_id',55)->limit(5)->get();
  echo "audit logs for sys55: ".$logs->count()."\n";
  foreach($logs as $l){ echo json_encode($l)."\n"; }
} catch(Throwable $e){ echo "audit err ".$e->getMessage()."\n"; }
