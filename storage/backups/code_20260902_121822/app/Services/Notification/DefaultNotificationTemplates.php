<?php

namespace App\Services\Notification;

/**
 * Industry-neutral default notification templates, seeded globally (institute_id
 * = NULL) for every registered event × channel × language. Institutes can
 * override any template through the settings UI; the resolver prefers the
 * institute override and falls back to these defaults.
 */
final class DefaultNotificationTemplates
{
    /**
     * @return array<string, array<string, array{en: array{subject: string, body: string}, bn: array{subject: string, body: string}}>>
     */
    public static function all(): array
    {
        return [
            'education.student_enrolled' => [
                'in_app' => [
                    'en' => ['subject' => 'Enrollment confirmed', 'body' => 'Dear {{ student_name }}, you have been enrolled in {{ course_name }} ({{ batch_name }}) at {{ institute_name }}.'],
                    'bn' => ['subject' => 'ভর্তি নিশ্চিত হয়েছে', 'body' => 'প্রিয় {{ student_name }}, আপনাকে {{ institute_name }}-এর {{ course_name }} ({{ batch_name }}) কোর্সে ভর্তি করা হয়েছে।'],
                ],
                'sms' => [
                    'en' => ['subject' => '', 'body' => 'Dear {{ student_name }}, your enrollment in {{ course_name }} ({{ batch_name }}) is confirmed. Reg. {{ reg_no }}.'],
                    'bn' => ['subject' => '', 'body' => 'প্রিয় {{ student_name }}, {{ course_name }} ({{ batch_name }}) কোর্সে আপনার ভর্তি নিশ্চিত হয়েছে। রেজি. {{ reg_no }}।'],
                ],
                'email' => [
                    'en' => ['subject' => 'Enrollment confirmed – {{ course_name }}', 'body' => "Dear {{ student_name }},\n\nYour enrollment in {{ course_name }} ({{ batch_name }}) at {{ institute_name }} has been confirmed.\n\nRegistration: {{ reg_no }}\nStart date: {{ start_date }}\n\nBest regards,\n{{ institute_name }}"],
                    'bn' => ['subject' => 'ভর্তি নিশ্চিত – {{ course_name }}', 'body' => "প্রিয় {{ student_name }},\n\n{{ institute_name }}-এর {{ course_name }} ({{ batch_name }}) কোর্সে আপনার ভর্তি নিশ্চিত হয়েছে।\n\nরেজিস্ট্রেশন: {{ reg_no }}\nশুরুর তারিখ: {{ start_date }}\n\nধন্যবাদ,\n{{ institute_name }}"],
                ],
            ],

            'education.batch_status_changed' => [
                'in_app' => [
                    'en' => ['subject' => 'Batch status changed', 'body' => 'The status of batch {{ batch_name }} ({{ course_name }}) has changed to {{ status }}.'],
                    'bn' => ['subject' => 'ব্যাচের অবস্থা পরিবর্তিত হয়েছে', 'body' => '{{ course_name }} ({{ batch_name }}) ব্যাচের অবস্থা পরিবর্তন হয়ে {{ status }} হয়েছে।'],
                ],
                'sms' => [
                    'en' => ['subject' => '', 'body' => 'Batch {{ batch_name }} ({{ course_name }}) status is now {{ status }}.'],
                    'bn' => ['subject' => '', 'body' => '{{ course_name }} ({{ batch_name }}) ব্যাচের অবস্থা এখন {{ status }}।'],
                ],
                'email' => [
                    'en' => ['subject' => 'Batch status updated – {{ batch_name }}', 'body' => 'The status of batch {{ batch_name }} ({{ course_name }}) at {{ institute_name }} has changed to {{ status }}.'],
                    'bn' => ['subject' => 'ব্যাচের অবস্থা আপডেট – {{ batch_name }}', 'body' => '{{ institute_name }}-এর {{ course_name }} ({{ batch_name }}) ব্যাচের অবস্থা পরিবর্তন হয়ে {{ status }} হয়েছে।'],
                ],
            ],

            'education.result_published' => [
                'in_app' => [
                    'en' => ['subject' => 'Result published', 'body' => 'Your result for {{ course_name }} ({{ batch_name }}) has been published. Status: {{ result_status }}.{{ gpa }}'],
                    'bn' => ['subject' => 'ফলাফল প্রকাশিত হয়েছে', 'body' => '{{ course_name }} ({{ batch_name }}) কোর্সের আপনার ফলাফল প্রকাশিত হয়েছে। অবস্থা: {{ result_status }}।{{ gpa }}'],
                ],
                'sms' => [
                    'en' => ['subject' => '', 'body' => 'Dear {{ student_name }}, your {{ course_name }} result has been published. Status: {{ result_status }}.{{ gpa }}'],
                    'bn' => ['subject' => '', 'body' => 'প্রিয় {{ student_name }}, আপনার {{ course_name }} কোর্সের ফলাফল প্রকাশিত হয়েছে। অবস্থা: {{ result_status }}।{{ gpa }}'],
                ],
                'email' => [
                    'en' => ['subject' => 'Result published – {{ course_name }}', 'body' => "Dear {{ student_name }},\n\nYour result for {{ course_name }} ({{ batch_name }}) has been published.\n\nStatus: {{ result_status }}\n{{ gpa }}\n\nBest regards,\n{{ institute_name }}"],
                    'bn' => ['subject' => 'ফলাফল প্রকাশিত – {{ course_name }}', 'body' => "প্রিয় {{ student_name }},\n\n{{ course_name }} ({{ batch_name }}) কোর্সের আপনার ফলাফল প্রকাশিত হয়েছে।\n\nঅবস্থা: {{ result_status }}\n{{ gpa }}\n\nধন্যবাদ,\n{{ institute_name }}"],
                ],
            ],

            'education.certificate_approved' => [
                'in_app' => [
                    'en' => ['subject' => 'Certificate approved', 'body' => 'Your certificate for {{ course_name }} has been approved (No. {{ certificate_number }}).'],
                    'bn' => ['subject' => 'সার্টিফিকেট অনুমোদিত', 'body' => '{{ course_name }} কোর্সের আপনার সার্টিফিকেট অনুমোদিত হয়েছে (নং {{ certificate_number }})।'],
                ],
                'sms' => [
                    'en' => ['subject' => '', 'body' => 'Dear {{ student_name }}, your {{ course_name }} certificate (No. {{ certificate_number }}) has been approved.'],
                    'bn' => ['subject' => '', 'body' => 'প্রিয় {{ student_name }}, আপনার {{ course_name }} সার্টিফিকেট (নং {{ certificate_number }}) অনুমোদিত হয়েছে।'],
                ],
                'email' => [
                    'en' => ['subject' => 'Certificate approved – {{ course_name }}', 'body' => "Dear {{ student_name }},\n\nYour certificate for {{ course_name }} has been approved.\n\nCertificate number: {{ certificate_number }}\n\nBest regards,\n{{ institute_name }}"],
                    'bn' => ['subject' => 'সার্টিফিকেট অনুমোদিত – {{ course_name }}', 'body' => "প্রিয় {{ student_name }},\n\n{{ course_name }} কোর্সের আপনার সার্টিফিকেট অনুমোদিত হয়েছে।\n\nসার্টিফিকেট নম্বর: {{ certificate_number }}\n\nধন্যবাদ,\n{{ institute_name }}"],
                ],
            ],

            'finance.invoice_created' => [
                'in_app' => [
                    'en' => ['subject' => 'New invoice', 'body' => 'Invoice {{ invoice_number }} of {{ amount }} is due on {{ due_date }}.'],
                    'bn' => ['subject' => 'নতুন চালান', 'body' => 'চালান {{ invoice_number }}-এর {{ amount }} টাকা {{ due_date }} তারিখের মধ্যে পরিশোধ করতে হবে।'],
                ],
                'sms' => [
                    'en' => ['subject' => '', 'body' => 'Dear {{ student_name }}, invoice {{ invoice_number }} of {{ amount }} is due on {{ due_date }}.'],
                    'bn' => ['subject' => '', 'body' => 'প্রিয় {{ student_name }}, চালান {{ invoice_number }}-এর {{ amount }} টাকা {{ due_date }} তারিখের মধ্যে পরিশোধ করুন।'],
                ],
                'email' => [
                    'en' => ['subject' => 'Invoice {{ invoice_number }} from {{ institute_name }}', 'body' => "Dear {{ student_name }},\n\nYour invoice {{ invoice_number }} of {{ amount }} is due on {{ due_date }}.\n\nBest regards,\n{{ institute_name }}"],
                    'bn' => ['subject' => '{{ institute_name }} থেকে চালান {{ invoice_number }}', 'body' => "প্রিয় {{ student_name }},\n\nআপনার চালান {{ invoice_number }}-এর {{ amount }} টাকা {{ due_date }} তারিখের মধ্যে পরিশোধ করুন।\n\nধন্যবাদ,\n{{ institute_name }}"],
                ],
            ],

            'finance.payment_received' => [
                'in_app' => [
                    'en' => ['subject' => 'Payment received', 'body' => 'Payment of {{ amount }} against invoice {{ invoice_number }} has been received. Outstanding balance: {{ balance }}.'],
                    'bn' => ['subject' => 'পেমেন্ট গৃহীত হয়েছে', 'body' => 'চালান {{ invoice_number }}-এর বিপরীতে {{ amount }} টাকা পেমেন্ট গৃহীত হয়েছে। অবশিষ্ট বকেয়া: {{ balance }}।'],
                ],
                'sms' => [
                    'en' => ['subject' => '', 'body' => 'Dear {{ student_name }}, we received {{ amount }} against invoice {{ invoice_number }}. Balance: {{ balance }}.'],
                    'bn' => ['subject' => '', 'body' => 'প্রিয় {{ student_name }}, চালান {{ invoice_number }}-এর বিপরীতে {{ amount }} টাকা পেয়েছি। বকেয়া: {{ balance }}।'],
                ],
                'email' => [
                    'en' => ['subject' => 'Payment received – invoice {{ invoice_number }}', 'body' => "Dear {{ student_name }},\n\nWe have received {{ amount }} against invoice {{ invoice_number }}.\n\nOutstanding balance: {{ balance }}\n\nBest regards,\n{{ institute_name }}"],
                    'bn' => ['subject' => 'পেমেন্ট গৃহীত – চালান {{ invoice_number }}', 'body' => "প্রিয় {{ student_name }},\n\nচালান {{ invoice_number }}-এর বিপরীতে {{ amount }} টাকা গৃহীত হয়েছে।\n\nঅবশিষ্ট বকেয়া: {{ balance }}\n\nধন্যবাদ,\n{{ institute_name }}"],
                ],
            ],

            'crm.lead_created' => [
                'in_app' => [
                    'en' => ['subject' => 'New lead', 'body' => 'New lead {{ lead_name }} ({{ lead_source }}) created with status {{ lead_status }}.'],
                    'bn' => ['subject' => 'নতুন লিড', 'body' => 'নতুন লিড {{ lead_name }} ({{ lead_source }}) তৈরি হয়েছে, অবস্থা {{ lead_status }}।'],
                ],
                'sms' => [
                    'en' => ['subject' => '', 'body' => 'New lead {{ lead_name }} created with status {{ lead_status }}.'],
                    'bn' => ['subject' => '', 'body' => 'নতুন লিড {{ lead_name }} তৈরি হয়েছে, অবস্থা {{ lead_status }}।'],
                ],
                'email' => [
                    'en' => ['subject' => 'New CRM lead – {{ lead_name }}', 'body' => "A new lead has been created.\n\nName: {{ lead_name }}\nSource: {{ lead_source }}\nStatus: {{ lead_status }}\n\nBest regards,\n{{ institute_name }}"],
                    'bn' => ['subject' => 'নতুন CRM লিড – {{ lead_name }}', 'body' => "একটি নতুন লিড তৈরি হয়েছে।\n\nনাম: {{ lead_name }}\nসূত্র: {{ lead_source }}\nঅবস্থা: {{ lead_status }}\n\nধন্যবাদ,\n{{ institute_name }}"],
                ],
            ],

            'admission.pending_approval' => [
                'in_app' => [
                    'en' => ['subject' => 'New admission pending approval', 'body' => '{{ student_name }} ({{ application_number }}) has been admitted to {{ course_name }} by {{ submitted_by }}. Review and approve.'],
                    'bn' => ['subject' => 'নতুন ভর্তি অনুমোদন অপেক্ষমান', 'body' => '{{ student_name }} ({{ application_number }}) কে {{ submitted_by }} {{ course_name }} কোর্সে ভর্তি করেছেন। পর্যালোচনা ও অনুমোদন করুন।'],
                ],
                'email' => [
                    'en' => ['subject' => 'Admission pending approval – {{ student_name }}', 'body' => "A new admission request is pending your approval.\n\nStudent: {{ student_name }}\nApplication: {{ application_number }}\nCourse: {{ course_name }}\nBatch: {{ batch_name }}\nSubmitted by: {{ submitted_by }}\n\nPlease review and approve or reject.\n\nBest regards,\n{{ institute_name }}"],
                    'bn' => ['subject' => 'ভর্তি অনুমোদন অপেক্ষমান – {{ student_name }}', 'body' => "একটি নতুন ভর্তি আবেদন আপনার অনুমোদন অপেক্ষা করছে।\n\nছাত্র/ছাত্রী: {{ student_name }}\nআবেদন নম্বর: {{ application_number }}\nকোর্স: {{ course_name }}\nব্যাচ: {{ batch_name }}\nআবেদনকারী: {{ submitted_by }}\n\nঅনুগ্রহ করে পর্যালোচনা করুন।\n\nধন্যবাদ,\n{{ institute_name }}"],
                ],
            ],

            'admission.approved' => [
                'in_app' => [
                    'en' => ['subject' => 'Admission approved', 'body' => 'Your admission {{ application_number }} for {{ course_name }} has been approved.'],
                    'bn' => ['subject' => 'ভর্তি অনুমোদিত হয়েছে', 'body' => 'আপনার {{ course_name }} কোর্সের ভর্তি আবেদন {{ application_number }} অনুমোদিত হয়েছে।'],
                ],
                'email' => [
                    'en' => ['subject' => 'Admission approved – {{ application_number }}', 'body' => "Your admission has been approved.\n\nApplication: {{ application_number }}\nCourse: {{ course_name }}\nStatus: {{ status }}\n\nBest regards,\n{{ institute_name }}"],
                    'bn' => ['subject' => 'ভর্তি অনুমোদিত – {{ application_number }}', 'body' => "আপনার ভর্তি অনুমোদিত হয়েছে।\n\nআবেদন: {{ application_number }}\nকোর্স: {{ course_name }}\nঅবস্থা: {{ status }}\n\nধন্যবাদ,\n{{ institute_name }}"],
                ],
            ],

            'admission.rejected' => [
                'in_app' => [
                    'en' => ['subject' => 'Admission rejected', 'body' => 'Your admission {{ application_number }} for {{ course_name }} has been rejected.'],
                    'bn' => ['subject' => 'ভর্তি প্রত্যাখ্যাত', 'body' => 'আপনার {{ course_name }} কোর্সের ভর্তি আবেদন {{ application_number }} প্রত্যাখ্যাত হয়েছে।'],
                ],
                'email' => [
                    'en' => ['subject' => 'Admission rejected – {{ application_number }}', 'body' => "Your admission has been rejected.\n\nApplication: {{ application_number }}\nCourse: {{ course_name }}\nStatus: {{ status }}\n\nBest regards,\n{{ institute_name }}"],
                    'bn' => ['subject' => 'ভর্তি প্রত্যাখ্যাত – {{ application_number }}', 'body' => "আপনার ভর্তি প্রত্যাখ্যাত হয়েছে।\n\nআবেদন: {{ application_number }}\nকোর্স: {{ course_name }}\nঅবস্থা: {{ status }}\n\nধন্যবাদ,\n{{ institute_name }}"],
                ],
            ],
        ];
    }
}
