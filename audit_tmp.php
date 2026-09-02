<?php
use Illuminate\Support\Facades\DB;
$tables = ['education_systems','academic_levels','class_grades','academic_groups','subjects','structure_nodes','structure_templates'];
foreach($tables as $t){
  try{
    $total = DB::table($t)->count();
    $empty = DB::table($t)->where(function($q){$q->whereNull('name')->orWhere('name','');})->count();
    echo "$t: total=$total empty_name=$empty\n";
    if($empty>0){
      $rows = DB::table($t)->where(function($q){$q->whereNull('name')->orWhere('name','');})->limit(3)->get(['id','code','name']);
      foreach($rows as $r){
        $code = $r->code ?? ($r->subject_code ?? '(null)');
        $name = var_export($r->name, true);
        echo "  id={$r->id} code=$code name=$name\n";
      }
    }
  } catch(Throwable $e){ echo "$t error: ".$e->getMessage()."\n"; }
}
echo "\n--- Sample education_systems ---\n";
foreach(DB::table('education_systems')->limit(5)->get(['id','code','name','country_id']) as $r){ echo "id={$r->id} code={$r->code} name={$r->name} country={$r->country_id}\n"; }
echo "\n--- Sample academic_levels ---\n";
foreach(DB::table('academic_levels')->limit(5)->get(['id','code','name','education_system_id']) as $r){ echo "id={$r->id} code={$r->code} name={$r->name} sys={$r->education_system_id}\n"; }
echo "\n--- Sample class_grades ---\n";
foreach(DB::table('class_grades')->limit(5)->get(['id','code','name','academic_level_id']) as $r){ echo "id={$r->id} code={$r->code} name={$r->name} lvl={$r->academic_level_id}\n"; }
