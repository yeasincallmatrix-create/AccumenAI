<?php
$cols = DB::select('SHOW COLUMNS FROM institutes');
foreach ($cols as $c) echo $c->Field . ' | ' . $c->Type . ' | ' . $c->Null . ' | ' . $c->Default . PHP_EOL;
echo '---INDEXES---' . PHP_EOL;
$idx = DB::select('SHOW INDEX FROM institutes');
$g = [];
foreach ($idx as $i) { $g[$i->Key_name][] = $i->Column_name . ' seq' . $i->Seq_in_index; }
foreach ($g as $k => $v) echo $k . ' => ' . implode(', ', $v) . PHP_EOL;
