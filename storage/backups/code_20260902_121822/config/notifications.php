<?php

use App\Services\Notification\Sms\HttpSmsProvider;
use App\Services\Notification\Sms\LogSmsProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Notification events
    |--------------------------------------------------------------------------
    |
    | Industry-neutral registry of notification events. Each event lists its
    | default channels and the placeholder variables its templates may use.
    | The Education / Finance / CRM modules trigger these events through the
    | NotificationService — they never hard-code the delivery engine.
    |
    */

    'events' => [
        'education.student_enrolled' => [
            'label' => 'Student enrolled in a batch',
            'channels' => ['in_app', 'sms', 'email'],
            'variables' => ['student_name', 'registration_number', 'course_name', 'batch_name', 'institute_name', 'start_date'],
        ],
        'education.batch_status_changed' => [
            'label' => 'Batch status changed',
            'channels' => ['in_app'],
            'variables' => ['batch_name', 'course_name', 'institute_name', 'status'],
        ],
        'education.result_published' => [
            'label' => 'Final result published',
            'channels' => ['in_app', 'sms', 'email'],
            'variables' => ['student_name', 'registration_number', 'course_name', 'batch_name', 'institute_name', 'result_status', 'gpa'],
        ],
        'education.certificate_approved' => [
            'label' => 'Certificate approved',
            'channels' => ['in_app', 'sms', 'email'],
            'variables' => ['student_name', 'registration_number', 'course_name', 'institute_name', 'certificate_number'],
        ],
        'finance.invoice_created' => [
            'label' => 'Invoice created',
            'channels' => ['in_app', 'sms', 'email'],
            'variables' => ['student_name', 'registration_number', 'institute_name', 'amount', 'due_date', 'invoice_number'],
        ],
        'finance.payment_received' => [
            'label' => 'Payment received',
            'channels' => ['in_app', 'sms', 'email'],
            'variables' => ['student_name', 'registration_number', 'institute_name', 'amount', 'invoice_number', 'balance'],
        ],
        'crm.lead_created' => [
            'label' => 'CRM lead created',
            'channels' => ['in_app', 'email'],
            'variables' => ['lead_name', 'institute_name', 'lead_source', 'lead_status'],
        ],
        'hr.leave_requested' => [
            'label' => 'Leave requested',
            'channels' => ['in_app', 'email'],
            'variables' => ['employee_name', 'leave_type', 'start_date', 'end_date', 'institute_name'],
        ],
        'hr.leave_decided' => [
            'label' => 'Leave decided',
            'channels' => ['in_app', 'email'],
            'variables' => ['employee_name', 'leave_type', 'status', 'institute_name'],
        ],
        'hr.attendance_correction_requested' => [
            'label' => 'Attendance correction requested',
            'channels' => ['in_app'],
            'variables' => ['employee_name', 'correction_date', 'institute_name'],
        ],
        'hr.attendance_correction_decided' => [
            'label' => 'Attendance correction decided',
            'channels' => ['in_app'],
            'variables' => ['employee_name', 'status', 'institute_name'],
        ],
        'hr.payslip_generated' => [
            'label' => 'Payslip generated',
            'channels' => ['in_app', 'email'],
            'variables' => ['employee_name', 'period_name', 'net_salary', 'institute_name'],
        ],
        'hr.document_expiry' => [
            'label' => 'Document expiring',
            'channels' => ['in_app', 'email'],
            'variables' => ['employee_name', 'document_name', 'expiry_date', 'institute_name'],
        ],
        'hr.workflow_pending' => [
            'label' => 'Workflow pending action',
            'channels' => ['in_app'],
            'variables' => ['employee_name', 'request_type', 'institute_name'],
        ],
        'admission.pending_approval' => [
            'label' => 'Admission pending approval',
            'channels' => ['in_app', 'email'],
            'variables' => ['student_name', 'application_number', 'course_name', 'batch_name', 'submitted_by', 'institute_name'],
        ],
        'admission.approved' => [
            'label' => 'Admission approved',
            'channels' => ['in_app', 'email'],
            'variables' => ['student_name', 'application_number', 'course_name', 'batch_name', 'status', 'institute_name'],
        ],
        'admission.rejected' => [
            'label' => 'Admission rejected',
            'channels' => ['in_app', 'email'],
            'variables' => ['student_name', 'application_number', 'course_name', 'batch_name', 'status', 'rejection_reason', 'institute_name'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Channels
    |--------------------------------------------------------------------------
    */

    'channels' => [
        'in_app' => ['label' => 'In-app'],
        'email' => ['label' => 'Email'],
        'sms' => ['label' => 'SMS'],
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS providers
    |--------------------------------------------------------------------------
    |
    | Provider classes implement App\Services\Notification\Sms\SmsProviderContract.
    | "log" is always available (writes to the log channel, returns a fake id) so
    | the engine never crashes when no real gateway is configured.
    |
    */

    'sms' => [
        'providers' => [
            'log' => LogSmsProvider::class,
            'http' => HttpSmsProvider::class,
        ],
        'default' => env('SMS_DEFAULT_PROVIDER', 'log'),
        // Generic HTTP gateway (HttpSmsProvider) configuration:
        'http' => [
            'url' => env('SMS_HTTP_URL', ''),
            'method' => 'post',
            // Map outgoing fields: [gateway_field => payload_key] where payload
            // keys are 'to', 'message', 'api_key', 'from'.
            'fields' => [
                'to' => 'to',
                'message' => 'message',
                'api_key' => 'api_key',
                'from' => 'from',
            ],
            'response_message_id_path' => '', // dot path into the JSON/XML response
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry / failure policy
    |--------------------------------------------------------------------------
    */

    'retry' => [
        'max_attempts' => 3,
        'delay_seconds' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery
    |--------------------------------------------------------------------------
    |
    | in_app.title/message max lengths match the existing notifications table.
    |
    */

    'delivery' => [
        'queue' => 'notifications',
        'in_app_title_max' => 150,
        'in_app_message_max' => 500,
    ],
];
