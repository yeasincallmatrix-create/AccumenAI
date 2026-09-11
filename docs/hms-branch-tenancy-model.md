# HMS Branch Tenancy Model (Phase 18)

Canonical reference for branch-level isolation in the HMS. Future phases
must follow this model; do not invent a parallel branch system.

## 1. Institute hierarchy

```text
Platform
  └── Institute / Tenant (institute_id)
        ├── Shared masters (institute-wide)
        └── Branch / Facility (branches.id, nullable user assignment)
              └── Branch-owned clinical transactions (branch_id, nullable)
```

## 2. Branch hierarchy (reused, not created)

The `branches` table, `Branch` model and `BranchContext` predate HMS
work and are reused as-is: `branches(id, code, institute_id, name,
manager_user_id, phone, email, address, status, is_principal,
deleted_at)`. Users carry at most one branch (`memberships.branch_id` /
`institute_users.branch_id`); NULL means institute-wide (owner/admin).
There is no branch CRUD UI and no multi-branch membership; both are
deferred, not reimplemented here.

## 3. Branch ownership rules

- Branch-owned transactions carry nullable `branch_id` (Stage 1):
  appointments, encounters, admissions, wards, beds, prescriptions,
  lab_orders, invoices, pharmacy_stock, pharmacy_dispenses, follow_ups,
  clinical_audit_logs.
- Derived children inherit through their parent and carry NO branch_id:
  encounter_diagnoses → encounter; lab_results → lab_order;
  prescription_items → prescription; vitals/notes → admission/appointment;
  beds additionally derive from their ward on write.
- Linked rows inherit deterministically: prescription/lab/follow-up/IPD
  invoice from their encounter/admission; an explicit contradicting
  branch is rejected, never silently accepted.
- `branch_id` is write-once: update paths unset it (no cross-branch
  transfer workflow exists).

## 4. Shared master rules

Institute-wide, never branched: patients (identity), terminology
(concepts/products/ingredients/forms/routes/identifiers), DGDA/RxNorm,
CDS rules, departments/specialties, LabTest/medicine catalogs, doctors
(identity), number_sequences. Doctor BRANCH ASSIGNMENT lives in the
`doctor_branch` pivot (branch, doctor, active flag) without duplicating
clinician identity.

## 5. Patient identity rule

`patients.institute_id` is the tenant identity; patients have no branch.
Branch users see the institute patient directory; clinical EVENTS are
branch-fenced. Never filter patient identity by branch.

## 6. Clinical transaction rule

New records default to the actor's context branch (validated, never
trusted blindly); institute-wide actors leave legacy NULL unless they
state a branch. Reads: context branch + legacy NULLs. Mutations:
exact-branch or legacy NULL, else 403. Legacy NULL is a legitimate
pre-branch state — never backfilled by guessing.

## 7. Doctor assignment rule

Doctors with no active `doctor_branch` rows are legacy-compatible
(institute-wide). Assigned doctors are restricted to their branches for
record ownership; pickers hide doctors assigned exclusively elsewhere.
Assignment is by `medical_doctors.id`; clinical references stay on
`users.id` as before.

## 8. Timeline rule

`PatientTimelineService` applies the same branch fence per branch-owned
type (diagnoses/vitals/notes via parents, transfers via audit rows,
results via orders). Problems are institute-level longitudinal context
and ignore branch. Bounds, ordering and pagination unchanged.

## 9. Audit rule

`clinical_audit_logs.branch_id` records context when the auditable
carries (or derives) one; historical NULLs stay valid. No second audit
system.

## 10. Legacy null-branch rule

NULL = pre-branch record, visible to branch-scoped readers, mutable only
through the normal fences. Stage 3 may backfill ONLY provably correct
branches; NOT NULL enforcement only after full migration + validation.

## 11. Authorization hierarchy

Institute fence → branch fence → doctor/actor fence → permission.
Existing helpers (`ensureSameInstitute`, `ensurePatientVisible`,
`mayActOnPatient`, `doctorFenceId`, `permission:`) are preserved;
`ensureBranchAccess` / `scopeBranch` / `resolveBranchId` /
`doctorBranchOk` augment them in `MedicalController`. No Spatie/Gates.

## 12. Unique-numbering decisions

Unchanged and institute-wide: MR / RX / LAB / INV / TPA / ENC stay
globally unique with institute segments (`NumberSequenceService`,
scoped institute+type+year); appointment serials stay
(institute, doctor, day) — branch adds NO numbering split and no
renumbering ever occurs.

## 13. Deferred branch work (Phase 18.1 status)

CLOSED in Phase 18.1:
- Branch CRUD/management UI (`BranchController`, admin-gated, no destroy;
  deactivation lifecycle-safe, inactive branches reject new records).
- Doctor↔branch assignment UI on the branch page (existing pivot reused).
- Livewire queue branch enforcement (mount/load/reorder/complete/start/
  cancel all server-side fenced; serial logic untouched).
- Service-level stock/expiry branch filtering (SQL, no PHP post-filters).
- Branch-aware FEFO selection (compatible-branch splits only); the
  row-locked decrement in deductStock remains the concurrency primitive —
  NO reservation table exists in this architecture (documented STOP
  decision: a true available→reserved→consumed lifecycle would require a
  new inventory subsystem).
- Create-form forged branch_id hardening (verified per form + tested).
- `medical:backfill-branch` dry-run/execute command for deterministic
  fills (beds←wards, clinical rows←linked parents, audit←parents).

STILL DEFERRED (unchanged):
- Branch switcher UI (single-branch memberships need none; verified).
- Per-branch FEFO reservation table (see above).
- NOT NULL enforcement (columns stay nullable; backfill is opt-in).
- Multi-branch memberships (data model supports one branch per
  user+institute; doctors span branches via the pivot instead).
