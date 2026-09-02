<?php

namespace App\Console\Commands;

use App\Models\Institute;
use App\Services\Demo\DemoDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class DemoBusinessSeederCommand extends Command
{
    protected $signature = 'demo:seed-all {--force : Re-seed even if data exists} {--country=Bangladesh : Country}';

    protected $description = 'Create lightweight demo businesses for every industry/sub-industry';

    private array $businesses = [
        ['industry' => 'education', 'sub' => 'institution', 'name' => 'Institution Demo'],
        ['industry' => 'education', 'sub' => 'school', 'name' => 'School Demo'],
        ['industry' => 'education', 'sub' => 'college', 'name' => 'College Demo'],
        ['industry' => 'education', 'sub' => 'vocational_institute', 'name' => 'Vocational Institute Demo'],
        ['industry' => 'education', 'sub' => 'technical_training_center', 'name' => 'Technical Training Center Demo'],
        ['industry' => 'healthcare', 'sub' => 'hospital', 'name' => 'Hospital Demo'],
        ['industry' => 'healthcare', 'sub' => 'clinic', 'name' => 'Clinic Demo'],
        ['industry' => 'healthcare', 'sub' => 'pharmacy', 'name' => 'Pharmacy Demo'],
        ['industry' => 'healthcare', 'sub' => 'diagnostic_center', 'name' => 'Diagnostic Center Demo'],
        ['industry' => 'information_technology', 'sub' => 'software_company', 'name' => 'Software Company Demo'],
        ['industry' => 'information_technology', 'sub' => 'it_services', 'name' => 'IT Services Demo'],
        ['industry' => 'information_technology', 'sub' => 'digital_agency', 'name' => 'Digital Agency Demo'],
        ['industry' => 'finance', 'sub' => 'bank', 'name' => 'Bank Demo'],
        ['industry' => 'finance', 'sub' => 'microfinance', 'name' => 'Microfinance Demo'],
        ['industry' => 'finance', 'sub' => 'insurance', 'name' => 'Insurance Demo'],
        ['industry' => 'retail', 'sub' => 'general_store', 'name' => 'General Store Demo'],
        ['industry' => 'retail', 'sub' => 'supermarket', 'name' => 'Supermarket Demo'],
        ['industry' => 'retail', 'sub' => 'electronics', 'name' => 'Electronics Store Demo'],
        ['industry' => 'manufacturing', 'sub' => 'garments', 'name' => 'Garments Demo'],
        ['industry' => 'manufacturing', 'sub' => 'food_processing', 'name' => 'Food Processing Demo'],
        ['industry' => 'manufacturing', 'sub' => 'pharmaceutical', 'name' => 'Pharmaceutical Demo'],
        ['industry' => 'real_estate', 'sub' => null, 'name' => 'Real Estate Demo'],
        ['industry' => 'transport', 'sub' => null, 'name' => 'Transport Demo'],
        ['industry' => 'restaurant', 'sub' => null, 'name' => 'Restaurant Demo'],
        ['industry' => 'hotels', 'sub' => null, 'name' => 'Hotel Demo'],
        ['industry' => 'personal_finance', 'sub' => null, 'name' => 'Personal Finance Demo'],
        ['industry' => 'other', 'sub' => null, 'name' => 'Other Business Demo'],
    ];

    public function handle(DemoDataService $demo): int
    {
        if (app()->environment('production') && ! config('app.demo_seed_enabled', env('DEMO_SEED_ENABLED', false))) {
            $this->error('demo:seed-all is disabled in production. Set DEMO_SEED_ENABLED=true to enable explicitly, or run in local/testing.');
            return self::FAILURE;
        }
        $country = $this->option('country');
        $force = (bool) $this->option('force');
        if (app()->environment('production') && ! $force) {
            $this->error('Refusing to run demo:seed-all in production without --force.');
            return self::FAILURE;
        }
        $password = '12345678';

        $this->info('Demo Business Seeder — creating '.count($this->businesses).' organizations...');
        $this->newLine();

        $results = [];
        $created = 0;
        $skipped = 0;

        foreach ($this->businesses as $biz) {
            $industry = $biz['industry'];
            $subIndustry = $biz['sub'];
            $ownerEmail = $demo->ownerEmail($industry, $subIndustry);

            $existingUser = \App\Models\User::where('email', $ownerEmail)->first();

            if ($existingUser && ! $force) {
                $existingInstitute = Institute::whereHas('memberships', function ($q) use ($existingUser) {
                    $q->where('user_id', $existingUser->id);
                })->first();

                if ($existingInstitute) {
                    $this->line("  <comment>SKIP</comment> {$biz['name']} (owner: {$ownerEmail})");
                    $skipped++;
                    $results[] = ['name' => $biz['name'], 'email' => $ownerEmail, 'status' => 'exists'];
                    continue;
                }
            }

            $institute = $this->createInstitute($industry, $subIndustry, $biz['name'], $country);

            $owner = $demo->createOwnerAccount($institute, $industry, $subIndustry, $password);

            $result = $demo->seed($institute, $owner, ['force' => $force]);

            if (isset($result['skipped'])) {
                $this->line("  <comment>SKIP</comment> {$institute->name} (demo data exists)");
                $skipped++;
                $results[] = ['name' => $institute->name, 'email' => $ownerEmail, 'status' => 'data exists'];
            } else {
                $this->line("  <info>OK</info>   {$institute->name} (owner: {$ownerEmail})");
                $created++;
                $results[] = ['name' => $institute->name, 'email' => $ownerEmail, 'status' => 'created'];
            }
        }

        $this->newLine();
        $this->info("Summary: {$created} created, {$skipped} skipped, ".count($this->businesses)." total");
        $this->newLine();

        $this->table(['Organization', 'Owner Email', 'Status'], $results);
        $this->newLine();
        $this->info('All owner accounts use password: '.$password);
        $this->info('Login URL: '.route('login'));

        return self::SUCCESS;
    }

    private function createInstitute(string $industry, ?string $subIndustry, string $name, string $country): Institute
    {
        $slug = Str::slug($name);
        $suffix = 2;
        while (Institute::where('slug', $slug)->exists()) {
            $slug = Str::slug($name).'-'.$suffix;
            $suffix++;
        }

        $countryModel = \App\Models\Country::firstOrCreate(
            ['name' => $country],
            ['iso2' => strtoupper(substr($country, 0, 2)), 'iso3' => strtoupper(substr($country, 0, 3)), 'phone_code' => '880', 'status' => true]
        );
        // Ensure BD lookup works for Bangladesh
        if ($country === 'Bangladesh') {
            $bd = \App\Models\Country::where('iso2', 'BD')->first();
            if ($bd) {
                $countryModel = $bd;
            }
        }

        $institute = Institute::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'industry' => $industry,
                'sub_industry' => $subIndustry,
                'country' => $country,
                'country_id' => $countryModel->id,
                'status' => 'active',
            ]
        );

        // Back-fill country_id for legacy demo institutes
        if ($institute->country_id === null) {
            $institute->update(['country_id' => $countryModel->id]);
        }

        return $institute;
    }
}
