# MAWA ACADEMY — Language Reference (EN ⇄ BN)

This document lists every translatable string in the project.

- **English file:** `lang/en.php`
- **Bangla file:** `lang/bn.php`
- **Total strings:** 1155 (complete parity — every English key has a Bangla value)
- **Usage in code:**
  - `mawa_lang('sidebar.students')` → returns the string in the current language
  - `mawa_e('sidebar.students')` → same, HTML-escaped
  - `mawa_current_lang()` → `'en'` or `'bn'`
  - The active language comes from `lang/` query, session, or the user's `preferred_language` (defaults to `en`)
- **Fallback:** if a key is missing in `bn.php`, the English value is used automatically.

The Bangla column below is the *current Bangla translation*. The English column is what displays in English mode and is the reference for the demo pages (`demo_institute_*.html`). The `txt.md` file in the root covers geo data (divisions/districts/upazilas + zip codes) used by the address dropdowns.

---

## Structure of the language files

```
return [
    'lang'      => [...],   // language switcher labels
    'brand'     => [...] ,  // platform brand text
    'nav'       => [...],   // public website menu
    'sidebar'   => [...],   // institute panel sidebar
    'actions'   => [...],   // shared buttons (Save, Cancel, Search...)
    'options'   => [...],   // select options (gender, religion, blood group...)
    'status'    => [...],   // status badges (Active, Completed...)
    'students'  => [...],   // student pages (list, modal, profile)
    'batches'   => [...],   // batches + enrollments
    ... (46 sections total)
];
```

---

## 46 sections at a glance

| Section | Purpose | Approx. strings |
|---|---|---|
| `lang` | Language switcher (English / বাংলা) | 3 |
| `brand` | Platform name + tagline | 4 |
| `navbar` / `nav` | Public website navigation | 8 |
| `sidebar` | Institute panel menu items | 11 |
| `actions` | Shared buttons/labels | 32 |
| `options` | Select options (gender, religion, blood group…) | 16 |
| `status` | Status labels | 10 |
| `dashboard` | Super Admin dashboard | few |
| `inst_dashboard` | Institute dashboard (stats, recent admissions) | 11 |
| `students` | Student list / add-edit modal / profile | ~95 |
| `batches` | Batches + enrollments | ~40 |
| `exams` / `results` | Exams, marks, results | ~70 |
| `certificates` | Certificates | ~30 |
| `messages` | Flash / error messages | ~60 |
| `notifications` | Notification center | ~30 |
| `offline` | Offline-mode UI | ~25 |
| `cash_memo` | Cash memo | ~20 |
| `profile`, `settings`, `change_password`, `theme_admin`, … | Settings & account | varied |

---

## Section: `lang`

| Key | English | বাংলা |
|---|---|---|
| `lang.en` | English | English |
| `lang.bn` | বাংলা | বাংলা |
| `lang.label` | Language | ভাষা |

## Section: `brand`

| Key | English | বাংলা |
|---|---|---|
| `brand.name` | MAWA ACADEMY | মাওয়া একাডেমি |
| `brand.tagline` | Professional Technical Training Institute | পেশাগত কারিগরি প্রশিক্ষণ ইনস্টিটিউট |

## Section: `nav`

| Key | English | বাংলা |
|---|---|---|
| `nav.home` | Home | হোম |
| `nav.about` | About | সম্পর্কে |
| `nav.courses` | Courses | কোর্সসমূহ |
| `nav.result` | Result | ফলাফল |
| `nav.verify` | Certificate Verification | সার্টিফিকেট যাচাই |
| `nav.contact` | Contact | যোগাযোগ |
| `nav.login` | Login | লগইন |
| `nav.register` | Registration | নিবন্ধন |

## Section: `sidebar`

| Key | English | বাংলা |
|---|---|---|
| `sidebar.dashboard` | Dashboard | ড্যাশবোর্ড |
| `sidebar.students` | Students | শিক্ষার্থী |
| `sidebar.batches` | Batches | ব্যাচ |
| `sidebar.courses` | Courses | কোর্সসমূহ |
| `sidebar.exams_results` | Exams & Results | পরীক্ষা ও ফলাফল |
| `sidebar.certificates` | Certificates | সার্টিফিকেট |
| `sidebar.cash_memo` | Cash Memo | ক্যাশ মেমো |
| `sidebar.offline_review` | Offline Review | অফলাইন রিভিউ |
| `sidebar.reports` | Reports | রিপোর্ট |
| `sidebar.soon` | Soon | শীঘ্রই |
| `sidebar.profile` | Profile | প্রোফাইল |

## Section: `actions`

| Key | English | বাংলা |
|---|---|---|
| `actions.add` | Add | যোগ করুন |
| `actions.save` | Save | সংরক্ষণ |
| `actions.cancel` | Cancel | বাতিল |
| `actions.edit` | Edit | সম্পাদনা |
| `actions.delete` | Delete | মুছুন |
| `actions.search` | Search | অনুসন্ধান |
| `actions.filter` | Filter | ফিল্টার |
| `actions.view` | View | দেখুন |
| `actions.close` | Close | বন্ধ করুন |
| `actions.back` | Back | ফিরে যান |
| `actions.confirm` | Confirm | নিশ্চিত করুন |
| `actions.download` | Download | ডাউনলোড |
| `actions.print` | Print | প্রিন্ট |
| `actions.maximize` | Maximize | বড় করুন |
| `actions.restore` | Restore | পুনরুদ্ধার |
| `actions.submit` | Submit | জমা দিন |

*(complete list — see `lang/en.php` + `lang/bn.php` for all 1155 keys)*

---

## Section: `status`

| Key | English | বাংলা |
|---|---|---|
| `status.active` | Active | সক্রিয় |
| `status.completed` | Completed | সম্পন্ন |
| `status.dropped` | Dropped | ঝরে পড়া |
| `status.suspended` | Suspended | স্থগিত |
| `status.upcoming` | Upcoming | আসন্ন |
| `status.running` | Running | চলমান |
| `status.cancelled` | Cancelled | বাতিল |
| `status.transferred` | Transferred | স্থানান্তরিত |
| `status.pass` | Pass | পাস |
| `status.fail` | Fail | ফেল |

## Section: `students` (most-used keys in the demo pages)

| Key | English | বাংলা |
|---|---|---|
| `students.add_new` | Add Student | নতুন শিক্ষার্থী |
| `students.search_placeholder` | Search name, student ID, registration no., phone, passport, NID… | নাম, আইডি, রেজি. নং, ফোন, পাসপোর্ট, এনআইডি অনুসন্ধান… |
| `students.all_status` | All Status | সব স্ট্যাটাস |
| `students.table_no` | Student ID | আইডি |
| `students.roll_number` | Roll | রোল |
| `students.table_name` | Name | নাম |
| `students.table_phone` | Phone | ফোন |
| `students.table_admission` | Admission | ভর্তির তারিখ |
| `students.table_status` | Status | স্ট্যাটাস |
| `students.first_name` | First Name | প্রথম নাম |
| `students.last_name` | Last Name | শেষ নাম |
| `students.gender` | Gender | লিঙ্গ |
| `students.dob` | Date of Birth | জন্ম তারিখ |
| `students.admission_date` | Admission Date | ভর্তির তারিখ |
| `students.phone` | Phone | ফোন |
| `students.email` | Email | ইমেইল |
| `students.father_name` | Father's Name | পিতার নাম |
| `students.mother_name` | Mother's Name | মাতার নাম |
| `students.guardian_phone` | Guardian Phone | অভিভাবকের ফোন |
| `students.nationality` | Nationality | জাতীয়তা |
| `students.nid_or_birth_certificate` | NID or Birth Certificate | এনআইডি বা জন্ম নিবন্ধন |
| `students.passport_number` | Passport Number | পাসপোর্ট নম্বর |
| `students.blood_group` | Blood Group | রক্তের গ্রুপ |
| `students.religion` | Religion | ধর্ম |
| `students.section_guardian` | Guardian Information | অভিভাবকের তথ্য |
| `students.section_documents` | Documents | নথিপত্র |
| `students.section_address` | Address | ঠিকানা |
| `students.section_present_address` | Present Address | বর্তমান ঠিকানা |
| `students.section_permanent_address` | Permanent Address | স্থায়ী ঠিকানা |
| `students.same_as_present` | Same as present | বর্তমান ঠিকানার মতো |
| `students.division` | Division | বিভাগ |
| `students.district` | District | জেলা |
| `students.upazila` | Upazila | উপজেলা |
| `students.post_office` | Post Office | পোস্ট অফিস |
| `students.zip_code` | Zip Code | পোস্ট কোড |
| `students.area_road_house` | Area / Road | এলাকা / রোড |
| `students.section_emergency` | Emergency Contact | জরুরি যোগাযোগ |
| `students.emergency_contact_name` | Emergency Contact Name | জরুরি যোগাযোগের নাম |
| `students.emergency_contact_phone` | Emergency Contact Phone | জরুরি যোগাযোগের ফোন |
| `students.confirm_delete` | Delete this student? | এই শিক্ষার্থীটি মুছবেন? |
| `students.empty` | No students found. | কোনো শিক্ষার্থী পাওয়া যায়নি। |
| `students.created` | Student created successfully. | শিক্ষার্থী সফলভাবে যোগ হয়েছে। |
| `students.updated` | Student updated successfully. | শিক্ষার্থীর তথ্য হালনাগাদ হয়েছে। |
| `students.deleted` | Student deleted. | শিক্ষার্থী মুছে ফেলা হয়েছে। |

## Section: `inst_dashboard` (demo dashboard page)

| Key | English | বাংলা |
|---|---|---|
| `inst_dashboard.welcome` | Welcome, :name 👋 | স্বাগতম, :name 👋 |
| `inst_dashboard.stat_students` | Total Students | মোট শিক্ষার্থী |
| `inst_dashboard.stat_running` | Running Batches | চলমান ব্যাচ |
| `inst_dashboard.stat_batches` | Total Batches | মোট ব্যাচ |
| `inst_dashboard.stat_courses` | Assigned Courses | নির্ধারিত কোর্স |
| `inst_dashboard.recent_admissions` | Recent Admissions | সাম্প্রতিক ভর্তি |
| `inst_dashboard.table_name` | Name | নাম |
| `inst_dashboard.table_student_id` | Student ID | শিক্ষার্থী আইডি |
| `inst_dashboard.table_admission` | Admission Date | ভর্তির তারিখ |
| `inst_dashboard.table_status` | Status | স্ট্যাটাস |
| `inst_dashboard.empty` | No students have been admitted yet. | এখনো কোনো শিক্ষার্থী ভর্তি হয়নি। |

---

## How to read this document / keep translations in sync

1. **Add a new string:** put the key in both `lang/en.php` and `lang/bn.php` under the right section, e.g. `'students' => ['new_key' => 'Value']`.
2. **Use it in PHP:** `echo mawa_e('students.new_key');`
3. **Use it in JS:** `var x = <?php echo json_encode(mawa_lang('students.new_key')); ?>;`
4. Never hardcode UI text in pages — always route through the `lang/` files so the language switcher works.
5. The `lang/` query param toggles the language: `?lang=bn` / `?lang=en`.

---

*Generated from `lang/en.php` + `lang/bn.php` (1155 keys, 46 sections). Demo pages (`demo_institute_*.html`) currently render the English column.*