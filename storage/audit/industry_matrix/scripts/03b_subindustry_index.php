<?php
echo 'Schema hasColumn sub_industry: ' . (Schema::hasColumn('institutes', 'sub_industry') ? 'YES' : 'NO') . PHP_EOL;
$idx = DB::select("SHOW INDEX FROM institutes WHERE Key_name = 'institutes_industry_sub_industry_index'");
echo 'index (industry,sub_industry): ' . (count($idx) ? 'YES' : 'NO') . PHP_EOL;
$all = DB::select('SHOW INDEX FROM institutes');
foreach ($all as $i) {
    if (stripos($i->Column_name, 'sub') !== false || stripos($i->Key_name, 'sub') !== false || stripos($i->Key_name, 'industry') !== false) {
        echo $i->Key_name . ' | col: ' . $i->Column_name . ' | seq: ' . $i->Seq_in_index . PHP_EOL;
    }
}
