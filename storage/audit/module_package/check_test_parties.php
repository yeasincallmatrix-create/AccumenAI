<?php
$m = new PDO('mysql:host=127.0.0.1;port=3306;dbname=monetix_test', 'root', '');
$cols = $m->query('SHOW COLUMNS FROM parties')->fetchAll(PDO::FETCH_ASSOC);
$names = array_column($cols, 'Field');
echo 'parties cols: '.implode(', ', $names).PHP_EOL;
echo 'has party_type: '.(in_array('party_type', $names) ? 'YES' : 'NO').PHP_EOL;
echo 'has is_customer: '.(in_array('is_customer', $names) ? 'YES' : 'NO').PHP_EOL;
echo 'has is_vendor: '.(in_array('is_vendor', $names) ? 'YES' : 'NO').PHP_EOL;
echo 'TOTAL: '.count($cols).PHP_EOL;
