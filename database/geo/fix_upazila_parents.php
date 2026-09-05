<?php
/**
 * Fix Upazila Parent IDs
 * 
 * Reads bangladesh.json, maps each upazila to its correct district
 * using a correction mapping (the JSON parent_code values are scrambled),
 * and outputs a SQL file with UPDATE statements.
 *
 * Usage: php database/geo/fix_upazila_parents.php
 * Output: database/geo/fix_upazila_parents.sql
 */

$jsonFile = __DIR__ . '/bangladesh.json';
$outputFile = __DIR__ . '/fix_upazila_parents.sql';

// Correction mapping: JSON parent_code → correct district code
// The JSON's parent_code values for upazilas are scrambled.
// This maps each JSON parent_code to the district code it SHOULD be.
$correctionMap = [
    'BD.T1'  => 'BD.T1',   // Comilla (unchanged)
    'BD.T2'  => 'BD.T2',   // Feni (unchanged)
    'BD.T3'  => 'BD.T32',  // Brahmanbaria
    'BD.T4'  => 'BD.T10',  // Rangamati
    'BD.T5'  => 'BD.T5',   // Noakhali (unchanged)
    'BD.T6'  => 'BD.T3',   // Chandpur
    'BD.T7'  => 'BD.T4',   // Lakshmipur
    'BD.T8'  => 'BD.T6',   // Chattogram
    'BD.T9'  => 'BD.T7',   // Cox's Bazar
    'BD.T10' => 'BD.T8',   // Khagrachhari
    'BD.T11' => 'BD.T9',   // Bandarban
    'BD.T12' => 'BD.T45',  // Sirajganj
    'BD.T13' => 'BD.T44',  // Pabna
    'BD.T14' => 'BD.T39',  // Bogura
    'BD.T15' => 'BD.T54',  // Rajshahi
    'BD.T16' => 'BD.T42',  // Natore
    'BD.T17' => 'BD.T40',  // Joypurhat
    'BD.T18' => 'BD.T43',  // Chapainawabganj
    'BD.T19' => 'BD.T41',  // Naogaon
    'BD.T20' => 'BD.T57',  // Jessore
    'BD.T21' => 'BD.T64',  // Satkhira
    'BD.T22' => 'BD.T62',  // Meherpur
    'BD.T23' => 'BD.T63',  // Narail
    'BD.T24' => 'BD.T56',  // Chuadanga
    'BD.T25' => 'BD.T60',  // Kushtia
    'BD.T26' => 'BD.T61',  // Magura
    'BD.T27' => 'BD.T59',  // Khulna
    'BD.T28' => 'BD.T55',  // Bagerhat
    'BD.T29' => 'BD.T58',  // Jhenaidah
    'BD.T30' => 'BD.T36',  // Jhalakathi
    'BD.T31' => 'BD.T37',  // Patuakhali
    'BD.T32' => 'BD.T38',  // Pirojpur
    'BD.T33' => 'BD.T34',  // Barishal
    'BD.T34' => 'BD.T35',  // Bhola
    'BD.T35' => 'BD.T33',  // Barguna
    'BD.T36' => 'BD.T16',  // Sylhet
    'BD.T37' => 'BD.T18',  // Moulvibazar
    'BD.T38' => 'BD.T17',  // Habiganj
    'BD.T39' => 'BD.T15',  // Sunamganj
    'BD.T40' => 'BD.T30',  // Narsingdi
    'BD.T41' => 'BD.T31',  // Gazipur
    'BD.T42' => 'BD.T24',  // Shariatpur
    'BD.T43' => 'BD.T29',  // Narayanganj
    'BD.T44' => 'BD.T25',  // Tangail
    'BD.T45' => 'BD.T26',  // Kishoreganj
    'BD.T46' => 'BD.T27',  // Manikganj
    'BD.T47' => 'BD.T19',  // Dhaka
    'BD.T48' => 'BD.T28',  // Munshiganj
    'BD.T49' => 'BD.T23',  // Rajbari
    'BD.T50' => 'BD.T22',  // Madaripur
    'BD.T51' => 'BD.T21',  // Gopalganj
    'BD.T52' => 'BD.T20',  // Faridpur
    'BD.T53' => 'BD.T51',  // Panchagarh
    'BD.T54' => 'BD.T46',  // Dinajpur
    'BD.T55' => 'BD.T49',  // Lalmonirhat
    'BD.T56' => 'BD.T50',  // Nilphamari
    'BD.T57' => 'BD.T47',  // Gaibandha
    'BD.T58' => 'BD.T53',  // Thakurgaon
    'BD.T59' => 'BD.T52',  // Rangpur
    'BD.T60' => 'BD.T48',  // Kurigram
    'BD.T61' => 'BD.T12',  // Sherpur
    'BD.T62' => 'BD.T11',  // Mymensingh
    'BD.T63' => 'BD.T13',  // Jamalpur
    'BD.T64' => 'BD.T14',  // Netrokona
];

// Individual overrides for upazilas whose JSON parent_code doesn't match
// any group (edge cases that the correction map can't handle correctly)
$overrides = [
    'BD.U494' => 'BD.T21',  // Dasar → Gopalganj (JSON has BD.T50 which maps to Madaripur)
    'BD.U537' => 'BD.T19',  // Wari → Dhaka (JSON has BD.T48 which maps to Munshiganj)
];

// District code to name mapping (for SQL comments)
$districtNames = [
    'BD.T1'  => 'Comilla',
    'BD.T2'  => 'Feni',
    'BD.T3'  => 'Chandpur',
    'BD.T4'  => 'Lakshmipur',
    'BD.T5'  => 'Noakhali',
    'BD.T6'  => 'Chattogram',
    'BD.T7'  => "Cox's Bazar",
    'BD.T8'  => 'Khagrachhari',
    'BD.T9'  => 'Bandarban',
    'BD.T10' => 'Rangamati',
    'BD.T11' => 'Mymensingh',
    'BD.T12' => 'Sherpur',
    'BD.T13' => 'Jamalpur',
    'BD.T14' => 'Netrokona',
    'BD.T15' => 'Sunamganj',
    'BD.T16' => 'Sylhet',
    'BD.T17' => 'Habiganj',
    'BD.T18' => 'Moulvibazar',
    'BD.T19' => 'Dhaka',
    'BD.T20' => 'Faridpur',
    'BD.T21' => 'Gopalganj',
    'BD.T22' => 'Madaripur',
    'BD.T23' => 'Rajbari',
    'BD.T24' => 'Shariatpur',
    'BD.T25' => 'Tangail',
    'BD.T26' => 'Kishoreganj',
    'BD.T27' => 'Manikganj',
    'BD.T28' => 'Munshiganj',
    'BD.T29' => 'Narayanganj',
    'BD.T30' => 'Narsingdi',
    'BD.T31' => 'Gazipur',
    'BD.T32' => 'Brahmanbaria',
    'BD.T33' => 'Barguna',
    'BD.T34' => 'Barishal',
    'BD.T35' => 'Bhola',
    'BD.T36' => 'Jhalakathi',
    'BD.T37' => 'Patuakhali',
    'BD.T38' => 'Pirojpur',
    'BD.T39' => 'Bogura',
    'BD.T40' => 'Joypurhat',
    'BD.T41' => 'Naogaon',
    'BD.T42' => 'Natore',
    'BD.T43' => 'Chapainawabganj',
    'BD.T44' => 'Pabna',
    'BD.T45' => 'Sirajganj',
    'BD.T46' => 'Dinajpur',
    'BD.T47' => 'Gaibandha',
    'BD.T48' => 'Kurigram',
    'BD.T49' => 'Lalmonirhat',
    'BD.T50' => 'Nilphamari',
    'BD.T51' => 'Panchagarh',
    'BD.T52' => 'Rangpur',
    'BD.T53' => 'Thakurgaon',
    'BD.T54' => 'Rajshahi',
    'BD.T55' => 'Bagerhat',
    'BD.T56' => 'Chuadanga',
    'BD.T57' => 'Jessore',
    'BD.T58' => 'Jhenaidah',
    'BD.T59' => 'Khulna',
    'BD.T60' => 'Kushtia',
    'BD.T61' => 'Magura',
    'BD.T62' => 'Meherpur',
    'BD.T63' => 'Narail',
    'BD.T64' => 'Satkhira',
];

// Read JSON
$lines = file($jsonFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$upazilas = [];
foreach ($lines as $line) {
    $entry = json_decode($line, true);
    if (!$entry) continue;
    if ($entry['level'] === 3 && isset($entry['code'])) {
        $upazilas[] = $entry;
    }
}

echo "Found " . count($upazilas) . " upazilas in JSON\n";

// Build UPDATE statements grouped by district
$updates = []; // district_code => [upazila_code, ...]
$changes = 0;
$unchanged = 0;

foreach ($upazilas as $upazila) {
    $code = $upazila['code'];
    $jsonParentCode = $upazila['parent_code'] ?? null;
    
    if (!$jsonParentCode) {
        echo "WARNING: No parent_code for {$code} ({$upazila['name']})\n";
        continue;
    }
    
    // Check for individual overrides first
    if (isset($overrides[$code])) {
        $correctDistrictCode = $overrides[$code];
    } elseif (isset($correctionMap[$jsonParentCode])) {
        $correctDistrictCode = $correctionMap[$jsonParentCode];
    } else {
        echo "WARNING: No correction mapping for JSON parent_code {$jsonParentCode} (upazila {$code})\n";
        continue;
    }
    
    $updates[$correctDistrictCode][] = $code;
    $changes++;
}

// Generate SQL
$sql = "-- Fix Upazila Parent IDs\n";
$sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
$sql .= "-- Total upazilas to update: {$changes}\n";
$sql .= "--\n";
$sql .= "-- This script fixes scrambled parent_id values for upazilas (level 3).\n";
$sql .= "-- Each upazila's parent_id is set to the correct district (level 2) ID.\n";
$sql .= "-- The JSON parent_code values were systematically scrambled during import.\n";
$sql .= "--\n";
$sql .= "-- Format: UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = '{district_code}') WHERE code = '{upazila_code}';\n";
$sql .= "--\n\n";

// Sort districts by code for organized output
uksort($updates, function($a, $b) {
    preg_match('/T(\d+)/', $a, $mA);
    preg_match('/T(\d+)/', $b, $mB);
    return (int)$mA[1] - (int)$mB[1];
});

foreach ($updates as $districtCode => $upazilaCodes) {
    $districtName = $districtNames[$districtCode] ?? $districtCode;
    $sql .= "-- District: {$districtName} ({$districtCode}) — " . count($upazilaCodes) . " upazilas\n";
    
    // Sort upazila codes numerically
    usort($upazilaCodes, function($a, $b) {
        preg_match('/U(\d+)/', $a, $mA);
        preg_match('/U(\d+)/', $b, $mB);
        return (int)$mA[1] - (int)$mB[1];
    });
    
    foreach ($upazilaCodes as $upazilaCode) {
        $sql .= "UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = '{$districtCode}') WHERE code = '{$upazilaCode}';\n";
    }
    $sql .= "\n";
}

// Write SQL file
file_put_contents($outputFile, $sql);
echo "\nSQL file written to: {$outputFile}\n";
echo "Total UPDATE statements: {$changes}\n";

// Print summary
echo "\nDistrict summary:\n";
foreach ($updates as $districtCode => $upazilaCodes) {
    $districtName = $districtNames[$districtCode] ?? $districtCode;
    echo "  {$districtCode} ({$districtName}): " . count($upazilaCodes) . " upazilas\n";
}
