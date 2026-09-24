<?php
use Illuminate\Support\Facades\DB;

DB::transaction(function () {
    $cases = ['supplier' => ['vendor', false, true], 'customer' => ['customer', true, false], 'both' => ['both', true, true]];
    foreach ($cases as $type => $expect) {
        $p = App\Models\Party::create([
            'institute_id' => 1,
            'type' => $type,
            'name' => 'Verify '.$type,
            'phone' => '017'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
            'is_active' => true,
        ]);
        $ok = $p->party_type === $expect[0]
            && (bool) $p->is_customer === $expect[1]
            && (bool) $p->is_vendor === $expect[2]
            && $p->type === ($type === 'supplier' ? 'supplier' : $type);
        echo ($ok ? 'OK  ' : 'FAIL').' type='.$type
            .' => party_type='.$p->party_type
            .' is_customer='.var_export($p->is_customer, true)
            .' is_vendor='.var_export($p->is_vendor, true)
            .' type='.$p->type
            .' display='.$p->display_type
            .' badge='.$p->badge_color."\n";
    }

    $flags = App\Models\Party::create([
        'institute_id' => 1,
        'is_customer' => false,
        'is_vendor' => true,
        'name' => 'Flags only',
        'phone' => '017'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
        'is_active' => true,
    ]);
    echo (($flags->party_type === 'vendor' && $flags->type === 'supplier') ? 'OK  ' : 'FAIL')
        ." flags-only => party_type={$flags->party_type} type={$flags->type}\n";

    echo 'scopes this-inst: customers='.App\Models\Party::customers()->count()
        .' suppliers='.App\Models\Party::suppliers()->count()
        .' both='.App\Models\Party::both()->count()
        .' active='.App\Models\Party::active()->count()."\n";
    throw new RuntimeException('rollback');
});
