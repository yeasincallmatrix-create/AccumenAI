<?php

namespace App\Services;

use App\Models\CrmContact;
use App\Models\CrmContactType;
use App\Models\CrmLead;
use App\Models\CrmLeadSource;
use App\Models\CrmLeadStatus;
use App\Models\Student;

/**
 * Step 34 — Education ↔ AccumenAI Core (CRM) integration.
 *
 * Bridges the existing Education module's Student model to the existing CRM
 * Core without duplicating source-of-truth data: the student stays the source
 * of truth for the person, and the CRM lead/contact are linked projections
 * used by CRM workflows.
 *
 * - Admission captures a CRM lead (the prospect pipeline entry).
 * - Enrollment converts that admission lead into a CRM contact (marks it won)
 *   and links the student to the converted contact.
 *
 * Every method is idempotent and safe to call repeatedly. All mutations are
 * delegated to the existing CrmLeadService / CrmContactService so audit trails
 * and duplicate protection behave exactly like the rest of CRM Core.
 */
class EducationCrmIntegrationService
{
    public function __construct(
        private readonly CrmContactService $contacts,
        private readonly CrmLeadService $leads,
    ) {}

    /**
     * Ensure the student is linked to a CRM contact. Creates the contact when
     * no link exists yet (idempotent).
     */
    public function ensureStudentCrmLink(Student $student, ?int $branchId, int $actorId): ?CrmContact
    {
        if ($student->crm_contact_id !== null) {
            return $this->resolveContact($student->institute_id, (int) $student->crm_contact_id);
        }

        $phone = $student->phone ?: $student->guardian_phone;
        $guardianPhone = $student->guardian_phone;

        $data = [
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'email' => $student->email,
            'phone' => $phone,
            'phone_alt' => $guardianPhone !== null && $guardianPhone !== $phone ? $guardianPhone : null,
            'contact_type_id' => $this->catalogId(CrmContactType::class, 'customer'),
            'source_id' => $this->catalogId(CrmLeadSource::class, 'other'),
            'is_customer' => true,
            'status' => CrmContact::STATUS_ACTIVE,
            'notes' => 'Linked to student #'.$student->id.($student->student_id_number ? ' ('.$student->student_id_number.')' : ''),
        ];

        $contact = $this->contacts->create(
            array_filter($data, fn ($value) => $value !== null),
            $student->institute_id,
            $branchId,
            $actorId,
        );

        $this->linkContact($student, (int) $contact->id);

        return $contact;
    }

    /**
     * Capture the student admission as a CRM lead (prospect pipeline entry).
     * Idempotent: returns the existing lead when the student is already linked.
     */
    public function captureAdmissionLead(Student $student, ?int $branchId, int $actorId): ?CrmLead
    {
        if ($student->crm_lead_id !== null) {
            return $this->resolveLead($student->institute_id, (int) $student->crm_lead_id);
        }

        $lead = $this->leads->create(
            [
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'email' => $student->email,
                'phone' => $student->phone ?: $student->guardian_phone,
                'contact_id' => $student->crm_contact_id,
                'interest_summary' => 'Admission'.($student->student_id_number ? ' — '.$student->student_id_number : ''),
            ],
            $student->institute_id,
            $branchId,
            $actorId,
        );

        $student->forceFill(['crm_lead_id' => $lead->id])->save();

        return $lead;
    }

    /**
     * Convert the student's admission lead into a CRM contact (marks the lead
     * won) and link the student to the converted contact. Idempotent: an
     * already-converted lead returns its existing converted contact, and a
     * student that already has a contact is linked to that contact instead of
     * creating a duplicate.
     */
    public function convertAdmissionLead(Student $student, int $instituteId, int $actorId): ?CrmContact
    {
        $lead = $student->crm_lead_id !== null
            ? $this->resolveLead($instituteId, (int) $student->crm_lead_id)
            : null;

        if ($lead === null) {
            return $student->crm_contact_id !== null
                ? $this->resolveContact($instituteId, (int) $student->crm_contact_id)
                : null;
        }

        if ($lead->converted_contact_id !== null) {
            $contact = $this->resolveContact($instituteId, (int) $lead->converted_contact_id);
            if ($contact !== null) {
                $this->linkContact($student, (int) $contact->id);

                return $contact;
            }
        }

        if ($student->crm_contact_id !== null) {
            $existing = $this->resolveContact($instituteId, (int) $student->crm_contact_id);
            if ($existing !== null) {
                // Link the existing contact as the conversion target so the
                // lead is marked won without creating a duplicate contact.
                $lead->forceFill([
                    'status_id' => CrmLeadStatus::where('slug', CrmLeadStatus::SLUG_WON)->value('id') ?? $lead->status_id,
                    'converted_at' => now(),
                    'converted_contact_id' => $existing->id,
                ])->save();

                return $existing;
            }
        }

        $contact = $this->leads->convert($lead, $instituteId, $actorId);
        $this->linkContact($student, (int) $contact->id);

        return $contact;
    }

    // ------------------------------------------------------------- Helpers

    private function resolveContact(int $instituteId, int $id): ?CrmContact
    {
        return CrmContact::withoutGlobalScopes()
            ->where('id', $id)
            ->where('institute_id', $instituteId)
            ->whereNull('deleted_at')
            ->first();
    }

    private function resolveLead(int $instituteId, int $id): ?CrmLead
    {
        return CrmLead::withoutGlobalScopes()
            ->where('id', $id)
            ->where('institute_id', $instituteId)
            ->whereNull('deleted_at')
            ->first();
    }

    private function linkContact(Student $student, int $contactId): void
    {
        if ((int) $student->crm_contact_id !== $contactId) {
            $student->forceFill(['crm_contact_id' => $contactId])->save();
        }
    }

    private function catalogId(string $model, string $slug): ?int
    {
        $id = $model::query()->where('slug', $slug)->value('id');

        return $id !== null ? (int) $id : null;
    }
}
