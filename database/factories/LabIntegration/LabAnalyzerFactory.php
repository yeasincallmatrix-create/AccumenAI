<?php

namespace Database\Factories\LabIntegration;

use App\Models\Institute;
use App\Models\LabIntegration\LabAnalyzer;
use Illuminate\Database\Eloquent\Factories\Factory;

class LabAnalyzerFactory extends Factory
{
    protected $model = LabAnalyzer::class;

    public function definition(): array
    {
        return [
            // No InstituteFactory exists in this repo; default to the first
            // institute so factory-only creation works in seeded DBs.
            // Tests should pass an explicit institute_id (tenant safety).
            'institute_id' => fn () => Institute::first()?->id ?? Institute::create([
                'name' => 'Factory Hospital',
                'slug' => 'factory-hospital-'.uniqid(),
                'industry' => 'healthcare',
                'sub_industry' => 'hospital',
                'country' => 'Bangladesh',
                'status' => 'active',
            ])->id,
            'code' => 'TEST-'.strtoupper($this->faker->bothify('??##')),
            'name' => 'Test Analyzer '.$this->faker->word(),
            'manufacturer' => 'Test',
            'model' => 'TEST-100',
            'serial_no' => 'SN-'.strtoupper($this->faker->bothify('########')),
            'instrument_type' => 'hematology',
            'protocol' => 'astm',
            'adapter_key' => 'generic_astm',
            'adapter_version' => 'v1',
            'connection_type' => 'tcp',
            'is_enabled' => true,
            'status' => 'active',
            'capabilities' => [
                'result_upload' => true,
                'worklist' => false,
                'query' => false,
                'bidirectional' => false,
            ],
        ];
    }
}
