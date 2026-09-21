<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CountryTaxConfig extends Model
{
    protected $fillable = [
        'country_code', 'tds_label', 'tds_label_local', 'module_label',
        'tax_authority', 'tax_authority_full',
        'fiscal_year_pattern', 'return_frequency', 'return_deadlines',
        'certificate_form_name', 'return_form_name',
        'tin_label', 'tin_format_regex',
        'has_advance_tax', 'has_minimum_tax', 'minimum_tax_rate',
        'corporate_tax_rates', 'currency_code', 'extra',
    ];

    protected $casts = [
        'return_deadlines' => 'array',
        'corporate_tax_rates' => 'array',
        'has_advance_tax' => 'boolean',
        'has_minimum_tax' => 'boolean',
        'minimum_tax_rate' => 'decimal:2',
        'extra' => 'array',
    ];

    public static function forCountry(string $code): ?self
    {
        return static::where('country_code', strtoupper($code))->first();
    }
}
