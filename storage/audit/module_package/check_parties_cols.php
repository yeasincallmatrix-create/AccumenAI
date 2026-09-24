<?php
$cols = DB::select("SHOW COLUMNS FROM parties");
echo "parties columns:\n";
foreach ($cols as $c) { echo '  '.$c->Field.' | '.$c->Type." | null='.".($c->Null === 'YES' ? 'Y' : 'N')."' | key='".$c->Key."' | default=".var_export($c->Default, true)."\n"; }
echo "TOTAL: ".count($cols)."\n";
$m = DB::table('migrations')->where('migration', 'like', '%parties%')->get();
echo "party migrations recorded:\n";
foreach ($m as $row) { echo '  #'.$row->id.' '.$row->migration.' batch='.$row->batch."\n"; }
if ($m->isEmpty()) { echo "  (none)\n"; }
