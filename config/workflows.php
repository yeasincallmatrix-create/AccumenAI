<?php

/**
 * Step 51 — Education workflow definitions.
 *
 * Each workflow type defines an ordered list of steps. Steps carry an optional
 * responsible role slug and/or permission slug so the UI can hint who should
 * act. The engine itself is intentionally small and Education-focused.
 */
return [

    'types' => [

        'certificate_request' => [
            'label' => 'Certificate Request',
            'steps' => [
                ['name' => 'Document Verification', 'role' => 'receptionist', 'permission' => 'documents.manage'],
                ['name' => 'Academic Verification', 'role' => 'institute-admin', 'permission' => 'academic.manage'],
                ['name' => 'Admin Approval', 'role' => 'institute-owner', 'permission' => 'certificates.manage'],
            ],
        ],

        'student_transfer' => [
            'label' => 'Student Transfer',
            'steps' => [
                ['name' => 'Document Verification', 'role' => 'receptionist', 'permission' => 'documents.manage'],
                ['name' => 'Academic Clearance', 'role' => 'institute-admin', 'permission' => 'academic.manage'],
                ['name' => 'Finance Clearance', 'role' => 'accountant', 'permission' => 'finance.manage'],
                ['name' => 'Admin Approval', 'role' => 'institute-owner', 'permission' => 'students.manage'],
            ],
        ],

        'student_withdrawal' => [
            'label' => 'Student Withdrawal',
            'steps' => [
                ['name' => 'Document Verification', 'role' => 'receptionist', 'permission' => 'documents.manage'],
                ['name' => 'Finance Clearance', 'role' => 'accountant', 'permission' => 'finance.manage'],
                ['name' => 'Admin Approval', 'role' => 'institute-owner', 'permission' => 'students.manage'],
            ],
        ],

        'admission_review' => [
            'label' => 'Admission Review',
            'steps' => [
                ['name' => 'Document Verification', 'role' => 'receptionist', 'permission' => 'documents.manage'],
                ['name' => 'Academic Review', 'role' => 'institute-admin', 'permission' => 'academic.manage'],
                ['name' => 'Final Approval', 'role' => 'institute-owner', 'permission' => 'students.manage'],
            ],
        ],

        // P2-2 — Final Result Review (multi-step approval: HoD → Registrar → Principal)
        'final_result_review' => [
            'label' => 'Final Result Review',
            'steps' => [
                ['name' => 'Head of Department', 'role' => 'hod', 'permission' => 'education.workflow.hod'],
                ['name' => 'Registrar', 'role' => 'registrar', 'permission' => 'education.workflow.registrar'],
                ['name' => 'Principal', 'role' => 'principal', 'permission' => 'education.workflow.principal'],
            ],
        ],

    ],

];
