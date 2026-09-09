<?php

namespace Database\Seeders;

use App\Models\Medical\Medicine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Bangladesh medicine reference catalog (per institute, idempotent).
 *
 * Common generics with popular local brands, strengths and plausible MRPs
 * for demo/dev use — a starting catalog, not a DGDA registry. Safe to
 * re-run: rows match on (institute_id, code) and existing rows are kept.
 */
class MedicalMedicineSeeder extends Seeder
{
    public function run(): void
    {
        $instituteIds = DB::table('institutes')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->pluck('id');

        if ($instituteIds->isEmpty()) {
            $this->command?->warn('MedicalMedicineSeeder skipped: no institute found.');
            return;
        }

        $now = now();
        $total = 0;
        foreach ($instituteIds as $instituteId) {
            foreach ($this->catalog() as $row) {
                // `medicines.code` is globally unique — namespace per institute.
                $row['code'] = 'MED-'.$instituteId.'-'.substr($row['code'], 4);
                if (Medicine::where('code', $row['code'])->exists()) {
                    continue;
                }
                Medicine::create($row + [
                    'institute_id' => $instituteId,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $total++;
            }
        }

        $this->command?->info("MedicalMedicineSeeder: {$total} new medicine(s) across {$instituteIds->count()} institute(s).");
    }

    /**
     * [code, generic, brand, strength, form, unit, pack, category, maker, price, reorder]
     */
    private function catalog(): array
    {
        $raw = [
            ['MED-101', 'Paracetamol', 'Napa 500mg', '500mg', 'Tablet', 'Strip', 10, 'Analgesic', 'Square', 12.00, 100],
            ['MED-102', 'Paracetamol', 'Napa Extend 665mg', '665mg', 'Tablet', 'Strip', 10, 'Analgesic', 'Square', 25.00, 60],
            ['MED-103', 'Paracetamol', 'Ace 500mg', '500mg', 'Tablet', 'Strip', 10, 'Analgesic', 'Square', 10.00, 100],
            ['MED-104', 'Amoxicillin', 'Amoxil 500mg', '500mg', 'Capsule', 'Strip', 10, 'Antibiotic', 'Square', 80.00, 40],
            ['MED-105', 'Azithromycin', 'Zimax 500mg', '500mg', 'Tablet', 'Strip', 6, 'Antibiotic', 'Square', 210.00, 30],
            ['MED-106', 'Ciprofloxacin', 'Cipro 500mg', '500mg', 'Tablet', 'Strip', 10, 'Antibiotic', 'Square', 90.00, 30],
            ['MED-107', 'Cefixime', 'Cef-3 400mg', '400mg', 'Capsule', 'Strip', 10, 'Antibiotic', 'Square', 350.00, 20],
            ['MED-108', 'Doxycycline', 'Doxin 100mg', '100mg', 'Capsule', 'Strip', 10, 'Antibiotic', 'Square', 60.00, 30],
            ['MED-109', 'Metronidazole', 'Filmet 400mg', '400mg', 'Tablet', 'Strip', 10, 'Antibiotic', 'Square', 25.00, 40],
            ['MED-110', 'Erythromycin', 'Eryth 500mg', '500mg', 'Tablet', 'Strip', 10, 'Antibiotic', 'Square', 120.00, 20],
            ['MED-111', 'Levofloxacin', 'Levoxin 500mg', '500mg', 'Tablet', 'Strip', 10, 'Antibiotic', 'Beximco', 180.00, 20],
            ['MED-112', 'Fluconazole', 'Flucon 150mg', '150mg', 'Capsule', 'Strip', 4, 'Antifungal', 'Square', 140.00, 20],
            ['MED-113', 'Albendazole', 'Almex 400mg', '400mg', 'Tablet', 'Strip', 2, 'Anthelmintic', 'Square', 20.00, 50],
            ['MED-114', 'Ivermectin', 'Ivera 6mg', '6mg', 'Tablet', 'Strip', 10, 'Anthelmintic', 'Beximco', 100.00, 20],
            ['MED-115', 'Metformin', 'Comet 500mg', '500mg', 'Tablet', 'Strip', 30, 'Antidiabetic', 'Square', 90.00, 60],
            ['MED-116', 'Metformin', 'Glycomet 850mg', '850mg', 'Tablet', 'Strip', 30, 'Antidiabetic', 'Square', 150.00, 40],
            ['MED-117', 'Gliclazide', 'Diamicron MR 60mg', '60mg', 'Tablet', 'Strip', 30, 'Antidiabetic', 'Servier', 300.00, 30],
            ['MED-118', 'Omeprazole', 'Losectil 20mg', '20mg', 'Capsule', 'Strip', 30, 'PPI', 'Square', 120.00, 60],
            ['MED-119', 'Esomeprazole', 'Esotid 20mg', '20mg', 'Capsule', 'Strip', 28, 'PPI', 'Square', 196.00, 40],
            ['MED-120', 'Pantoprazole', 'Pantonix 40mg', '40mg', 'Tablet', 'Strip', 30, 'PPI', 'Square', 240.00, 30],
            ['MED-121', 'Domperidone', 'Domiren 10mg', '10mg', 'Tablet', 'Strip', 30, 'Prokinetic', 'Square', 90.00, 40],
            ['MED-122', 'Ondansetron', 'Emistat 8mg', '8mg', 'Tablet', 'Strip', 10, 'Antiemetic', 'Square', 100.00, 30],
            ['MED-123', 'Atorvastatin', 'Tiglor 10mg', '10mg', 'Tablet', 'Strip', 30, 'Statin', 'Square', 180.00, 40],
            ['MED-124', 'Rosuvastatin', 'Rosuvas 10mg', '10mg', 'Tablet', 'Strip', 30, 'Statin', 'Square', 270.00, 30],
            ['MED-125', 'Amlodipine', 'Amlocard 5mg', '5mg', 'Tablet', 'Strip', 30, 'Antihypertensive', 'Square', 90.00, 50],
            ['MED-126', 'Losartan', 'Losartil 50mg', '50mg', 'Tablet', 'Strip', 30, 'Antihypertensive', 'Square', 150.00, 40],
            ['MED-127', 'Atenolol', 'Tenolol 50mg', '50mg', 'Tablet', 'Strip', 30, 'Antihypertensive', 'Square', 60.00, 40],
            ['MED-128', 'Bisoprolol', 'Bisolol 5mg', '5mg', 'Tablet', 'Strip', 30, 'Antihypertensive', 'Square', 120.00, 30],
            ['MED-129', 'Aspirin', 'Ecosprin 75mg', '75mg', 'Tablet', 'Strip', 30, 'Antiplatelet', 'Square', 45.00, 50],
            ['MED-130', 'Clopidogrel', 'Clopilet 75mg', '75mg', 'Tablet', 'Strip', 30, 'Antiplatelet', 'Sun Pharma', 300.00, 20],
            ['MED-131', 'Furosemide', 'Fusid 40mg', '40mg', 'Tablet', 'Strip', 30, 'Diuretic', 'Square', 75.00, 30],
            ['MED-132', 'Montelukast', 'Monas 10mg', '10mg', 'Tablet', 'Strip', 30, 'Anti-asthma', 'Square', 300.00, 30],
            ['MED-133', 'Salbutamol', 'Azmasol Inhaler', '100mcg/dose', 'Inhaler', 'Box', 1, 'Anti-asthma', 'Square', 350.00, 20],
            ['MED-134', 'Cetirizine', 'Alcet 10mg', '10mg', 'Tablet', 'Strip', 30, 'Antihistamine', 'Square', 90.00, 50],
            ['MED-135', 'Fexofenadine', 'Fexo 120mg', '120mg', 'Tablet', 'Strip', 30, 'Antihistamine', 'Square', 210.00, 30],
            ['MED-136', 'Loratadine', 'Loratin 10mg', '10mg', 'Tablet', 'Strip', 30, 'Antihistamine', 'Square', 120.00, 30],
            ['MED-137', 'Prednisolone', 'Prednisol 5mg', '5mg', 'Tablet', 'Strip', 30, 'Steroid', 'Square', 60.00, 30],
            ['MED-138', 'Thyroxine', 'Thyrox 50mcg', '50mcg', 'Tablet', 'Strip', 30, 'Thyroid', 'Square', 90.00, 30],
            ['MED-139', 'Calcium + Vitamin D3', 'Calbo-D', '500mg+400IU', 'Tablet', 'Strip', 30, 'Supplement', 'Square', 240.00, 40],
            ['MED-140', 'Iron + Folic Acid', 'Hemax', '100mg+500mcg', 'Capsule', 'Strip', 30, 'Supplement', 'Square', 180.00, 30],
            ['MED-141', 'Multivitamin', 'Multivit Syrup', '100ml', 'Syrup', 'Bottle', 1, 'Supplement', 'Square', 150.00, 20],
            ['MED-142', 'ORS', 'Orsaline-N', '500ml', 'Sachet', 'Box', 20, 'Rehydration', 'SMC', 120.00, 60],
            ['MED-143', 'Zinc', 'Pep-2 20mg', '20mg', 'Tablet', 'Strip', 10, 'Supplement', 'Square', 50.00, 40],
            ['MED-144', 'Diazepam', 'Sedil 5mg', '5mg', 'Tablet', 'Strip', 30, 'Sedative', 'Square', 45.00, 20],
            ['MED-145', 'Alprazolam', 'Anxid 0.5mg', '0.5mg', 'Tablet', 'Strip', 30, 'Anxiolytic', 'Square', 90.00, 20],
            ['MED-146', 'Amitriptyline', 'Amit 10mg', '10mg', 'Tablet', 'Strip', 30, 'Antidepressant', 'Square', 60.00, 20],
            ['MED-147', 'Diclofenac', 'Voltaren 50mg', '50mg', 'Tablet', 'Strip', 20, 'NSAID', 'Novartis', 80.00, 30],
            ['MED-148', 'Ibuprofen', 'Bufen 400mg', '400mg', 'Tablet', 'Strip', 30, 'NSAID', 'Square', 90.00, 40],
            ['MED-149', 'Naproxen', 'Naprosyn 500mg', '500mg', 'Tablet', 'Strip', 20, 'NSAID', 'Square', 100.00, 20],
            ['MED-150', 'Ambroxol', 'Ambrox Syrup', '100ml', 'Syrup', 'Bottle', 1, 'Expectorant', 'Square', 110.00, 30],
        ];

        return array_map(fn ($r) => [
            'code' => $r[0],
            'generic_name' => $r[1],
            'brand_name' => $r[2],
            'strength' => $r[3],
            'dosage_form' => $r[4],
            'unit' => $r[5],
            'pack_size' => $r[6],
            'category' => $r[7],
            'selling_price' => $r[9],
            'purchase_price' => round($r[9] * 0.85, 2),
            'reorder_level' => $r[10],
        ], $raw);
    }
}
