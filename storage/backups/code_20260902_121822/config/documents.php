<?php

use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CrmContact;
use App\Models\CrmLead;
use App\Models\CrmOrganization;
use App\Models\HrEmployee;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;

/**
 * Step 46 — Document Management configuration.
 *
 * The generic document layer is industry-neutral: any entity listed here can
 * own documents. Files land on the application's public disk (the same
 * convention used by student photos/documents, course materials and banners)
 * under documents/{instituteId}/{entity}/{uuid}.{ext}.
 */
return [

    /*
    | Disk that stores document files. 'public' matches the existing upload
    | convention (storage/app/public, symlinked at public/storage).
    */
    'disk' => env('DOCUMENTS_DISK', 'public'),

    /*
    | Maximum accepted upload size in kilobytes (default 10 MB).
    */
    'max_size_kb' => (int) env('DOCUMENTS_MAX_SIZE_KB', 10240),

    /*
    | Server-side MIME whitelist (never trusts the client extension). Mirrors
    | the CourseMaterialService whitelist; no executables or markup.
    */
    'allowed_mimes' => [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip',
        'text/plain',
        'text/csv',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ],

    /*
    | MIME -> file extension map used to build a safe stored filename.
    */
    'mime_extensions' => [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/zip' => 'zip',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ],

    /*
    | Entities that documents can link to. The slug is used by the routes and
    | the reusable documents panel; 'label' is shown in category pickers.
    */
    'entities' => [
        'student' => ['model' => Student::class, 'label' => 'Student'],
        'teacher' => ['model' => InstituteUser::class, 'label' => 'Teacher'],
        'staff' => ['model' => InstituteUser::class, 'label' => 'Staff'],
        'course' => ['model' => Course::class, 'label' => 'Course'],
        'batch' => ['model' => Batch::class, 'label' => 'Batch'],
        'institute' => ['model' => Institute::class, 'label' => 'Institute'],
        'crm-lead' => ['model' => CrmLead::class, 'label' => 'CRM Lead'],
        'crm-contact' => ['model' => CrmContact::class, 'label' => 'CRM Contact'],
        'crm-organization' => ['model' => CrmOrganization::class, 'label' => 'CRM Organization'],
        'invoice' => ['model' => Invoice::class, 'label' => 'Invoice'],
        'payment' => ['model' => Payment::class, 'label' => 'Payment'],
        'certificate' => ['model' => Certificate::class, 'label' => 'Certificate'],
        'hr-employee' => ['model' => HrEmployee::class, 'label' => 'HR Employee'],
    ],
];
