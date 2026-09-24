<?php

/*
|--------------------------------------------------------------------------
| Country Default Module Access
|--------------------------------------------------------------------------
|
| Maps ISO-3166 alpha-2 codes to the list of modules that should be
| enabled by default for institutes belonging to that country when the
| admin runs the `assign_default_modules` batch action.
|
| Modules correspond to keys in `module_registry.key` (see migration
| 2026_09_02_000100). Unknown keys are silently ignored by
| CountryBatchService::assignDefaultModules() and reported as skipped.
|
| Fallback: countries without an entry use `defaults`.
|
*/

return [
    'defaults' => ['education', 'crm', 'accounting'],

    'BD' => ['education', 'crm', 'accounting', 'hr'],
    'US' => ['education', 'crm', 'accounting', 'sales'],
    'GB' => ['education', 'crm', 'accounting', 'sales'],
    'IN' => ['education', 'crm', 'accounting', 'hr'],
    'CA' => ['education', 'crm', 'accounting', 'sales'],
    'AU' => ['education', 'crm', 'accounting', 'hr', 'sales'],
    'PK' => ['education', 'crm', 'accounting', 'hr'],
    'NP' => ['education', 'crm', 'accounting'],
    'LK' => ['education', 'crm', 'accounting'],
    'MY' => ['education', 'crm', 'accounting', 'inventory', 'sales'],
    'AE' => ['education', 'crm', 'accounting', 'sales', 'hr'],
    'SA' => ['education', 'crm', 'accounting', 'hr'],
];
