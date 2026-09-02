<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== POST-MIGRATION VERIFICATION ===\n\n";

echo "--- fee_heads new columns ---\n";
$cols = DB::getSchemaBuilder()->getColumnListing('fee_heads');
echo "  is_recurring: " . (in_array('is_recurring', $cols) ? 'EXISTS' : 'MISSING') . "\n";
echo "  billing_frequency: " . (in_array('billing_frequency', $cols) ? 'EXISTS' : 'MISSING') . "\n";
echo "  sort_order: " . (in_array('sort_order', $cols) ? 'EXISTS' : 'MISSING') . "\n";

echo "\n--- fee_structures new columns ---\n";
$cols = DB::getSchemaBuilder()->getColumnListing('fee_structures');
echo "  billing_frequency: " . (in_array('billing_frequency', $cols) ? 'EXISTS' : 'MISSING') . "\n";
echo "  auto_generate_monthly: " . (in_array('auto_generate_monthly', $cols) ? 'EXISTS' : 'MISSING') . "\n";

echo "\n--- payments new columns ---\n";
$cols = DB::getSchemaBuilder()->getColumnListing('payments');
echo "  receipt_number: " . (in_array('receipt_number', $cols) ? 'EXISTS' : 'MISSING') . "\n";
echo "  receipt_printed_at: " . (in_array('receipt_printed_at', $cols) ? 'EXISTS' : 'MISSING') . "\n";

echo "\n--- monthly_fee_periods table ---\n";
$exists = DB::getSchemaBuilder()->hasTable('monthly_fee_periods');
echo "  exists: " . ($exists ? 'YES' : 'NO') . "\n";
if ($exists) {
    $cols = DB::getSchemaBuilder()->getColumnListing('monthly_fee_periods');
    echo "  columns: " . implode(', ', $cols) . "\n";
}

echo "\n--- baseline counts unchanged ---\n";
echo "  students = " . DB::table('students')->count() . "\n";
echo "  courses = " . DB::table('courses')->count() . "\n";
echo "  institutes = " . DB::table('institutes')->count() . "\n";
echo "  invoices = " . DB::table('invoices')->count() . "\n";
echo "  payments = " . DB::table('payments')->count() . "\n";

echo "\n=== VERIFICATION COMPLETE ===\n";
