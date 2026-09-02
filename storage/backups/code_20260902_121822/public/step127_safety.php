<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== STEP 127 PRE-CHANGE SAFETY AUDIT ===\n";
echo "Date: " . now()->toDateTimeString() . "\n\n";

echo "--- BASELINE TABLE COUNTS ---\n";
$tables = [
    'institutes','institute_users','students','courses','batches',
    'student_enrollments','fee_heads','fee_structures','fee_structure_items',
    'invoices','invoice_items','installments','payments',
    'student_waivers','journals','journal_entries',
    'chart_of_accounts','payment_methods','parties','academic_years',
    'settings','currencies','branches',
];
foreach ($tables as $t) {
    try { $c = DB::table($t)->count(); echo "  $t = $c\n"; }
    catch (Throwable $e) { echo "  $t = MISSING\n"; }
}

echo "\n--- FEE_HEADS COLUMNS (verify no duplicates) ---\n";
try {
    $cols = DB::getSchemaBuilder()->getColumnListing('fee_heads');
    echo "  " . implode(', ', $cols) . "\n";
    if (in_array('is_recurring', $cols)) echo "  WARNING: is_recurring already exists!\n";
    if (in_array('billing_frequency', $cols)) echo "  WARNING: billing_frequency already exists!\n";
    if (in_array('sort_order', $cols)) echo "  WARNING: sort_order already exists!\n";
} catch (Throwable $e) { echo "  ERROR: {$e->getMessage()}\n"; }

echo "\n--- FEE_STRUCTURES COLUMNS (verify no duplicates) ---\n";
try {
    $cols = DB::getSchemaBuilder()->getColumnListing('fee_structures');
    echo "  " . implode(', ', $cols) . "\n";
    if (in_array('billing_frequency', $cols)) echo "  WARNING: billing_frequency already exists!\n";
    if (in_array('auto_generate_monthly', $cols)) echo "  WARNING: auto_generate_monthly already exists!\n";
    if (in_array('course_id', $cols)) echo "  NOTE: course_id already exists\n";
} catch (Throwable $e) { echo "  ERROR: {$e->getMessage()}\n"; }

echo "\n--- PAYMENTS COLUMNS (verify no duplicates) ---\n";
try {
    $cols = DB::getSchemaBuilder()->getColumnListing('payments');
    echo "  " . implode(', ', $cols) . "\n";
    if (in_array('receipt_number', $cols)) echo "  WARNING: receipt_number already exists!\n";
    if (in_array('receipt_printed_at', $cols)) echo "  WARNING: receipt_printed_at already exists!\n";
} catch (Throwable $e) { echo "  ERROR: {$e->getMessage()}\n"; }

echo "\n--- MONTHLY_FEE_PERIODS TABLE (verify not exists) ---\n";
try {
    $exists = DB::getSchemaBuilder()->hasTable('monthly_fee_periods');
    echo "  exists = " . ($exists ? 'YES' : 'NO') . "\n";
} catch (Throwable $e) { echo "  ERROR\n"; }

echo "\n--- MIGRATION STATUS (relevant) ---\n";
try {
    $migrations = DB::table('migrations')->orderBy('batch')->orderBy('migration')->get();
    $relevant = $migrations->filter(fn($m) => str_contains($m->migration, 'fee') || str_contains($m->migration, 'payment') || str_contains($m->migration, 'invoice') || str_contains($m->migration, 'installment') || str_contains($m->migration, 'enrollment') || str_contains($m->migration, 'waiver'));
    foreach ($relevant as $m) echo "  [{$m->batch}] {$m->migration}\n";
} catch (Throwable $e) { echo "  ERROR: {$e->getMessage()}\n"; }

echo "\n--- EXISTING PERMISSIONS ---\n";
try {
    $perms = DB::table('permissions')->where('name', 'like', '%account%')
        ->orWhere('name', 'like', '%fee%')
        ->orWhere('name', 'like', '%invoice%')
        ->orWhere('name', 'like', '%payment%')
        ->orWhere('name', 'like', '%finance%')
        ->orWhere('name', 'like', '%journal%')
        ->orderBy('name')->get(['id','name']);
    foreach ($perms as $p) echo "  id={$p->id} {$p->name}\n";
} catch (Throwable $e) { echo "  ERROR\n"; }

echo "\n--- PAYMENT_METHODS COLUMNS ---\n";
try {
    echo "  " . implode(', ', DB::getSchemaBuilder()->getColumnListing('payment_methods')) . "\n";
} catch (Throwable $e) { echo "  ERROR\n"; }

echo "\n--- COA (sample) ---\n";
try {
    $coa = DB::table('chart_of_accounts')->where('is_active', true)->limit(10)->get(['id','code','name','type']);
    foreach ($coa as $a) echo "  {$a->code} - {$a->name} ({$a->type})\n";
    if ($coa->isEmpty()) echo "  (empty)\n";
} catch (Throwable $e) { echo "  ERROR\n"; }

echo "\n=== SAFETY AUDIT COMPLETE ===\n";
