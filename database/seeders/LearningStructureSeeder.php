<?php

namespace Database\Seeders;

use App\Models\IndustryTemplateMapping;
use App\Models\StructureLabel;
use App\Models\StructureTemplate;
use App\Models\StructureTemplateLevel;
use Illuminate\Database\Seeder;

class LearningStructureSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDictionary();
        $this->seedTemplates();
        $this->seedMappings();
    }

    private function seedDictionary(): void
    {
        // Top-level labels (§2.1)
        $topLevel = [
            ['School', 'school'],
            ['College', 'college'],
            ['University', 'university'],
            ['Faculty', 'faculty'],
            ['Institute', 'institute'],
            ['Academy', 'academy'],
            ['Center', 'center'],
            ['Workshop', 'workshop'],
            ['Coaching Center', 'coaching_center'],
            ['Madrasa', 'madrasa'],
            ['Vocational Institute', 'vocational_institute'],
            ['Technical Institute', 'technical_institute'],
            ['Music Academy', 'music_academy'],
            ['Dance Academy', 'dance_academy'],
            ['Martial Arts Academy', 'martial_arts_academy'],
            ['Sports Academy', 'sports_academy'],
            ['Language Academy', 'language_academy'],
            ['Learning Center', 'learning_center'],
            ['Skill Academy', 'skill_academy'],
            ['Professional Training Center', 'professional_training_center'],
        ];

        // Level labels
        $levelLabels = [
            ['Class', 'class'],
            ['Grade', 'grade'],
            ['Year', 'year'],
            ['Semester', 'semester'],
            ['Term', 'term'],
            ['Section', 'section'],
            ['Division', 'division'],
            ['Course', 'course'],
            ['Program', 'program'],
            ['Module', 'module'],
            ['Session', 'session'],
            ['Batch', 'batch'],
            ['Workshop', 'workshop_level'],
            ['Discipline', 'discipline'],
            ['Level', 'level'],
            ['Belt', 'belt'],
            ['Stage', 'stage'],
            ['Rank', 'rank'],
            ['Instrument', 'instrument'],
            ['Dance Style', 'dance_style'],
            ['Vocal', 'vocal'],
            ['Genre', 'genre'],
            ['Sport', 'sport'],
            ['Age Group', 'age_group'],
            ['Team', 'team'],
            ['Squad', 'squad'],
            ['Department', 'department'],
            ['Group', 'group'],
            ['Stream', 'stream'],
            ['Category', 'category'],
            ['Phase', 'phase'],
            ['Trade', 'trade'],
            ['Language', 'language'],
            ['Subject', 'subject'],
            ['Faculty', 'faculty_level'],
        ];

        // Value templates (§12)
        $valueTemplates = [
            ['Grade Numbers', 'grade_numbers', ['1','2','3','4','5','6','7','8','9','10','11','12']],
            ['Sections', 'sections', ['Section A','Section B','Section C','Section D']],
            ['Year Numbers', 'year_numbers', ['1st Year','2nd Year','3rd Year','4th Year']],
            ['Belt Colors', 'belt_colors', ['White','Yellow','Orange','Green','Blue','Purple','Brown','Black']],
            ['Age Groups', 'age_groups', ['Under-8','Under-10','Under-12','Under-14','Under-16','Under-18','18+']],
            ['Batch Timings', 'batch_timings', ['Morning','Afternoon','Evening','Weekend']],
        ];

        foreach ($topLevel as [$name, $code]) {
            StructureLabel::updateOrCreate(
                ['category' => 'top_level', 'code' => $code],
                ['name' => $name, 'status' => true, 'metadata' => null]
            );
        }

        foreach ($levelLabels as [$name, $code]) {
            StructureLabel::updateOrCreate(
                ['category' => 'level_label', 'code' => $code],
                ['name' => $name, 'status' => true, 'metadata' => null]
            );
        }

        foreach ($valueTemplates as [$name, $code, $values]) {
            StructureLabel::updateOrCreate(
                ['category' => 'value_template', 'code' => $code],
                ['name' => $name, 'status' => true, 'metadata' => ['values' => $values]]
            );
        }
    }

    private function seedTemplates(): void
    {
        $templates = [
            [
                'name' => 'School',
                'code' => 'school',
                'description' => 'Class → Section',
                'levels' => [
                    ['label' => 'Class', 'label_key' => 'class', 'value_source' => 'grade_numbers'],
                    ['label' => 'Section', 'label_key' => 'section', 'value_source' => 'sections'],
                ],
            ],
            [
                'name' => 'College',
                'code' => 'college',
                'description' => 'Year → Group → Section',
                'levels' => [
                    ['label' => 'Year', 'label_key' => 'year', 'value_source' => 'year_numbers'],
                    ['label' => 'Group', 'label_key' => 'group', 'value_source' => null],
                    ['label' => 'Section', 'label_key' => 'section', 'value_source' => 'sections'],
                ],
            ],
            [
                'name' => 'University',
                'code' => 'university',
                'description' => 'Faculty → Department → Program → Semester',
                'levels' => [
                    ['label' => 'Faculty', 'label_key' => 'faculty_level', 'value_source' => null],
                    ['label' => 'Department', 'label_key' => 'department', 'value_source' => null],
                    ['label' => 'Program', 'label_key' => 'program', 'value_source' => null],
                    ['label' => 'Semester', 'label_key' => 'semester', 'value_source' => null],
                ],
            ],
            [
                'name' => 'Training Institute',
                'code' => 'training_institute',
                'description' => 'Course → Batch',
                'levels' => [
                    ['label' => 'Course', 'label_key' => 'course', 'value_source' => null],
                    ['label' => 'Batch', 'label_key' => 'batch', 'value_source' => 'batch_timings'],
                ],
            ],
            [
                'name' => 'Coaching Center',
                'code' => 'coaching_center',
                'description' => 'Subject → Batch',
                'levels' => [
                    ['label' => 'Subject', 'label_key' => 'subject', 'value_source' => null],
                    ['label' => 'Batch', 'label_key' => 'batch', 'value_source' => 'batch_timings'],
                ],
            ],
            [
                'name' => 'Madrasa',
                'code' => 'madrasa',
                'description' => 'Level → Class → Section',
                'levels' => [
                    ['label' => 'Level', 'label_key' => 'level', 'value_source' => null],
                    ['label' => 'Class', 'label_key' => 'class', 'value_source' => 'grade_numbers'],
                    ['label' => 'Section', 'label_key' => 'section', 'value_source' => 'sections'],
                ],
            ],
            [
                'name' => 'Vocational Institute',
                'code' => 'vocational_institute',
                'description' => 'Trade → Level → Batch',
                'levels' => [
                    ['label' => 'Trade', 'label_key' => 'trade', 'value_source' => null],
                    ['label' => 'Level', 'label_key' => 'level', 'value_source' => null],
                    ['label' => 'Batch', 'label_key' => 'batch', 'value_source' => 'batch_timings'],
                ],
            ],
            [
                'name' => 'Technical Institute',
                'code' => 'technical_institute',
                'description' => 'Program → Semester → Batch',
                'levels' => [
                    ['label' => 'Program', 'label_key' => 'program', 'value_source' => null],
                    ['label' => 'Semester', 'label_key' => 'semester', 'value_source' => null],
                    ['label' => 'Batch', 'label_key' => 'batch', 'value_source' => 'batch_timings'],
                ],
            ],
            [
                'name' => 'Martial Arts — Style Based',
                'code' => 'martial_arts_style',
                'description' => 'Discipline → Level → Batch',
                'levels' => [
                    ['label' => 'Discipline', 'label_key' => 'discipline', 'value_source' => null],
                    ['label' => 'Level', 'label_key' => 'level', 'value_source' => null],
                    ['label' => 'Batch', 'label_key' => 'batch', 'value_source' => 'batch_timings'],
                ],
            ],
            [
                'name' => 'Martial Arts — Belt Based',
                'code' => 'martial_arts_belt',
                'description' => 'Discipline → Belt → Batch',
                'levels' => [
                    ['label' => 'Discipline', 'label_key' => 'discipline', 'value_source' => null],
                    ['label' => 'Belt', 'label_key' => 'belt', 'value_source' => 'belt_colors'],
                    ['label' => 'Batch', 'label_key' => 'batch', 'value_source' => 'batch_timings'],
                ],
            ],
            [
                'name' => 'Dance Academy',
                'code' => 'dance_academy',
                'description' => 'Dance Style → Grade → Batch',
                'levels' => [
                    ['label' => 'Dance Style', 'label_key' => 'dance_style', 'value_source' => null],
                    ['label' => 'Grade', 'label_key' => 'grade', 'value_source' => null],
                    ['label' => 'Batch', 'label_key' => 'batch', 'value_source' => 'batch_timings'],
                ],
            ],
            [
                'name' => 'Music Academy',
                'code' => 'music_academy',
                'description' => 'Instrument → Level → Batch',
                'levels' => [
                    ['label' => 'Instrument', 'label_key' => 'instrument', 'value_source' => null],
                    ['label' => 'Level', 'label_key' => 'level', 'value_source' => null],
                    ['label' => 'Batch', 'label_key' => 'batch', 'value_source' => 'batch_timings'],
                ],
            ],
            [
                'name' => 'Sports Academy',
                'code' => 'sports_academy',
                'description' => 'Sport → Age Group → Team',
                'levels' => [
                    ['label' => 'Sport', 'label_key' => 'sport', 'value_source' => null],
                    ['label' => 'Age Group', 'label_key' => 'age_group', 'value_source' => 'age_groups'],
                    ['label' => 'Team', 'label_key' => 'team', 'value_source' => null],
                ],
            ],
            [
                'name' => 'Language Academy',
                'code' => 'language_academy',
                'description' => 'Language → Level → Batch',
                'levels' => [
                    ['label' => 'Language', 'label_key' => 'language', 'value_source' => null],
                    ['label' => 'Level', 'label_key' => 'level', 'value_source' => null],
                    ['label' => 'Batch', 'label_key' => 'batch', 'value_source' => 'batch_timings'],
                ],
            ],
        ];

        foreach ($templates as $tpl) {
            $template = StructureTemplate::updateOrCreate(
                ['code' => $tpl['code'], 'is_global' => true],
                [
                    'name' => $tpl['name'],
                    'description' => $tpl['description'],
                    'institute_id' => null,
                    'status' => true,
                    'metadata' => null,
                ]
            );

            foreach ($tpl['levels'] as $idx => $lvl) {
                StructureTemplateLevel::updateOrCreate(
                    ['template_id' => $template->id, 'level_order' => $idx + 1],
                    [
                        'label' => $lvl['label'],
                        'label_key' => $lvl['label_key'],
                        'required' => true,
                        'has_values' => true,
                        'value_source' => $lvl['value_source'],
                        'metadata' => null,
                    ]
                );
            }

            // Remove stale levels if template reduced in size (idempotent rerun)
            $expected = count($tpl['levels']);
            StructureTemplateLevel::where('template_id', $template->id)
                ->where('level_order', '>', $expected)
                ->delete();
        }
    }

    private function seedMappings(): void
    {
        // Canonical B2 taxonomy: Education = Academic, Training Center = Professional
        // Legacy aliases preserved as NEEDS_REVIEW rows (not removed) for audit trail
        $map = [
            // Education — Academic Institutions (canonical 4 + variants)
            ['industry' => 'education', 'sub_industry' => 'school', 'code' => 'school'],
            ['industry' => 'education', 'sub_industry' => 'college', 'code' => 'college'],
            ['industry' => 'education', 'sub_industry' => 'polytechnic', 'code' => 'technical_institute'],
            ['industry' => 'education', 'sub_industry' => 'university', 'code' => 'university'],
            ['industry' => 'education', 'sub_industry' => 'madrasha', 'code' => 'madrasa'],
            // Legacy academic variants — preserved, mapped to school (NEEDS_REVIEW)
            ['industry' => 'education', 'sub_industry' => 'primary_school', 'code' => 'school'],
            ['industry' => 'education', 'sub_industry' => 'secondary_high_school', 'code' => 'school'],
            ['industry' => 'education', 'sub_industry' => 'school_college', 'code' => 'school'],
            // Training Center — Professional (canonical 5)
            ['industry' => 'training_center', 'sub_industry' => 'training_institute', 'code' => 'training_institute'],
            ['industry' => 'training_center', 'sub_industry' => 'professional_training_center', 'code' => 'training_institute'],
            ['industry' => 'training_center', 'sub_industry' => 'dance_academy', 'code' => 'dance_academy'],
            ['industry' => 'training_center', 'sub_industry' => 'it_training_center', 'code' => 'training_institute'],
            ['industry' => 'training_center', 'sub_industry' => 'vocational_training_center', 'code' => 'vocational_institute'],
            // Legacy professional aliases — preserved under training_center for review trail
            ['industry' => 'training_center', 'sub_industry' => 'institution', 'code' => 'training_institute'],
            ['industry' => 'training_center', 'sub_industry' => 'professional_training_academy', 'code' => 'training_institute'],
            ['industry' => 'training_center', 'sub_industry' => 'computer_it_training_institute', 'code' => 'training_institute'],
            ['industry' => 'training_center', 'sub_industry' => 'vocational_institute', 'code' => 'vocational_institute'],
            ['industry' => 'training_center', 'sub_industry' => 'technical_training_center', 'code' => 'technical_institute'],
            ['industry' => 'training_center', 'sub_industry' => 'skill_development_center', 'code' => 'vocational_institute'],
            ['industry' => 'training_center', 'sub_industry' => 'martial_arts', 'code' => 'martial_arts_belt'],
            ['industry' => 'training_center', 'sub_industry' => 'music_academy', 'code' => 'music_academy'],
            ['industry' => 'training_center', 'sub_industry' => 'sports_academy', 'code' => 'sports_academy'],
            ['industry' => 'training_center', 'sub_industry' => 'language_academy', 'code' => 'language_academy'],
            ['industry' => 'training_center', 'sub_industry' => 'coaching_centre', 'code' => 'coaching_center'],
            // Fallbacks
            ['industry' => 'education', 'sub_industry' => null, 'code' => 'school'],
            ['industry' => 'training_center', 'sub_industry' => null, 'code' => 'training_institute'],
        ];

        foreach ($map as $row) {
            $template = StructureTemplate::where('code', $row['code'])->where('is_global', true)->first();
            if (! $template) {
                continue;
            }

            IndustryTemplateMapping::updateOrCreate(
                [
                    'industry' => $row['industry'],
                    'sub_industry' => $row['sub_industry'],
                    'country_id' => null,
                ],
                [
                    'structure_template_id' => $template->id,
                    'priority' => $row['sub_industry'] === null ? 999 : 100,
                    'status' => true,
                    'metadata' => null,
                ]
            );
        }
    }
}
