<?php

namespace Database\Seeders;

use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
use App\Models\ClassGrade;
use App\Models\Country;
use App\Models\EducationSystem;
use Illuminate\Database\Seeder;

/**
 * SpecificAcademicStructureSeeder
 *
 * Seeds country-specific Academic Structures for 20 countries:
 *   India, Singapore, Malaysia, Kuwait, Qatar, KSA, Italy, Spain,
 *   France, Germany, Portugal, Pakistan, Australia, Canada, New Zealand,
 *   Myanmar, Vietnam, Laos, Cambodia, Maldives.
 *
 * Structure per country:
 *   Country → EducationSystem → AcademicLevel → ClassGrade → AcademicGroup
 *
 * Notes on idempotency:
 *   EducationSystem unique on [country_id, code]
 *   AcademicLevel   unique on [education_system_id, code]
 *   ClassGrade      unique on [academic_level_id, code]
 *   AcademicGroup   unique on [class_grade_id, code]
 *
 * Groups are attached per ClassGrade (inside levels that have streams).
 * This matches the schema in 2026_08_17_100000_create_academic_structure_tables.php
 * and the helper pattern used in AcademicStructureSeeder.php:150-333.
 *
 * Safe to re-run: all writes via updateOrCreate; display_order/status/names
 * are reconciled on each run. Missing countries are skipped with a warning.
 */
class SpecificAcademicStructureSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalog() as $iso2 => $data) {
            $country = Country::where('iso2', $iso2)->first();
            if ($country === null) {
                // Fallback by name in case iso2 not yet filled (e.g. legacy rows)
                $country = Country::where('name', $data['country_name'])->first();
            }
            if ($country === null) {
                $this->command?->warn("SpecificAcademicStructureSeeder: {$data['country_name']} ({$iso2}) not found — skipping.");
                continue;
            }

            $system = $this->system($country, $data['system_code'], $data['system_name'], 1);

            foreach ($data['levels'] as $levelDef) {
                $level = $this->level($country, $system, $levelDef['code'], $levelDef['name'], $levelDef['order']);

                $order = 1;
                foreach ($levelDef['classes'] as $classDef) {
                    $cg = $this->classGrade(
                        $country,
                        $system,
                        $level,
                        $classDef['code'],
                        $classDef['name'],
                        $order,
                        $classDef['sequence'] ?? null
                    );

                    if (! empty($levelDef['groups'])) {
                        $this->groupsForClass($country, $system, $level, $cg, $levelDef['groups']);
                    }
                    $order++;
                }
            }
        }
    }

    /**
     * Full catalog for the 20 countries.
     *
     * @return array<string, array{country_name:string,system_code:string,system_name:string,levels:array}>
     */
    private function catalog(): array
    {
        return [
            // ---------------------------------------------------------------- IN - India
            'IN' => [
                'country_name' => 'India',
                'system_code'  => 'in_national',
                'system_name'  => 'Indian Education System',
                'levels' => [
                    [
                        'code' => 'primary', 'name' => 'Primary', 'order' => 1,
                        'classes' => $this->classesRange(1, 5, 'Class %d'),
                        'groups'  => [],
                    ],
                    [
                        'code' => 'middle', 'name' => 'Middle', 'order' => 2,
                        'classes' => $this->classesRange(6, 8, 'Class %d'),
                        'groups'  => [],
                    ],
                    [
                        'code' => 'secondary', 'name' => 'Secondary', 'order' => 3,
                        'classes' => $this->classesRange(9, 10, 'Class %d'),
                        'groups'  => [
                            ['science', 'Science', 1],
                            ['commerce', 'Commerce', 2],
                            ['arts', 'Arts / Humanities', 3],
                        ],
                    ],
                    [
                        'code' => 'higher_secondary', 'name' => 'Higher Secondary', 'order' => 4,
                        'classes' => $this->classesRange(11, 12, 'Class %d'),
                        'groups'  => [
                            ['science', 'Science', 1],
                            ['commerce', 'Commerce', 2],
                            ['arts', 'Arts / Humanities', 3],
                        ],
                    ],
                    [
                        'code' => 'undergraduate', 'name' => 'Undergraduate', 'order' => 5,
                        'classes' => $this->classesRange(1, 4, 'Year %d'),
                        'groups'  => [],
                    ],
                ],
            ],

            // ---------------------------------------------------------------- SG - Singapore
            'SG' => [
                'country_name' => 'Singapore',
                'system_code'  => 'sg_national',
                'system_name'  => 'Singapore Education System',
                'levels' => [
                    [
                        'code' => 'primary', 'name' => 'Primary', 'order' => 1,
                        'classes' => $this->classesMap([
                            ['1', 'Primary 1'], ['2', 'Primary 2'], ['3', 'Primary 3'],
                            ['4', 'Primary 4'], ['5', 'Primary 5'], ['6', 'Primary 6'],
                        ]),
                        'groups' => [],
                    ],
                    [
                        'code' => 'secondary', 'name' => 'Secondary', 'order' => 2,
                        'classes' => $this->classesMap([
                            ['1', 'Secondary 1'], ['2', 'Secondary 2'], ['3', 'Secondary 3'],
                            ['4', 'Secondary 4'], ['5', 'Secondary 5'],
                        ]),
                        'groups' => [
                            ['science', 'Science', 1],
                            ['arts', 'Arts', 2],
                            ['commerce', 'Commerce', 3],
                        ],
                    ],
                    [
                        'code' => 'jc', 'name' => 'Junior College / Pre-University', 'order' => 3,
                        'classes' => $this->classesMap([
                            ['1', 'JC 1'], ['2', 'JC 2'],
                        ]),
                        'groups' => [
                            ['science', 'Science', 1],
                            ['arts', 'Arts', 2],
                            ['commerce', 'Commerce', 3],
                        ],
                    ],
                    [
                        'code' => 'polytechnic', 'name' => 'Polytechnic', 'order' => 4,
                        'classes' => $this->classesMap([
                            ['1', 'Diploma Year 1'], ['2', 'Diploma Year 2'], ['3', 'Diploma Year 3'],
                        ]),
                        'groups' => [],
                    ],
                    [
                        'code' => 'university', 'name' => 'University', 'order' => 5,
                        'classes' => $this->classesRange(1, 4, 'Year %d'),
                        'groups'  => [],
                    ],
                ],
            ],

            // ---------------------------------------------------------------- MY - Malaysia
            'MY' => [
                'country_name' => 'Malaysia',
                'system_code'  => 'my_national',
                'system_name'  => 'Malaysian Education System',
                'levels' => [
                    [
                        'code' => 'primary', 'name' => 'Primary', 'order' => 1,
                        'classes' => $this->classesMap([
                            ['1', 'Standard 1'], ['2', 'Standard 2'], ['3', 'Standard 3'],
                            ['4', 'Standard 4'], ['5', 'Standard 5'], ['6', 'Standard 6'],
                        ]),
                        'groups' => [],
                    ],
                    [
                        'code' => 'lower_secondary', 'name' => 'Lower Secondary', 'order' => 2,
                        'classes' => $this->classesMap([
                            ['1', 'Form 1'], ['2', 'Form 2'], ['3', 'Form 3'],
                        ]),
                        'groups' => [],
                    ],
                    [
                        'code' => 'upper_secondary', 'name' => 'Upper Secondary', 'order' => 3,
                        'classes' => $this->classesMap([
                            ['4', 'Form 4'], ['5', 'Form 5'],
                        ]),
                        'groups' => [
                            ['science', 'Science', 1],
                            ['arts', 'Arts', 2],
                            ['islamic', 'Islamic Studies', 3],
                            ['technical', 'Technical / Vocational', 4],
                        ],
                    ],
                    [
                        'code' => 'pre_university', 'name' => 'Pre-University', 'order' => 4,
                        'classes' => $this->classesMap([
                            ['sem1', 'Form 6 - Semester 1'], ['sem2', 'Form 6 - Semester 2'], ['sem3', 'Form 6 - Semester 3'],
                        ]),
                        'groups' => [
                            ['science', 'Science', 1],
                            ['arts', 'Arts', 2],
                            ['islamic', 'Islamic Studies', 3],
                            ['technical', 'Technical / Vocational', 4],
                        ],
                    ],
                    [
                        'code' => 'university', 'name' => 'University', 'order' => 5,
                        'classes' => $this->classesRange(1, 4, 'Year %d'),
                        'groups'  => [],
                    ],
                ],
            ],

            // ---------------------------------------------------------------- KW - Kuwait (GCC)
            'KW' => [
                'country_name' => 'Kuwait',
                'system_code'  => 'kw_national',
                'system_name'  => 'Kuwait National Education System',
                'levels' => [
                    [
                        'code' => 'primary', 'name' => 'Primary', 'order' => 1,
                        'classes' => $this->classesRange(1, 6, 'Grade %d'),
                        'groups'  => [],
                    ],
                    [
                        'code' => 'intermediate', 'name' => 'Intermediate / Middle', 'order' => 2,
                        'classes' => $this->classesRange(7, 9, 'Grade %d'),
                        'groups'  => [
                            ['science', 'Science', 1],
                            ['arts', 'Arts / Humanities', 2],
                        ],
                    ],
                    [
                        'code' => 'secondary', 'name' => 'Secondary', 'order' => 3,
                        'classes' => $this->classesRange(10, 12, 'Grade %d'),
                        'groups'  => [
                            ['science', 'Science', 1],
                            ['arts', 'Arts / Humanities', 2],
                        ],
                    ],
                    [
                        'code' => 'university', 'name' => 'University', 'order' => 4,
                        'classes' => $this->classesRange(1, 4, 'Year %d'),
                        'groups'  => [],
                    ],
                ],
            ],

            // ---------------------------------------------------------------- QA - Qatar (GCC)
            'QA' => [
                'country_name' => 'Qatar',
                'system_code'  => 'qa_national',
                'system_name'  => 'Qatar National Education System',
                'levels' => [
                    [
                        'code' => 'primary', 'name' => 'Primary', 'order' => 1,
                        'classes' => $this->classesRange(1, 6, 'Grade %d'),
                        'groups'  => [],
                    ],
                    [
                        'code' => 'intermediate', 'name' => 'Intermediate / Middle', 'order' => 2,
                        'classes' => $this->classesRange(7, 9, 'Grade %d'),
                        'groups'  => [
                            ['science', 'Science', 1],
                            ['arts', 'Arts / Humanities', 2],
                        ],
                    ],
                    [
                        'code' => 'secondary', 'name' => 'Secondary', 'order' => 3,
                        'classes' => $this->classesRange(10, 12, 'Grade %d'),
                        'groups'  => [
                            ['science', 'Science', 1],
                            ['arts', 'Arts / Humanities', 2],
                        ],
                    ],
                    [
                        'code' => 'university', 'name' => 'University', 'order' => 4,
                        'classes' => $this->classesRange(1, 4, 'Year %d'),
                        'groups'  => [],
                    ],
                ],
            ],

            // ---------------------------------------------------------------- SA - Saudi Arabia (GCC)
            'SA' => [
                'country_name' => 'Saudi Arabia',
                'system_code'  => 'sa_national',
                'system_name'  => 'Saudi Arabia National Education System',
                'levels' => [
                    [
                        'code' => 'primary', 'name' => 'Primary', 'order' => 1,
                        'classes' => $this->classesRange(1, 6, 'Grade %d'),
                        'groups'  => [],
                    ],
                    [
                        'code' => 'intermediate', 'name' => 'Intermediate / Middle', 'order' => 2,
                        'classes' => $this->classesRange(7, 9, 'Grade %d'),
                        'groups'  => [
                            ['science', 'Science', 1],
                            ['arts', 'Arts / Humanities', 2],
                        ],
                    ],
                    [
                        'code' => 'secondary', 'name' => 'Secondary', 'order' => 3,
                        'classes' => $this->classesRange(10, 12, 'Grade %d'),
                        'groups'  => [
                            ['science', 'Science', 1],
                            ['arts', 'Arts / Humanities', 2],
                        ],
                    ],
                    [
                        'code' => 'university', 'name' => 'University', 'order' => 4,
                        'classes' => $this->classesRange(1, 4, 'Year %d'),
                        'groups'  => [],
                    ],
                ],
            ],

            // ---------------------------------------------------------------- IT - Italy
            'IT' => [
                'country_name' => 'Italy',
                'system_code'  => 'it_national',
                'system_name'  => 'Italian Education System',
                'levels' => [
                    [
                        'code' => 'primary', 'name' => 'Scuola Primaria', 'order' => 1,
                        'classes' => $this->classesRange(1, 5, 'Class %d'),
                        'groups'  => [],
                    ],
                    [
                        'code' => 'middle', 'name' => 'Scuola Secondaria di Primo Grado', 'order' => 2,
                        'classes' => $this->classesRange(1, 3, 'Class %d'),
                        'groups'  => [],
                    ],
                    [
                        'code' => 'secondary', 'name' => 'Scuola Secondaria di Secondo Grado', 'order' => 3,
                        'classes' => $this->classesRange(1, 5, 'Class %d'),
                        'groups'  => [
                            ['classical', 'Liceo Classico (Classical)', 1],
                            ['scientific', 'Liceo Scientifico (Scientific)', 2],
                            ['linguistic', 'Liceo Linguistico (Languages)', 3],
                            ['artistic', 'Liceo Artistico (Arts)', 4],
                            ['technical', 'Tecnico (Technical)', 5],
                        ],
                    ],
                    [
                        'code' => 'university', 'name' => 'University', 'order' => 4,
                        'classes' => $this->classesRange(1, 5, 'Year %d'),
                        'groups'  => [],
                    ],
                ],
            ],

            // ---------------------------------------------------------------- ES - Spain
            'ES' => [
                'country_name' => 'Spain',
                'system_code'  => 'es_national',
                'system_name'  => 'Spanish Education System',
                'levels' => [
                    [
                        'code' => 'primary', 'name' => 'Educación Primaria', 'order' => 1,
                        'classes' => $this->classesRange(1, 6, 'Grade %d'),
                        'groups'  => [],
                    ],
                    [
                        'code' => 'secondary', 'name' => 'Educación Secundaria Obligatoria (ESO)', 'order' => 2,
                        'classes' => $this->classesRange(1, 4, 'Grade %d'),
                        'groups'  => [],
                    ],
                    [
                        'code' => 'bachillerato', 'name' => 'Bachillerato', 'order' => 3,
                        'classes' => $this->classesRange(1, 2, 'Grade %d'),
                        'groups'  => [
                            ['sciences', 'Sciences', 1],
                            ['humanities', 'Humanities & Social Sciences', 2],
                            ['arts', 'Arts', 3],
                        ],
                    ],
                    [
                        'code' => 'university', 'name' => 'University', 'order' => 4,
                        'classes' => $this->classesRange(1, 4, 'Year %d'),
                        'groups'  => [],
                    ],
                ],
            ],

            // ---------------------------------------------------------------- FR - France
            'FR' => [
                'country_name' => 'France',
                'system_code'  => 'fr_national',
                'system_name'  => 'French Education System',
                'levels' => [
                    [
                        'code' => 'primary', 'name' => 'École Primaire', 'order' => 1,
                        'classes' => $this->classesMap([
                            ['cp', 'CP'], ['ce1', 'CE1'], ['ce2', 'CE2'], ['cm1', 'CM1'], ['cm2', 'CM2'],
                        ]),
                        'groups' => [],
                    ],
                    [
                        'code' => 'middle', 'name' => 'Collège', 'order' => 2,
                        'classes' => $this->classesMap([
                            ['6eme', '6ème'], ['5eme', '5ème'], ['4eme', '4ème'], ['3eme', '3ème'],
                        ]),
                        'groups' => [],
                    ],
                    [
                        'code' => 'secondary', 'name' => 'Lycée', 'order' => 3,
                        'classes' => $this->classesMap([
                            ['2nde', '2nde'], ['1ere', '1ère'], ['terminale', 'Terminale'],
                        ]),
                        'groups' => [
                            ['sciences', 'Sciences', 1],
                            ['economics', 'Economics & Social', 2],
                            ['humanities', 'Humanities', 3],
                            ['technical', 'Technical', 4],
                        ],
                    ],
                    [
                        'code' => 'university', 'name' => 'University', 'order' => 4,
                        'classes' => $this->classesMap([
                            ['l1', 'Licence 1'], ['l2', 'Licence 2'], ['l3', 'Licence 3'],
                            ['m1', 'Master 1'], ['m2', 'Master 2'],
                        ]),
                        'groups' => [],
                    ],
                ],
            ],

            // ---------------------------------------------------------------- DE - Germany
            'DE' => [
                'country_name' => 'Germany',
                'system_code'  => 'de_national',
                'system_name'  => 'German Education System',
                'levels' => [
                    [
                        'code' => 'primary', 'name' => 'Grundschule', 'order' => 1,
                        'classes' => $this->classesRange(1, 4, 'Class %d'),
                        'groups'  => [],
                    ],
                    [
                        'code' => 'lower_secondary', 'name' => 'Sekundarstufe I', 'order' => 2,
                        'classes' => $this->classesRange(5, 10, 'Class %d'),
                        'groups'  => [],
                    ],
                    [
                        'code' => 'upper_secondary', 'name' => 'Sekundarstufe II', 'order' => 3,
                        'classes' => $this->classesRange(11, 13, 'Class %d'),
                        'groups'  => [
                            ['gymnasium', 'Gymnasium', 1],
                            ['realschule', 'Realschule', 2],
                            ['hauptschule', 'Hauptschule', 3],
                            ['gesamtschule', 'Gesamtschule', 4],
                        ],
                    ],
                    [
                        'code' => 'university', 'name' => 'University', 'order' => 4,
                        'classes' => $this->classesMap([
                            ['1', 'Semester 1'], ['2', 'Semester 2'], ['3', 'Semester 3'],
                            ['4', 'Semester 4'], ['5', 'Semester 5'], ['6', 'Semester 6'],
                        ]),
                        'groups' => [],
                    ],
                ],
            ],

            // ---------------------------------------------------------------- PT - Portugal
            'PT' => [
                'country_name' => 'Portugal',
                'system_code'  => 'pt_national',
                'system_name'  => 'Portuguese Education System',
                'levels' => [
                    [
                        'code' => 'primary_1', 'name' => '1º Ciclo', 'order' => 1,
                        'classes' => $this->classesMap([
                            ['1', '1º ano'], ['2', '2º ano'], ['3', '3º ano'], ['4', '4º ano'],
                        ]),
                        'groups' => [],
                    ],
                    [
                        'code' => 'primary_2', 'name' => '2º Ciclo', 'order' => 2,
                        'classes' => $this->classesMap([
                            ['5', '5º ano'], ['6', '6º ano'],
                        ]),
                        'groups' => [],
                    ],
                    [
                        'code' => 'middle', 'name' => '3º Ciclo', 'order' => 3,
                        'classes' => $this->classesMap([
                            ['7', '7º ano'], ['8', '8º ano'], ['9', '9º ano'],
                        ]),
                        'groups' => [],
                    ],
                    [
                        'code' => 'secondary', 'name' => 'Secundário', 'order' => 4,
                        'classes' => $this->classesMap([
                            ['10', '10º ano'], ['11', '11º ano'], ['12', '12º ano'],
                        ]),
                        'groups' => [
                            ['sciences', 'Ciências e Tecnologias (Sciences)', 1],
                            ['economics', 'Ciências Socioeconómicas (Economics)', 2],
                            ['humanities', 'Humanidades (Humanities)', 3],
                            ['arts', 'Artes Visuais (Arts)', 4],
                        ],
                    ],
                    [
                        'code' => 'university', 'name' => 'University', 'order' => 5,
                        'classes' => $this->classesMap([
                            ['1', '1º ano'], ['2', '2º ano'], ['3', '3º ano'], ['4', '4º ano'],
                        ]),
                        'groups' => [],
                    ],
                ],
            ],

            // ---------------------------------------------------------------- PK - Pakistan
            'PK' => [
                'country_name' => 'Pakistan',
                'system_code'  => 'pk_national',
                'system_name'  => 'Pakistan Education System',
                'levels' => [
                    [
                        'code' => 'primary', 'name' => 'Primary', 'order' => 1,
                        'classes' => $this->classesRange(1, 5, 'Class %d'),
                        'groups'  => [],
                    ],
                    [
                        'code' => 'middle', 'name' => 'Middle', 'order' => 2,
                        'classes' => $this->classesRange(6, 8, 'Class %d'),
                        'groups'  => [],
                    ],
                    [
                        'code' => 'secondary', 'name' => 'Secondary', 'order' => 3,
                        'classes' => $this->classesRange(9, 10, 'Class %d'),
                        'groups'  => [
                            ['pre_medical', 'Pre-Medical', 1],
                            ['pre_engineering', 'Pre-Engineering', 2],
                            ['cs', 'Computer Science', 3],
                            ['humanities', 'Humanities', 4],
                            ['commerce', 'Commerce', 5],
                        ],
                    ],
                    [
                        'code' => 'higher_secondary', 'name' => 'Higher Secondary', 'order' => 4,
                        'classes' => $this->classesRange(11, 12, 'Class %d'),
                        'groups'  => [
                            ['pre_medical', 'Pre-Medical', 1],
                            ['pre_engineering', 'Pre-Engineering', 2],
                            ['cs', 'Computer Science', 3],
                            ['humanities', 'Humanities', 4],
                            ['commerce', 'Commerce', 5],
                        ],
                    ],
                    [
                        'code' => 'university', 'name' => 'University', 'order' => 5,
                        'classes' => $this->classesRange(1, 4, 'Year %d'),
                        'groups'  => [],
                    ],
                ],
            ],

            // ---------------------------------------------------------------- AU - Australia
            'AU' => [
                'country_name' => 'Australia',
                'system_code'  => 'au_national',
                'system_name'  => 'Australian Education System',
                'levels' => [
                    [
                        'code' => 'primary', 'name' => 'Primary', 'order' => 1,
                        'classes' => $this->classesMap([
                            ['k', 'Kindergarten'], ['1', 'Grade 1'], ['2', 'Grade 2'],
                            ['3', 'Grade 3'], ['4', 'Grade 4'], ['5', 'Grade 5'], ['6', 'Grade 6'],
                        ]),
                        'groups' => [],
                    ],
                    [
                        'code' => 'junior', 'name' => 'Junior Secondary', 'order' => 2,
                        'classes' => $this->classesRange(7, 10, 'Grade %d'),
                        'groups'  => [],
                    ],
                    [
                        'code' => 'senior', 'name' => 'Senior Secondary', 'order' => 3,
                        'classes' => $this->classesRange(11, 12, 'Grade %d'),
                        'groups'  => [
                            ['sciences', 'Sciences', 1],
                            ['humanities', 'Humanities', 2],
                            ['arts', 'Arts', 3],
                            ['business', 'Business', 4],
                            ['vet', 'Vocational / VET', 5],
                        ],
                    ],
                    [
                        'code' => 'tertiary', 'name' => 'University / Tertiary', 'order' => 4,
                        'classes' => $this->classesRange(1, 4, 'Year %d'),
                        'groups'  => [],
                    ],
                ],
            ],

            // ---------------------------------------------------------------- CA - Canada
            'CA' => [
                'country_name' => 'Canada',
                'system_code'  => 'ca_national',
                'system_name'  => 'Canadian Education System',
                'levels' => [
                    [
                        'code' => 'elementary', 'name' => 'Elementary', 'order' => 1,
                        'classes' => $this->classesMap([
                            ['k', 'Kindergarten'],
                            ['1', 'Grade 1'], ['2', 'Grade 2'], ['3', 'Grade 3'],
                            ['4', 'Grade 4'], ['5', 'Grade 5'], ['6', 'Grade 6'],
                            ['7', 'Grade 7'], ['8', 'Grade 8'],
                        ]),
                        'groups' => [],
                    ],
                    [
                        'code' => 'secondary', 'name' => 'Secondary', 'order' => 2,
                        'classes' => $this->classesRange(9, 12, 'Grade %d'),
                        'groups'  => [
                            ['sciences', 'Sciences', 1],
                            ['humanities', 'Humanities', 2],
                            ['arts', 'Arts', 3],
                            ['business', 'Business', 4],
                            ['vocational', 'Vocational', 5],
                        ],
                    ],
                    [
                        'code' => 'tertiary', 'name' => 'College / University', 'order' => 3,
                        'classes' => $this->classesRange(1, 4, 'Year %d'),
                        'groups'  => [],
                    ],
                ],
            ],

            // ---------------------------------------------------------------- NZ - New Zealand
            'NZ' => [
                'country_name' => 'New Zealand',
                'system_code'  => 'nz_national',
                'system_name'  => 'New Zealand Education System',
                'levels' => [
                    [
                        'code' => 'primary', 'name' => 'Primary', 'order' => 1,
                        'classes' => $this->classesRange(1, 8, 'Year %d'),
                        'groups'  => [],
                    ],
                    [
                        'code' => 'secondary', 'name' => 'Secondary', 'order' => 2,
                        'classes' => $this->classesRange(9, 13, 'Year %d'),
                        'groups'  => [
                            ['sciences', 'Sciences', 1],
                            ['humanities', 'Humanities', 2],
                            ['arts', 'Arts', 3],
                            ['technology', 'Technology', 4],
                            ['business', 'Business', 5],
                        ],
                    ],
                    [
                        'code' => 'tertiary', 'name' => 'Tertiary', 'order' => 3,
                        'classes' => $this->classesRange(1, 4, 'Year %d'),
                        'groups'  => [],
                    ],
                ],
            ],

            // ---------------------------------------------------------------- MM - Myanmar
            'MM' => [
                'country_name' => 'Myanmar',
                'system_code'  => 'mm_national',
                'system_name'  => 'Myanmar Education System',
                'levels' => [
                    [
                        'code' => 'primary', 'name' => 'Primary', 'order' => 1,
                        'classes' => $this->classesRange(1, 5, 'Grade %d'),
                        'groups'  => [],
                    ],
                    [
                        'code' => 'middle', 'name' => 'Middle', 'order' => 2,
                        'classes' => $this->classesRange(6, 9, 'Grade %d'),
                        'groups'  => [],
                    ],
                    [
                        'code' => 'high', 'name' => 'High', 'order' => 3,
                        'classes' => $this->classesRange(10, 12, 'Grade %d'),
                        'groups'  => [
                            ['science', 'Science', 1],
                            ['arts', 'Arts', 2],
                            ['economics', 'Economics', 3],
                        ],
                    ],
                    [
                        'code' => 'university', 'name' => 'University', 'order' => 4,
                        'classes' => $this->classesRange(1, 4, 'Year %d'),
                        'groups'  => [],
                    ],
                ],
            ],

            // ---------------------------------------------------------------- VN - Vietnam
            'VN' => [
                'country_name' => 'Vietnam',
                'system_code'  => 'vn_national',
                'system_name'  => 'Vietnam Education System',
                'levels' => [
                    [
                        'code' => 'primary', 'name' => 'Primary', 'order' => 1,
                        'classes' => $this->classesMap([
                            ['1', 'Lớp 1'], ['2', 'Lớp 2'], ['3', 'Lớp 3'], ['4', 'Lớp 4'], ['5', 'Lớp 5'],
                        ]),
                        'groups' => [],
                    ],
                    [
                        'code' => 'lower_secondary', 'name' => 'Lower Secondary', 'order' => 2,
                        'classes' => $this->classesMap([
                            ['6', 'Lớp 6'], ['7', 'Lớp 7'], ['8', 'Lớp 8'], ['9', 'Lớp 9'],
                        ]),
                        'groups' => [],
                    ],
                    [
                        'code' => 'upper_secondary', 'name' => 'Upper Secondary', 'order' => 3,
                        'classes' => $this->classesMap([
                            ['10', 'Lớp 10'], ['11', 'Lớp 11'], ['12', 'Lớp 12'],
                        ]),
                        'groups' => [
                            ['natural_sciences', 'Natural Sciences', 1],
                            ['social_sciences', 'Social Sciences', 2],
                            ['math_it', 'Mathematics & IT', 3],
                        ],
                    ],
                    [
                        'code' => 'university', 'name' => 'University', 'order' => 4,
                        'classes' => $this->classesMap([
                            ['1', 'Năm 1'], ['2', 'Năm 2'], ['3', 'Năm 3'], ['4', 'Năm 4'],
                        ]),
                        'groups' => [],
                    ],
                ],
            ],

            // ---------------------------------------------------------------- LA - Laos
            'LA' => [
                'country_name' => 'Laos',
                'system_code'  => 'la_national',
                'system_name'  => 'Laos Education System',
                'levels' => [
                    [
                        'code' => 'primary', 'name' => 'Primary', 'order' => 1,
                        'classes' => $this->classesMap([
                            ['p1', 'ປ.1'], ['p2', 'ປ.2'], ['p3', 'ປ.3'], ['p4', 'ປ.4'], ['p5', 'ປ.5'],
                        ]),
                        'groups' => [],
                    ],
                    [
                        'code' => 'lower_secondary', 'name' => 'Lower Secondary', 'order' => 2,
                        'classes' => $this->classesMap([
                            ['m1', 'ມ.1'], ['m2', 'ມ.2'], ['m3', 'ມ.3'], ['m4', 'ມ.4'],
                        ]),
                        'groups' => [],
                    ],
                    [
                        'code' => 'upper_secondary', 'name' => 'Upper Secondary', 'order' => 3,
                        'classes' => $this->classesMap([
                            ['m5', 'ມ.5'], ['m6', 'ມ.6'], ['m7', 'ມ.7'],
                        ]),
                        'groups' => [
                            ['general', 'General', 1],
                            ['technical', 'Technical', 2],
                            ['vocational', 'Vocational', 3],
                        ],
                    ],
                    [
                        'code' => 'university', 'name' => 'University', 'order' => 4,
                        'classes' => $this->classesMap([
                            ['1', 'ປີ 1'], ['2', 'ປີ 2'], ['3', 'ປີ 3'], ['4', 'ປີ 4'],
                        ]),
                        'groups' => [],
                    ],
                ],
            ],

            // ---------------------------------------------------------------- KH - Cambodia
            'KH' => [
                'country_name' => 'Cambodia',
                'system_code'  => 'kh_national',
                'system_name'  => 'Cambodia Education System',
                'levels' => [
                    [
                        'code' => 'primary', 'name' => 'Primary', 'order' => 1,
                        'classes' => $this->classesRange(1, 6, 'Grade %d'),
                        'groups'  => [],
                    ],
                    [
                        'code' => 'lower_secondary', 'name' => 'Lower Secondary', 'order' => 2,
                        'classes' => $this->classesRange(7, 9, 'Grade %d'),
                        'groups'  => [],
                    ],
                    [
                        'code' => 'upper_secondary', 'name' => 'Upper Secondary', 'order' => 3,
                        'classes' => $this->classesRange(10, 12, 'Grade %d'),
                        'groups'  => [
                            ['sciences', 'Sciences', 1],
                            ['social_sciences', 'Social Sciences', 2],
                            ['economics', 'Economics', 3],
                        ],
                    ],
                    [
                        'code' => 'university', 'name' => 'University', 'order' => 4,
                        'classes' => $this->classesRange(1, 4, 'Year %d'),
                        'groups'  => [],
                    ],
                ],
            ],

            // ---------------------------------------------------------------- MV - Maldives
            'MV' => [
                'country_name' => 'Maldives',
                'system_code'  => 'mv_national',
                'system_name'  => 'Maldives Education System',
                'levels' => [
                    [
                        'code' => 'primary', 'name' => 'Primary', 'order' => 1,
                        'classes' => $this->classesRange(1, 7, 'Grade %d'),
                        'groups'  => [],
                    ],
                    [
                        'code' => 'lower_secondary', 'name' => 'Lower Secondary', 'order' => 2,
                        'classes' => $this->classesRange(8, 10, 'Grade %d'),
                        'groups'  => [],
                    ],
                    [
                        'code' => 'higher_secondary', 'name' => 'Higher Secondary', 'order' => 3,
                        'classes' => $this->classesRange(11, 12, 'Grade %d'),
                        'groups'  => [
                            ['sciences', 'Sciences', 1],
                            ['humanities', 'Humanities', 2],
                            ['business', 'Business', 3],
                        ],
                    ],
                    [
                        'code' => 'university', 'name' => 'University', 'order' => 4,
                        'classes' => $this->classesRange(1, 4, 'Year %d'),
                        'groups'  => [],
                    ],
                ],
            ],
        ];
    }

    // ------------------------------------------------------------------ Helpers for catalog building

    /**
     * Build classes from a numeric range.
     *
     * @return array<int, array{code:string,name:string,sequence:int}>
     */
    private function classesRange(int $from, int $to, string $pattern): array
    {
        $out = [];
        foreach (range($from, $to) as $n) {
            $out[] = ['code' => (string) $n, 'name' => sprintf($pattern, $n), 'sequence' => $n];
        }

        return $out;
    }

    /**
     * Build classes from explicit [code, name] pairs.
     *
     * @param  array<int, array{0:string,1:string}>  $pairs
     * @return array<int, array{code:string,name:string,sequence:?int}>
     */
    private function classesMap(array $pairs): array
    {
        $out = [];
        foreach ($pairs as [$code, $name]) {
            $out[] = ['code' => $code, 'name' => $name, 'sequence' => is_numeric($code) ? (int) $code : null];
        }

        return $out;
    }

    // ------------------------------------------------------------------ DB helpers (match AcademicStructureSeeder)

    private function system(Country $country, string $code, string $name, int $order): EducationSystem
    {
        return EducationSystem::updateOrCreate(
            ['country_id' => $country->id, 'code' => $code],
            ['name' => $name, 'display_order' => $order, 'status' => true]
        );
    }

    private function level(Country $country, EducationSystem $system, string $code, string $name, int $order): AcademicLevel
    {
        return AcademicLevel::updateOrCreate(
            ['education_system_id' => $system->id, 'code' => $code],
            ['country_id' => $country->id, 'name' => $name, 'display_order' => $order, 'status' => true]
        );
    }

    private function classGrade(
        Country $country,
        EducationSystem $system,
        AcademicLevel $level,
        string $code,
        string $name,
        int $order,
        ?int $sequence = null
    ): ClassGrade {
        return ClassGrade::updateOrCreate(
            ['academic_level_id' => $level->id, 'code' => $code],
            [
                'country_id' => $country->id,
                'education_system_id' => $system->id,
                'name' => $name,
                'sequence' => $sequence ?? (is_numeric($code) ? (int) $code : null),
                'display_order' => $order,
                'status' => true,
            ]
        );
    }

    /**
     * @param  array<int, array{0:string,1:string,2:int}>  $groups  [code, name, order]
     */
    private function groupsForClass(Country $country, EducationSystem $system, AcademicLevel $level, ClassGrade $classGrade, array $groups): void
    {
        foreach ($groups as [$code, $name, $order]) {
            AcademicGroup::updateOrCreate(
                ['class_grade_id' => $classGrade->id, 'code' => $code],
                [
                    'country_id' => $country->id,
                    'education_system_id' => $system->id,
                    'academic_level_id' => $level->id,
                    'name' => $name,
                    'display_order' => $order,
                    'status' => true,
                ]
            );
        }
    }
}
