<?php
$m = new PDO('mysql:host=127.0.0.1;port=3306;dbname=monetix_test', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$cols = array_column($m->query('SHOW COLUMNS FROM parties')->fetchAll(PDO::FETCH_ASSOC), 'Field');

if (!in_array('party_type', $cols, true)) {
    $m->exec("ALTER TABLE parties ADD COLUMN party_type VARCHAR(20) NOT NULL DEFAULT 'customer' AFTER name");
    echo "added party_type\n";
} else {
    echo "party_type exists\n";
}
if (!in_array('is_customer', $cols, true)) {
    $m->exec('ALTER TABLE parties ADD COLUMN is_customer TINYINT(1) NOT NULL DEFAULT 1 AFTER party_type');
    echo "added is_customer\n";
} else {
    echo "is_customer exists\n";
}
if (!in_array('is_vendor', $cols, true)) {
    $m->exec('ALTER TABLE parties ADD COLUMN is_vendor TINYINT(1) NOT NULL DEFAULT 0 AFTER is_customer');
    echo "added is_vendor\n";
} else {
    echo "is_vendor exists\n";
}

$m->exec("UPDATE parties SET party_type='customer', is_customer=1, is_vendor=0 WHERE type='customer'");
$m->exec("UPDATE parties SET party_type='vendor', is_customer=0, is_vendor=1 WHERE type='supplier'");
$m->exec("UPDATE parties SET party_type='both', is_customer=1, is_vendor=1 WHERE type='both'");
echo "backfilled from type\n";

$cols = array_column($m->query('SHOW COLUMNS FROM parties')->fetchAll(PDO::FETCH_ASSOC), 'Field');
echo 'TOTAL: '.count($cols).PHP_EOL;
echo 'has: '.(in_array('party_type', $cols, true) && in_array('is_customer', $cols, true) && in_array('is_vendor', $cols, true) ? 'ALL YES' : 'MISSING').PHP_EOL;
