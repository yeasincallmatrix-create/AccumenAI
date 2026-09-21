<?php

namespace Tests\Concerns;

trait ResolvesTestIds
{
    protected function bdCountryId(): int
    {
        static $id = null;
        return $id ??= \App\Models\Country::where('iso2', 'BD')->value('id')
            ?? \App\Models\Country::first()->id;
    }

    protected function nonBdCountryId(): int
    {
        static $id = null;
        return $id ??= \App\Models\Country::where('iso2', '!=', 'BD')->value('id')
            ?? \App\Models\Country::first()->id;
    }

    protected function educationIndustryId(): int
    {
        static $id = null;
        return $id ??= \App\Models\Industry::where('slug', 'education')->value('id')
            ?? \App\Models\Industry::first()->id;
    }

    protected function schoolSubIndustryId(): int
    {
        static $id = null;
        return $id ??= \App\Models\SubIndustry::where('slug', 'school')->value('id')
            ?? \App\Models\SubIndustry::first()->id;
    }
}
