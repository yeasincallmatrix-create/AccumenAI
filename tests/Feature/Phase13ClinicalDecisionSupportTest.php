<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\CdsFinding;
use App\Models\Medical\CdsRule;
use App\Models\Medical\CdsRuleVersion;
use App\Models\Medical\Doctor;
use App\Models\Medical\Medicine;
use App\Models\Medical\MedicineIdentifier;
use App\Models\Medical\MedicineIngredient;
use App\Models\Medical\Patient;
use App\Models\Medical\PatientAllergy;
use App\Models\Medical\Prescription;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Services\Medical\CdsEngine;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase 13 — Validated clinical decision support.
 *
 * Active rules here are structural (same-ingredient duplication, stated
 * allergy→ingredient conflicts) plus explicitly synthetic fixtures for the
 * generic interpreters (source 'synthetic-test-fixture' — never presented
 * as authoritative clinical knowledge). No external interaction,
 * contraindication or dosing knowledge is claimed anywhere.
 */
class Phase13ClinicalDecisionSupportTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;

    private User $owner;

    private User $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->institute = Institute::create([
            'name' => 'CDS Test Hospital',
            'slug' => 'cds-test-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $this->owner = User::factory()->create([
            'account_type' => 'owner',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        Membership::create([
            'user_id' => $this->owner->id,
            'institution_id' => $this->institute->id,
            'role_id' => Role::where('slug', 'institute-owner')->value('id'),
            'status' => 'active',
        ]);

        $this->doctor = User::factory()->create([
            'account_type' => 'staff',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        Doctor::create([
            'institute_id' => $this->institute->id,
            'user_id' => $this->doctor->id,
            'registration_number' => 'REG-'.strtoupper(uniqid()),
        ]);

        $this->actingAs($this->owner, 'web');
        Workspace::set($this->institute->id);
    }

    private function makeRule(
        string $key,
        string $type,
        string $severity,
        string $policy,
        array $definition,
        string $status = 'active',
        string $source = 'synthetic-test-fixture'
    ): CdsRule {
        $rule = CdsRule::create([
            'rule_key' => $key,
            'rule_type' => $type,
            'severity' => $severity,
            'block_policy' => $policy,
            'status' => in_array($status, ['draft', 'suspended', 'retired']) ? $status : CdsRule::STATUS_ACTIVE,
            'source' => $source,
            'source_version' => 't1',
            'validated_by' => 'test',
            'validated_at' => now(),
            'current_version' => 1,
        ]);
        CdsRuleVersion::create([
            'cds_rule_id' => $rule->id,
            'version' => 1,
            'definition' => $definition,
            'status' => in_array($status, ['draft', 'suspended', 'retired']) ? $status : CdsRule::STATUS_ACTIVE,
            'validated_by' => 'test',
            'validated_at' => now(),
        ]);

        return $rule;
    }

    private function seedStructuralRules(): void
    {
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CdsRuleSeeder'])->assertSuccessful();
    }

    private function makeMedicine(array $overrides = []): Medicine
    {
        return Medicine::create(array_merge([
            'institute_id' => $this->institute->id,
            'code' => 'T-'.strtoupper(uniqid()),
            'generic_name' => 'Testmycin',
            'brand_name' => 'Testmycin 500',
            'dosage_form' => 'Tablet',
            'strength' => '500mg',
            'unit' => 'Strip',
            'pack_size' => 10,
            'purchase_price' => 5,
            'selling_price' => 8,
            'reorder_level' => 10,
            'reorder_quantity' => 50,
            'is_active' => true,
        ], $overrides));
    }

    private function createPatient(array $overrides = []): Patient
    {
        $this->post(route('medical.patients.store'), array_merge([
            'first_name' => 'CDS',
            'last_name' => 'Probe',
            'date_of_birth' => '1990-05-15',
            'gender' => 'male',
            'phone' => '01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'blood_group' => 'O+',
        ], $overrides))->assertSessionHasNoErrors();

        return Patient::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    private function prescribe(Patient $patient, array $items)
    {
        return $this->post(route('medical.prescriptions.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'prescription_date' => now()->format('Y-m-d'),
            'diagnosis' => 'CDS diagnosis',
            'items' => $items,
        ]);
    }

    private function itemFor(Medicine $medicine, array $overrides = []): array
    {
        return array_merge([
            'medicine_id' => $medicine->id,
            'medicine_name' => $medicine->display_name,
            'dosage' => '500mg',
            'frequency' => '1+0+1',
            'quantity' => 10,
        ], $overrides);
    }

    // --- Rule lifecycle ---------------------------------------------------------------

    public function test_non_active_rules_never_execute(): void
    {
        foreach (['draft', 'suspended', 'retired'] as $status) {
            $this->makeRule('CDS-LC-'.$status, CdsRule::TYPE_DUPLICATE_THERAPY, 'HIGH', 'block', ['mode' => 'shared_ingredient'], $status);
        }

        $engine = app(CdsEngine::class);
        $patient = $this->createPatient();
        $result = $engine->evaluate($patient, [], $this->institute->id);

        $this->assertSame([], $result->blocking);
        $this->assertSame([], $result->warnings);
    }

    public function test_seeder_provides_two_validated_structural_rules(): void
    {
        $this->seedStructuralRules();

        $dup = CdsRule::where('rule_key', 'CDS-DUP-INGREDIENT-001')->firstOrFail();
        $alg = CdsRule::where('rule_key', 'CDS-ALG-INGREDIENT-001')->firstOrFail();
        $this->assertSame('active', $dup->status);
        $this->assertSame('active', $alg->status);
        $this->assertSame(1, $dup->versions()->where('status', 'active')->count());
        $this->assertStringContainsString('governance', (string) $dup->source);
    }

    // --- Versioning -------------------------------------------------------------------------

    public function test_findings_pin_version_and_v2_creates_new_rows(): void
    {
        $rule = $this->makeRule('CDS-V-1', CdsRule::TYPE_INTERACTION, 'MODERATE', 'warn', ['pairs' => []]);
        $v1 = $rule->versions()->firstOrFail();

        $finding = CdsFinding::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->createPatient()->id,
            'cds_rule_version_id' => $v1->id,
            'severity' => 'MODERATE',
            'status' => 'open',
            'message' => 'v1 finding',
            'evaluated_at' => now(),
        ]);

        // Material change → new version row; v1 finding keeps pointing at v1.
        $v2 = CdsRuleVersion::create([
            'cds_rule_id' => $rule->id, 'version' => 2,
            'definition' => ['pairs' => []], 'status' => 'active',
        ]);
        $rule->update(['current_version' => 2]);

        $this->assertSame($v1->id, (int) $finding->fresh()->cds_rule_version_id);
        $this->assertSame(2, $v2->version);
        $this->assertSame('open', $finding->fresh()->status);
    }

    // --- Allergy -----------------------------------------------------------------------------------

    public function test_exact_ingredient_allergy_blocks_save(): void
    {
        $this->seedStructuralRules();
        // Ingredient-level isolation: the allergy term matches NO generic,
        // brand or category string, so the legacy string check passes while
        // the terminology-normalized CDS check blocks.
        $medicine = $this->makeMedicine(['generic_name' => 'Ace Plus', 'brand_name' => 'Ace Plus 500']);
        $this->artisan('medical:backfill-medicine-terminology')->assertSuccessful();
        $para = MedicineIngredient::create(['canonical_name' => 'Paracetamol', 'normalized_name' => 'paracetamol']);
        $product = $medicine->fresh()->product;
        $product->productIngredients()->create([
            'medicine_ingredient_id' => $para->id, 'sequence' => 1,
            'strength_value' => 500, 'strength_unit' => 'mg',
        ]);

        $patient = $this->createPatient(['allergies' => 'Paracetamol']);
        $this->prescribe($patient, [$this->itemFor($medicine)])->assertSessionHas('error');

        $this->assertSame(0, Prescription::where('institute_id', $this->institute->id)->count());
        $this->assertSame(0, CdsFinding::where('institute_id', $this->institute->id)->count());
    }

    public function test_combination_component_allergy_blocks_save(): void
    {
        $this->seedStructuralRules();
        $medicine = $this->makeMedicine([
            'generic_name' => 'Calcium + Vitamin D3',
            'strength' => '500mg+400IU',
        ]);
        $this->artisan('medical:backfill-medicine-terminology')->assertSuccessful();

        $patient = $this->createPatient(['allergies' => 'Vitamin D3']);
        $this->prescribe($patient, [$this->itemFor($medicine)])->assertSessionHas('error');
        $this->assertSame(0, Prescription::where('institute_id', $this->institute->id)->count());
    }

    public function test_category_only_similarity_is_uncertainty_not_conflict(): void
    {
        $this->seedStructuralRules();
        $medicine = $this->makeMedicine(['category' => 'RareCategoryX']);
        $this->artisan('medical:backfill-medicine-terminology')->assertSuccessful();
        $patient = $this->createPatient(['allergies' => 'RareCategoryX']);

        // Direct engine evaluation (bypasses the legacy string block, which
        // would independently refuse this same-category pair): terminology
        // proves no ingredient identity, so only an uncertainty INFO finding
        // is produced — non-blocking by policy override.
        $engine = app(\App\Services\Medical\CdsEngine::class);
        $result = $engine->evaluate($patient, [[
            'medicine_id' => $medicine->id,
            'medicine_name' => $medicine->display_name,
        ]], $this->institute->id);

        $this->assertFalse($result->hasBlockingIssues());
        $this->assertCount(1, $result->warnings);
        // Severity stays CRITICAL (allergy rule) but the explicit warn
        // override keeps it non-blocking: information, not a directive.
        $this->assertSame('CRITICAL', $result->warnings[0]['severity']);
        $this->assertTrue((bool) ($result->warnings[0]['trigger_data']['uncertain'] ?? false));
        $this->assertSame('warn', $result->warnings[0]['block_policy']);
    }

    // --- Interaction (synthetic interpreter proof) ------------------------------------------------------

    public function test_synthetic_pair_triggers_and_unrelated_does_not(): void
    {
        $medA = $this->makeMedicine(['generic_name' => 'AlphaX', 'code' => 'T-'.strtoupper(uniqid())]);
        $medB = $this->makeMedicine(['generic_name' => 'BetaY', 'code' => 'T-'.strtoupper(uniqid())]);
        $medC = $this->makeMedicine(['generic_name' => 'GammaZ', 'code' => 'T-'.strtoupper(uniqid())]);
        $this->artisan('medical:backfill-medicine-terminology')->assertSuccessful();

        $ingA = $medA->fresh()->product->ingredients()->firstOrFail()->id;
        $ingB = $medB->fresh()->product->ingredients()->firstOrFail()->id;
        $this->makeRule('CDS-SYN-PAIR-1', CdsRule::TYPE_INTERACTION, 'MODERATE', 'warn', [
            'pairs' => [[(int) $ingA, (int) $ingB]],
        ]);

        $patient = $this->createPatient();
        $this->prescribe($patient, [$this->itemFor($medA), $this->itemFor($medB)])->assertSessionHasNoErrors();

        $rx = Prescription::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
        $findings = CdsFinding::where('prescription_id', $rx->id)->get();
        $this->assertCount(1, $findings);
        $this->assertSame('MODERATE', $findings->first()->severity);
        $this->assertStringContainsString('synthetic-test-fixture', (string) $findings->first()->ruleVersion->rule->source);

        // Unrelated pair: no findings at all.
        $patient2 = $this->createPatient();
        $this->prescribe($patient2, [$this->itemFor($medA), $this->itemFor($medC)])->assertSessionHasNoErrors();
        $rx2 = Prescription::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
        $this->assertSame(0, CdsFinding::where('prescription_id', $rx2->id)->count());
    }

    // --- Duplicate therapy ------------------------------------------------------------------------------

    public function test_exact_duplicate_blocked(): void
    {
        $this->seedStructuralRules();
        $medicine = $this->makeMedicine();
        $this->artisan('medical:backfill-medicine-terminology')->assertSuccessful();

        $patient = $this->createPatient();
        $this->prescribe($patient, [$this->itemFor($medicine), $this->itemFor($medicine)])
            ->assertSessionHas('error');
        $this->assertSame(0, Prescription::where('institute_id', $this->institute->id)->count());
    }

    public function test_cross_brand_same_ingredient_blocked_beyond_string_match(): void
    {
        $this->seedStructuralRules();
        // Same ingredient, textually different generics: the legacy string
        // check sees different names; terminology sees one ingredient.
        $medA = $this->makeMedicine(['generic_name' => 'Paracetamol', 'brand_name' => 'BrandA 500']);
        $medB = $this->makeMedicine([
            'code' => 'T-'.strtoupper(uniqid()),
            'generic_name' => 'Acetaminophen',
            'brand_name' => 'BrandB 500',
        ]);
        $this->artisan('medical:backfill-medicine-terminology')->assertSuccessful();

        // Force a shared ingredient identity (two spellings, one substance).
        $ingredient = MedicineIngredient::where('normalized_name', 'paracetamol')->firstOrFail();
        $productB = $medB->fresh()->product;
        $productB->productIngredients()->delete();
        $productB->productIngredients()->create([
            'medicine_ingredient_id' => $ingredient->id, 'sequence' => 0,
            'strength_value' => 500, 'strength_unit' => 'mg',
        ]);

        $patient = $this->createPatient();
        $this->prescribe($patient, [$this->itemFor($medA), $this->itemFor($medB)])
            ->assertSessionHas('error');
    }

    public function test_shared_category_alone_never_blocks(): void
    {
        $this->seedStructuralRules();
        $medA = $this->makeMedicine(['generic_name' => 'CatA', 'category' => 'SharedCat']);
        $medB = $this->makeMedicine([
            'code' => 'T-'.strtoupper(uniqid()),
            'generic_name' => 'CatB', 'category' => 'SharedCat',
        ]);
        $this->artisan('medical:backfill-medicine-terminology')->assertSuccessful();

        $patient = $this->createPatient();
        $this->prescribe($patient, [$this->itemFor($medA), $this->itemFor($medB)])
            ->assertSessionHasNoErrors();
        $rx = Prescription::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
        $this->assertSame(0, CdsFinding::where('prescription_id', $rx->id)->count());
    }

    // --- Findings, override, audit -----------------------------------------------------------------------------

    public function test_finding_fields_and_idempotent_refesh(): void
    {
        $this->makeRule('CDS-SYN-DUP-1', CdsRule::TYPE_INTERACTION, 'LOW', 'warn', ['pairs' => []]);
        $medA = $this->makeMedicine(['generic_name' => 'FindA']);
        $medB = $this->makeMedicine(['generic_name' => 'FindB']);
        $this->artisan('medical:backfill-medicine-terminology')->assertSuccessful();

        $ingA = $medA->fresh()->product->ingredients()->firstOrFail()->id;
        $ingB = $medB->fresh()->product->ingredients()->firstOrFail()->id;
        $rule = CdsRule::where('rule_key', 'CDS-SYN-DUP-1')->firstOrFail();
        $rule->versions()->firstOrFail()->update(['definition' => ['pairs' => [[(int) $ingA, (int) $ingB]]]]);

        $patient = $this->createPatient();
        $this->prescribe($patient, [$this->itemFor($medA), $this->itemFor($medB)])->assertSessionHasNoErrors();
        $rx = Prescription::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();

        $finding = CdsFinding::where('prescription_id', $rx->id)->firstOrFail();
        $this->assertSame('LOW', $finding->severity);
        $this->assertSame('open', $finding->status);
        $this->assertNotEmpty($finding->message);
        $this->assertIsArray($finding->explanation);
        $this->assertArrayHasKey('evidence', $finding->explanation);
        $this->assertNotNull($finding->evaluated_at);
        $this->assertSame($this->institute->id, (int) $finding->institute_id);
        $this->assertSame($patient->id, (int) $finding->patient_id);

        // Same context re-evaluated (draft update, unchanged items): still
        // exactly one open finding for the version — never duplicated.
        $this->put(route('medical.prescriptions.update', $rx), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'prescription_date' => now()->format('Y-m-d'),
            'items' => [$this->itemFor($medA), $this->itemFor($medB)],
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, CdsFinding::where('prescription_id', $rx->id)
            ->where('status', 'open')->count());
    }

    public function test_override_requires_reason_and_audits(): void
    {
        // LOW/warn pair from the start so a persisted finding exists to act on.
        $rule = $this->makeRule('CDS-SYN-OVR-1', CdsRule::TYPE_INTERACTION, 'LOW', 'warn', ['pairs' => []]);
        $medA = $this->makeMedicine(['generic_name' => 'OvrA']);
        $medB = $this->makeMedicine(['generic_name' => 'OvrB']);
        $this->artisan('medical:backfill-medicine-terminology')->assertSuccessful();
        $ingA = $medA->fresh()->product->ingredients()->firstOrFail()->id;
        $ingB = $medB->fresh()->product->ingredients()->firstOrFail()->id;
        CdsRule::where('rule_key', 'CDS-SYN-OVR-1')->firstOrFail()
            ->versions()->firstOrFail()->update(['definition' => ['pairs' => [[(int) $ingA, (int) $ingB]]]]);

        $patient = $this->createPatient();
        $this->prescribe($patient, [$this->itemFor($medA), $this->itemFor($medB)])->assertSessionHasNoErrors();
        $rx = Prescription::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
        $finding = CdsFinding::where('prescription_id', $rx->id)->firstOrFail();

        // Override without reason: refused, state untouched.
        $this->post(route('medical.prescriptions.findings.resolve', [$rx, $finding]), [
            'action' => 'override',
        ])->assertSessionHasErrors(['reason']);
        $this->assertSame('open', $finding->fresh()->status);

        // Override with reason: applied + audited with actor + reason.
        $this->post(route('medical.prescriptions.findings.resolve', [$rx, $finding]), [
            'action' => 'override', 'reason' => 'Benefit outweighs risk per consultant.',
        ])->assertRedirect();
        $finding->refresh();
        $this->assertSame('overridden', $finding->status);
        $this->assertSame($this->owner->id, (int) $finding->resolved_by);
        $this->assertSame('Benefit outweighs risk per consultant.', $finding->resolution_reason);
        $this->assertDatabaseHas('clinical_audit_logs', [
            'auditable_type' => \App\Models\Medical\CdsFinding::class,
            'auditable_id' => $finding->id,
            'action' => 'finding_override',
        ]);

        // Acknowledge needs no reason.
        $finding2 = CdsFinding::create([
            'institute_id' => $this->institute->id, 'patient_id' => $patient->id,
            'prescription_id' => $rx->id,
            'cds_rule_version_id' => $rule->versions()->firstOrFail()->id,
            'severity' => 'LOW', 'status' => 'open', 'message' => 'ack me',
            'evaluated_at' => now(),
        ]);
        $this->post(route('medical.prescriptions.findings.resolve', [$rx, $finding2]), [
            'action' => 'acknowledge',
        ])->assertRedirect();
        $this->assertSame('acknowledged', $finding2->fresh()->status);
    }

    public function test_resolve_requires_edit_permission(): void
    {
        $rule = $this->makeRule('CDS-SYN-PERM-1', CdsRule::TYPE_INTERACTION, 'LOW', 'warn', ['pairs' => []]);
        $rx = Prescription::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->createPatient()->id,
            'doctor_id' => $this->doctor->id,
            'prescription_number' => 'RX-PERM-1',
            'prescription_date' => now()->format('Y-m-d'),
        ]);
        $finding = CdsFinding::create([
            'institute_id' => $this->institute->id, 'patient_id' => $rx->patient_id,
            'prescription_id' => $rx->id,
            'cds_rule_version_id' => $rule->versions()->firstOrFail()->id,
            'severity' => 'LOW', 'status' => 'open', 'message' => 'perm probe',
            'evaluated_at' => now(),
        ]);

        $staff = User::factory()->create(['account_type' => 'staff', 'status' => 'active']);
        $this->actingAs($staff, 'web');
        // No membership at all: denied before reaching the finding.
        $this->post(route('medical.prescriptions.findings.resolve', [$rx, $finding]), [
            'action' => 'acknowledge',
        ])->assertForbidden();
        $this->assertSame('open', $finding->fresh()->status);
    }

    // --- Tenant isolation -----------------------------------------------------------------------------------------------

    public function test_cross_tenant_evaluation_and_findings_denied(): void
    {
        $mine = $this->createPatient();

        $engine = app(\App\Services\Medical\CdsEngine::class);
        $foreign = Institute::create([
            'name' => 'CDS Rival', 'slug' => 'cds-rival-'.uniqid(),
            'industry' => 'healthcare', 'sub_industry' => 'clinic',
            'country' => 'Bangladesh', 'status' => 'active',
        ]);
        $foreignPatient = Patient::create([
            'institute_id' => $foreign->id, 'mr_number' => 'MR-CDS-1',
            'first_name' => 'Foreign', 'last_name' => 'Patient',
            'date_of_birth' => '1990-01-01', 'gender' => 'male', 'phone' => '0100000091',
        ]);

        // Engine refuses cross-tenant evaluation outright.
        try {
            $engine->evaluate($foreignPatient, [], $this->institute->id);
            $this->fail('Cross-tenant evaluation must throw.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('tenant boundary', $e->getMessage());
        }

        // Findings of another institute are unreachable through its routes.
        $rival = User::factory()->create(['account_type' => 'owner', 'status' => 'active']);
        Membership::create([
            'user_id' => $rival->id, 'institution_id' => $foreign->id,
            'role_id' => Role::where('slug', 'institute-owner')->value('id'), 'status' => 'active',
        ]);
        $this->actingAs($rival, 'web');
        Workspace::set($foreign->id);

        $rx = Prescription::create([
            'institute_id' => $this->institute->id, 'patient_id' => $mine->id,
            'doctor_id' => $this->doctor->id, 'prescription_number' => 'RX-XT-1',
            'prescription_date' => now()->format('Y-m-d'),
        ]);
        $finding = CdsFinding::create([
            'institute_id' => $this->institute->id, 'patient_id' => $mine->id,
            'prescription_id' => $rx->id,
            'cds_rule_version_id' => $this->makeRule(
                'CDS-SYN-XT-1', CdsRule::TYPE_INTERACTION, 'LOW', 'warn', ['pairs' => []]
            )->versions()->firstOrFail()->id,
            'severity' => 'LOW', 'status' => 'open', 'message' => 'x',
            'evaluated_at' => now(),
        ]);
        $this->post(route('medical.prescriptions.findings.resolve', [$rx, $finding]), [
            'action' => 'acknowledge',
        ])->assertForbidden();
    }

    // --- Terminology failure & history safety ------------------------------------------------------------------------------------

    public function test_free_text_items_unevaluated_without_fabrication(): void
    {
        $this->seedStructuralRules();
        $patient = $this->createPatient();
        $this->prescribe($patient, [[
            'medicine_id' => null,
            'medicine_name' => 'Mystery Syrup',
            'dosage' => '5ml',
            'frequency' => '1+0+1',
            'quantity' => 1,
        ]])->assertSessionHasNoErrors();

        $rx = Prescription::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
        $this->assertSame(0, CdsFinding::where('prescription_id', $rx->id)->count());
        // Nothing invented: no ingredients, identifiers or mappings created.
        $this->assertSame(0, MedicineIdentifier::count());
    }

    public function test_snapshots_untouched_by_cds(): void
    {
        $medicine = $this->makeMedicine();
        $this->artisan('medical:backfill-medicine-terminology')->assertSuccessful();
        $rx = $this->createPrescription($medicine);
        $item = $rx->items()->firstOrFail();
        $before = $item->only([
            'medicine_name', 'dgda_code', 'display_name_snapshot', 'strength_snapshot',
            'dosage_form_snapshot', 'rxnorm_code_snapshot',
        ]);

        // Direct engine evaluation is pure: zero writes by construction.
        $engine = app(\App\Services\Medical\CdsEngine::class);
        $engine->evaluate($rx->patient, [[
            'medicine_id' => $medicine->id, 'medicine_name' => $medicine->display_name,
        ]], $this->institute->id);

        $this->assertSame($before, $item->fresh()->only(array_keys($before)));
    }

    private function createPrescription(Medicine $medicine): Prescription
    {
        return $this->createPrescriptionFor($this->createPatient(), $medicine);
    }

    private function createPrescriptionFor(Patient $patient, Medicine $medicine): Prescription
    {
        $this->post(route('medical.prescriptions.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'prescription_date' => now()->format('Y-m-d'),
            'items' => [$this->itemFor($medicine)],
        ])->assertSessionHasNoErrors();

        return Prescription::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    // --- Authorization: no global rule administration surface ------------------------------------------------------------------------

    public function test_no_rule_admin_routes(): void
    {
        foreach (['cds.rules', 'cds.findings', 'cds.evaluations'] as $name) {
            $this->assertFalse(Route::has('medical.'.$name.'.store'));
            $this->assertFalse(Route::has('medical.'.$name.'.destroy'));
        }
    }
}
