<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MedicalDatabaseSeeder extends Seeder
{
    /**
     * Master seeder for the HMS (medical) module.
     *
     * Single entry point for ALL medical seed data:
     *
     *   php artisan db:seed --class=MedicalDatabaseSeeder
     *
     * To add a future seeder (medicines, lab tests, pharmacy stock, ...),
     * append it to the $this->call() array below — deployment scripts never
     * need to change.
     */
    public function run(): void
    {
        $this->call([
            MedicalDepartmentSpecialtySeeder::class,
            // Future medical seeders go here, e.g.:
            // MedicalPharmacySeeder::class,
            // MedicalLabSeeder::class,
        ]);
    }
}
