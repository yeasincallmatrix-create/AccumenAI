<?php
use Illuminate\Support\Facades\DB;
foreach(['education_systems','academic_levels','class_grades'] as $t){
  $cnt = DB::table($t)->where('name','like','%-%')->count();
  echo "$t dash_in_name=$cnt\n";
  $rows = DB::table($t)->where('name','like','%-%')->limit(2)->get(['id','code','name']);
  foreach($rows as $r) echo "  id={$r->id} code={$r->code} name={$r->name}\n";
}
echo "--- code GE% ---\n";
foreach(['education_systems','academic_levels','class_grades','structure_nodes'] as $t){
  try{
    $c = DB::table($t)->where('code','like','GE%')->count();
    echo "$t code GE%=$c\n";
  } catch(Throwable $e){ echo "$t err ".$e->getMessage()."\n"; }
}
echo "--- structure label check ---\n";
$cnt = DB::table('structure_nodes')->count();
echo "structure_nodes count=$cnt\n";
$cnt2 = DB::table('structure_templates')->count();
echo "structure_templates count=$cnt2\n";
