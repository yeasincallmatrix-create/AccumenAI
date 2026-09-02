<?php
use Illuminate\Support\Facades\DB;
// Find who/what created the cryptic rows - check created_at and nearby batches
foreach(DB::table('education_systems')->where('name','like','GE-%')->get() as $r){
  echo "SYS id={$r->id} code={$r->code} name={$r->name} created={$r->created_at} updated={$r->updated_at}\n";
}
foreach(DB::table('academic_levels')->where('code','like','%-6a91%')->get() as $r){
  echo "LVL id={$r->id} code={$r->code} name=".var_export($r->name,true)." sys={$r->education_system_id} created={$r->created_at}\n";
}
foreach(DB::table('class_grades')->where('code','like','%-6a91%')->orWhere('name','like','%-6a91%')->get() as $r){
  echo "CG id={$r->id} code={$r->code} name={$r->name} lvl={$r->academic_level_id} created={$r->created_at}\n";
}
// Check for any seeder or factory that generates 6a91 pattern: search installed files
echo "--- check if 6a91 appears in code generation ---\n";
echo file_get_contents('C:/xampp/htdocs/monetix/database/factories/UserFactory.php') ? "factory exists\n":"no\n";
