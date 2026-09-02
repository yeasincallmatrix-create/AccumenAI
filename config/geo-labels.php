<?php

/*
|--------------------------------------------------------------------------
| Country-specific administrative level labels
|--------------------------------------------------------------------------
|
| Human labels for the three selectable administrative levels per country,
| keyed by ISO-3166 alpha-2. GeoNames dumps do not carry a generic label per
| level (they carry concrete division names), so we curate the common labels
| here. Countries without an entry fall back to the generic defaults below.
|
| Labels are metadata only — the hierarchy itself comes from the dataset and
| is never per-country hard-coded.
*/

return [
    'defaults' => [
        1 => 'State / Province',
        2 => 'District / County',
        3 => 'City / Sub-district',
        'locality' => 'Address',
    ],

    'localities' => [
        'BD' => 'Area / Road',
        'US' => 'Address',
    ],

    'labels' => [
        'BD' => [1 => 'Division', 2 => 'District', 3 => 'Upazila'],
        'US' => [1 => 'State', 2 => 'County', 3 => 'City'],
        'IN' => [1 => 'State', 2 => 'District', 3 => 'Sub-District'],
        'GB' => [1 => 'Region', 2 => 'County', 3 => 'District'],
        'CA' => [1 => 'Province', 2 => 'County', 3 => 'City'],
        'AU' => [1 => 'State', 2 => 'Local Government Area', 3 => 'Suburb'],
        'PK' => [1 => 'Province', 2 => 'Division', 3 => 'District'],
        'NP' => [1 => 'Province', 2 => 'District', 3 => 'Municipality'],
        'LK' => [1 => 'Province', 2 => 'District', 3 => 'Divisional Secretariat'],
        'MY' => [1 => 'State', 2 => 'District', 3 => 'Mukim'],
        'SA' => [1 => 'Province', 2 => 'Governorate', 3 => 'Governorate Center'],
        'AE' => [1 => 'Emirate', 2 => 'Municipality', 3 => 'Sector'],
        'SG' => [1 => 'Region', 2 => 'District', 3 => 'Subzone'],
        'KW' => [1 => 'Governorate', 2 => 'Area', 3 => 'District'],
        'QA' => [1 => 'Municipality', 2 => 'Zone', 3 => 'Sub-Zone'],
        'NG' => [1 => 'State', 2 => 'Local Government Area', 3 => 'District'],
    ],
];
