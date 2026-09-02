# Model Inventory — `app/Models`

Audit of every model in `C:\xampp\htdocs\monetix\app\Models` (and subfolders).

- **Total Eloquent models:** 60 (63 PHP files under `app/Models` minus 3 concern traits)
- **Sub-directories:** `app/Models/Concerns` (3 traits, documented in their own section below)
- **Models NOT present:** `Plan`, `PlanFeature`, `Countries`. Subscription/billing is handled by `SubscriptionPackage` + `InstituteSubscription`; geography lives in a single `Country` table plus `AdministrativeLevel`/`AdministrativeUnit` reference data.

---

## `app/Models/Concerns` — Shared Traits

### `TenantScoped`
- `bootTenantScoped()` registers a **global scope** named `institute`.
- Behavior: while `App\Support\TenantContext` is enabled (`TenantContext::id() !== null`), every query is constrained with `WHERE institute_id = <context id>` (qualified against the model's table). No-op when no tenant context is set (platform-level queries / CLI see all rows).
- Expects a column `institute_id` on the table.
- Docblock notes it must only be applied to tables owned by a single institute (students, batches, results, …). Course/Subject catalog models deliberately do NOT use it (they are multi-tenant/shared).

### `BranchScoped`
- `bootBranchScoped()` registers a **global scope** named `branch`.
- Behavior: while `App\Support\BranchContext` is enabled, adds `WHERE branch_id = <context id>`. No-op when disabled or when the branch id is null — owners / institute admins / platform users see all branches. Mirrors `TenantScoped`.
- Expects a column `branch_id` on the table.
- Docblock: apply only to tables carrying a direct `branch_id` (students, batches, rooms, notices, transactions, users). Rows inheriting branch through a relation (attendance, results, invoices…) are scoped by their owning model instead.

### `HasUserPreferences`
- Not a global scope — adds preference helpers backed by a `preferences` JSON column (expects an `array` cast on `preferences`).
- `allPreferences(): array` — stored values merged over `defaultPreferences()` (`['theme' => 'default']`).
- `preference(string $key, mixed $default = null): mixed` — single-key read.
- `setPreference(string $key, mixed $value): void` — writes one key and saves via `forceFill`.
- Used by `User`, `InstituteUser`, `PlatformAdmin`. Preference values are per-account (never global).

---

## Models (alphabetical)

### `AccountHead`
- **File:** `app/Models/AccountHead.php` — `accounts_heads` chart-of-accounts entry for an institute.
- **Table:** `account_heads` | **Timestamps:** disabled | **Guarded:** `[]`
- **Traits:** `Concerns\TenantScoped`
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `transactions()` → `HasMany` `Transaction`

### `ActivityLog`
- **File:** `app/Models/ActivityLog.php`
- **Table:** `activity_logs` | **Timestamps:** disabled | **Guarded:** `[]`
- **Traits:** none
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`

### `AdministrativeLevel`
- **File:** `app/Models/AdministrativeLevel.php` — geography reference data (shared, not tenant-scoped).
- **Table:** `administrative_levels` | **Timestamps:** implicit (default on) | **Fillable:** `country_id`, `level_number`, `name`, `slug`, `status`
- **Traits:** none
- **Casts:** `status => boolean`
- **Relationships:**
  - `country()` → `BelongsTo` `Country`
  - `units()` → `HasMany` `AdministrativeUnit` (FK `administrative_level_id`)

### `AdministrativeUnit`
- **File:** `app/Models/AdministrativeUnit.php` — geography reference (shared).
- **Table:** `administrative_units` | **Timestamps:** implicit (default on) | **Fillable:** `country_id`, `administrative_level_id`, `parent_id`, `name`, `code`, `postal_code`, `latitude`, `longitude`, `status`
- **Traits:** none
- **Casts:** `status => boolean`, `latitude => float`, `longitude => float`
- **Relationships:**
  - `country()` → `BelongsTo` `Country`
  - `level()` → `BelongsTo` `AdministrativeLevel` (FK `administrative_level_id`)
  - `parent()` → `BelongsTo` `AdministrativeUnit` (self-referential, FK `parent_id`)

### `AiLog`
- **File:** `app/Models/AiLog.php`
- **Table:** `ai_logs` | **Timestamps:** disabled | **Guarded:** `[]`
- **Traits:** none
- **Casts:** `tools => array`, `created_at => datetime`

### `AiUsage`
- **File:** `app/Models/AiUsage.php`
- **Table:** `ai_usage` | **Timestamps:** enabled | **Guarded:** `[]`
- **Traits:** none
- **Notable:** constants `PERIOD_TYPE_DAILY = 'daily'`, `PERIOD_TYPE_MONTHLY = 'monthly'`. No relationships.

### `Attendance`
- **File:** `app/Models/Attendance.php`
- **Table:** `attendance` | **Timestamps:** disabled | **Guarded:** `[]`
- **Traits:** `Concerns\TenantScoped` (no direct `branch_id`; branch is inherited through batch, per trait docblock)
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `batch()` → `BelongsTo` `Batch`
  - `student()` → `BelongsTo` `Student`
  - `markedBy()` → `BelongsTo` `InstituteUser` (FK `marked_by`)

### `AuditLog`
- **File:** `app/Models/AuditLog.php`
- **Table:** `audit_logs` | **Timestamps:** disabled | **Guarded:** `[]`
- **Traits:** none
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`

### `Batch`
- **File:** `app/Models/Batch.php`
- **Table:** `batches` | **Timestamps:** enabled | **Guarded:** `[]`
- **Traits:** `Concerns\BranchScoped`, `Concerns\TenantScoped`, `SoftDeletes`
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `course()` → `BelongsTo` `Course`
  - `branch()` → `BelongsTo` `Branch`
  - `teacher()` → `BelongsTo` **`Membership`** (FK `teacher_id`) — teacher row is a membership record, not an `InstituteUser`
  - `room()` → `BelongsTo` `Room`
  - `enrollments()` → `HasMany` `StudentEnrollment`
  - `exams()` → `HasMany` `Exam`
  - `results()` → `HasMany` `Result`
  - `certificates()` → `HasMany` `Certificate`
  - `attendance()` → `HasMany` `Attendance`
  - `students()` → `BelongsToMany` `Student` via pivot `student_enrollments`

### `Branch`
- **File:** `app/Models/Branch.php`
- **Table:** `branches` | **Timestamps:** enabled | **Guarded:** `[]`
- **Traits:** `Concerns\TenantScoped` (Branch itself is scoped by institute but NOT by branch)
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `manager()` → `BelongsTo` `InstituteUser` (FK `manager_user_id`)
  - `rooms()` → `HasMany` `Room`
  - `students()` → `HasMany` `Student`
  - `batches()` → `HasMany` `Batch`
  - `users()` → `HasMany` `InstituteUser`

### `CashMemo`
- **File:** `app/Models/CashMemo.php`
- **Table:** `cash_memos` | **Timestamps:** disabled | **Guarded:** `[]`
- **Traits:** `Concerns\TenantScoped`
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `student()` → `BelongsTo` `Student`
  - `creator()` → `BelongsTo` `InstituteUser` (FK `created_by`)
  - `offlineOrigin()` → `BelongsTo` `OfflineSyncQueue` (FK `offline_origin_id`) — cash memos can originate from an offline sync entry

### `Certificate`
- **File:** `app/Models/Certificate.php`
- **Table:** `certificates` | **Timestamps:** enabled | **Guarded:** `[]`
- **Traits:** `Concerns\TenantScoped`, `SoftDeletes`
- **Casts:** `issue_date => date`, `reviewed_at => datetime`
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `student()` → `BelongsTo` `Student`
  - `course()` → `BelongsTo` `Course`
  - `batch()` → `BelongsTo` `Batch`
  - `result()` → `BelongsTo` `Result`
  - `reviewedBy()` → `BelongsTo` `PlatformAdmin` (FK `reviewed_by`) — review is a platform-level action
  - `issuedBy()` → `BelongsTo` `InstituteUser` (FK `issued_by`)

### `Country`
- **File:** `app/Models/Country.php` — the single geography country table (shared reference data).
- **Table:** `countries` | **Timestamps:** implicit (default on) | **Fillable:** `name`, `iso2`, `iso3`, `phone_code`, `status`
- **Traits:** none
- **Casts:** `status => boolean`
- **Relationships:**
  - `levels()` → `HasMany` `AdministrativeLevel`
  - `units()` → `HasMany` `AdministrativeUnit`
  - `selectableLevels()` → `HasMany` `AdministrativeLevel` with additional constraint chain: `where('level_number', '<=', 3)->orderBy('level_number')` — the three levels the UI exposes

### `Course`
- **File:** `app/Models/Course.php`
- **Table:** `courses` | **Timestamps:** enabled | **Guarded:** `[]`
- **Traits:** none (catalog is multi-tenant/shared — deliberately not `TenantScoped`; institute ownership still expressible via `institute_id`)
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `category()` → `BelongsTo` `CourseCategory` (FK `category_id`)
  - `subCategory()` → `BelongsTo` `CourseSubCategory` (FK `sub_category_id`)
  - `batches()` → `HasMany` `Batch`
  - `exams()` → `HasMany` `Exam`
  - `results()` → `HasMany` `Result`
  - `certificates()` → `HasMany` `Certificate`
  - `enrollments()` → `HasMany` `StudentEnrollment`
  - `courseRequests()` → `HasMany` `CourseRequest`
  - `subjects()` → `BelongsToMany` `Subject` via pivot `course_subjects`
  - `instituteAssignments()` → `HasMany` `InstituteCourse` (FK `course_id`) — assignment of the catalog course to institutes

### `CourseCategory`
- **File:** `app/Models/CourseCategory.php`
- **Table:** `course_categories` | **Timestamps:** disabled | **Guarded:** `[]`
- **Traits:** `Concerns\TenantScoped`
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `courses()` → `HasMany` `Course`
  - `subjects()` → `HasMany` `Subject`
  - `subCategories()` → `HasMany` `CourseSubCategory`

### `CourseRequest`
- **File:** `app/Models/CourseRequest.php`
- **Table:** `course_requests` | **Timestamps:** enabled | **Guarded:** `[]`
- **Traits:** `Concerns\TenantScoped`
- **Casts:** `reviewed_at => datetime`
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `course()` → `BelongsTo` `Course`
  - `requestedBy()` → `BelongsTo` `InstituteUser` (FK `requested_by`)
  - `reviewedBy()` → `BelongsTo` `PlatformAdmin` (FK `reviewed_by`) — review is platform-admin action

### `CourseSubject`
- **File:** `app/Models/CourseSubject.php` — pivot-style model for `course_subjects`.
- **Table:** `course_subjects` | **Timestamps:** disabled | **Guarded:** `[]`
- **Traits:** none
- **Relationships:**
  - `course()` → `BelongsTo` `Course`
  - `subject()` → `BelongsTo` `Subject`

### `CourseSubCategory`
- **File:** `app/Models/CourseSubCategory.php`
- **Table:** `course_sub_categories` | **Timestamps:** disabled | **Guarded:** `[]`
- **Traits:** `Concerns\TenantScoped`
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `category()` → `BelongsTo` `CourseCategory` (FK `category_id`)
  - `courses()` → `HasMany` `Course`

### `Exam`
- **File:** `app/Models/Exam.php`
- **Table:** `exams` | **Timestamps:** disabled | **Guarded:** `[]`
- **Traits:** `Concerns\TenantScoped`
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `course()` → `BelongsTo` `Course`
  - `batch()` → `BelongsTo` `Batch`
  - `creator()` → `BelongsTo` `InstituteUser` (FK `created_by`)
  - `results()` → `HasMany` `ExamResult`
  - `subjects()` → `HasMany` `ExamSubject` ordered by `->orderBy('id')`

### `ExamResult`
- **File:** `app/Models/ExamResult.php`
- **Table:** `exam_results` | **Timestamps:** enabled | **Guarded:** `[]`
- **Traits:** `Concerns\TenantScoped`
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `exam()` → `BelongsTo` `Exam`
  - `subject()` → `BelongsTo` `Subject`
  - `student()` → `BelongsTo` `Student`
  - `enteredBy()` → `BelongsTo` `InstituteUser` (FK `entered_by`)

### `ExamSubject`
- **File:** `app/Models/ExamSubject.php`
- **Table:** `exam_subjects` | **Timestamps:** enabled | **Guarded:** `[]`
- **Traits:** none
- **Relationships:**
  - `exam()` → `BelongsTo` `Exam`
  - `subject()` → `BelongsTo` `Subject`

### `ExamType`
- **File:** `app/Models/ExamType.php` — thin look-up.
- **Table:** `exam_types` | **Timestamps:** disabled | **Guarded:** `[]`
- **Traits:** none | **Relationships:** none

### `GalleryAlbum`
- **File:** `app/Models/GalleryAlbum.php`
- **Table:** `gallery_albums` | **Timestamps:** disabled | **Guarded:** `[]`
- **Traits:** `Concerns\TenantScoped`
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `media()` → `HasMany` `GalleryMedia`

### `GalleryMedia`
- **File:** `app/Models/GalleryMedia.php`
- **Table:** `gallery_media` | **Timestamps:** disabled | **Guarded:** `[]`
- **Traits:** `Concerns\TenantScoped`
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `album()` → `BelongsTo` `GalleryAlbum`

### `GeoImport`
- **File:** `app/Models/GeoImport.php` — one run of a geography data package import.
- **Table:** `geo_imports` | **Timestamps:** implicit (default on) | **Guarded:** `[]`
- **Traits:** none — docblock: geo data is global shared reference data, never institute/tenant-scoped
- **Casts:** `file_size`, `total_records`, `inserted_records`, `updated_records`, `skipped_records`, `duplicate_count`, `error_count` all `integer`; `started_at`, `completed_at` `datetime`
- **Relationships:**
  - `country()` → `BelongsTo` `Country`
  - `creator()` → `BelongsTo` `PlatformAdmin` (FK `created_by`)

### `GradingScale`
- **File:** `app/Models/GradingScale.php` — thin look-up.
- **Table:** `grading_scale` | **Timestamps:** disabled | **Guarded:** `[]`
- **Traits:** none | **Relationships:** none

### `IndustrySetting`
- **File:** `app/Models/IndustrySetting.php` — platform-wide settings look-up.
- **Table:** `industry_settings` | **Timestamps:** enabled | **Guarded:** `[]`
- **Traits:** none | **Relationships:** none

### `Institute`
- **File:** `app/Models/Institute.php` — an organization/tenant (AccumenAI terminology: "Organization").
- **Table:** `institutes` | **Timestamps:** enabled | **Guarded:** `[]`
- **Traits:** none (it IS the tenant root — no scope applied to itself)
- **Note:** has `institution_id` concept in membership link but `memberships()` uses `institution_id` FK on pivot; `memberUsers()`/`users` pivot table is `institution_user`.
- **Relationships:**
  - `students()` → `HasMany` `Student`
  - `branches()` → `HasMany` `Branch`
  - `rooms()` → `HasMany` `Room`
  - `batches()` → `HasMany` `Batch`
  - `exams()` → `HasMany` `Exam`
  - `results()` → `HasMany` `Result`
  - `certificates()` → `HasMany` `Certificate`
  - `notices()` → `HasMany` `Notice`
  - `galleryAlbums()` → `HasMany` `GalleryAlbum`
  - `invoices()` → `HasMany` `Invoice`
  - `payments()` → `HasMany` `Payment`
  - `accountHeads()` → `HasMany` `AccountHead`
  - `transactions()` → `HasMany` `Transaction`
  - `attendance()` → `HasMany` `Attendance`
  - `cashMemos()` → `HasMany` `CashMemo`
  - `offlineSyncQueue()` → `HasMany` `OfflineSyncQueue`
  - `courseRequests()` → `HasMany` `CourseRequest`
  - `instituteCourses()` → `HasMany` `InstituteCourse`
  - `instituteSubjects()` → `HasMany` `InstituteSubject`
  - `instituteSubscriptions()` → `HasMany` `InstituteSubscription`
  - `courses()` → `HasMany` `Course`
  - `users()` → `HasMany` `InstituteUser`
  - `memberships()` → `HasMany` `Membership`, FK `institution_id` (note: `Membership`'s table is `institution_user`)
  - `memberUsers()` → `BelongsToMany` `User` via pivot `institution_user` (`institution_id` / `user_id`), with pivot columns `uuid, role_id, branch_id, employee_id, designation, department, qualification, salary, joining_date, status`, `->withTimestamps()`
  - `settings()` → `HasOne` `InstituteSetting`
  - `package()` → `BelongsTo` `SubscriptionPackage` (FK `package_id` on `institutes`)

### `InstituteCourse`
- **File:** `app/Models/InstituteCourse.php` — assignment of a (shared) catalog course to an institute.
- **Table:** `institute_courses` | **Timestamps:** enabled | **Guarded:** `[]`
- **Traits:** `Concerns\TenantScoped`
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `course()` → `BelongsTo` `Course`
  - `assignedBy()` → `BelongsTo` `PlatformAdmin` (FK `assigned_by`)

### `InstituteSetting`
- **File:** `app/Models/InstituteSetting.php`
- **Table:** `institute_settings` | **Timestamps:** enabled | **Guarded:** `[]`
- **Traits:** `Concerns\TenantScoped`
- **Casts:** `ai_config => array`  (defined via `casts()` method rather than property)
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`

### `InstituteSubject`
- **File:** `app/Models/InstituteSubject.php` — assignment of a shared subject to an institute.
- **Table:** `institute_subjects` | **Timestamps:** enabled | **Guarded:** `[]`
- **Traits:** `Concerns\TenantScoped`
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `subject()` → `BelongsTo` `Subject`
  - `assignedBy()` → `BelongsTo` `PlatformAdmin` (FK `assigned_by`)

### `InstituteSubscription`
- **File:** `app/Models/InstituteSubscription.php`
- **Table:** `institute_subscriptions` | **Timestamps:** disabled | **Guarded:** `[]`
- **Traits:** `Concerns\TenantScoped`
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `package()` → `BelongsTo` `SubscriptionPackage`

### `InstituteUser`
- **File:** `app/Models/InstituteUser.php` — legacy per-institute account (largely superseded by global `User` + `Membership`).
- **Table:** `institute_users` | **Timestamps:** enabled | **Guarded:** `[]`
- **Traits:** `Concerns\BranchScoped`, `Concerns\TenantScoped`, `HasUserPreferences`, `MustVerifyEmail`, `Notifiable`, `SoftDeletes`, `TwoFactorAuthenticatable`; extends `Authenticatable implements MustVerifyEmailContract`
- **Hidden:** `password_hash`
- **Casts (via property):** `password_hash => hashed`, `failed_login_count => integer`, `salary => decimal:2`, `locked_until => datetime`, `last_login_at => datetime`, `email_verified_at => datetime`, `two_factor_confirmed_at => datetime`, `preferences => array`
- **Notable methods:**
  - `getAuthPassword()` / `getAuthPasswordName()` — real auth column is `password_hash`
  - `isLocked()` — `locked_until` future check
  - `hasRole(string|array)` / `hasPermission(string)` / `hasAnyPermission(array)` / `isOwner()` — role-slug and permission checks; owner (`institute-owner`) is a super-user; permissions come from `role->permissions` (role_permissions matrix)
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `role()` → `BelongsTo` `Role`
  - `branch()` → `BelongsTo` `Branch`

### `Installment`
- **File:** `app/Models/Installment.php`
- **Table:** `installments` | **Timestamps:** disabled | **Guarded:** `[]`
- **Traits:** `Concerns\TenantScoped`
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `invoice()` → `BelongsTo` `Invoice`
  - `student()` → `BelongsTo` `Student`

### `Invoice`
- **File:** `app/Models/Invoice.php`
- **Table:** `invoices` | **Timestamps:** enabled | **Guarded:** `[]`
- **Traits:** `Concerns\TenantScoped`
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `student()` → `BelongsTo` `Student`
  - `enrollment()` → `BelongsTo` `StudentEnrollment` (FK `enrollment_id`)
  - `creator()` → `BelongsTo` `InstituteUser` (FK `created_by`)
  - `items()` → `HasMany` `InvoiceItem`
  - `installments()` → `HasMany` `Installment`
  - `payments()` → `HasMany` `Payment`

### `InvoiceItem`
- **File:** `app/Models/InvoiceItem.php` — line item on an invoice.
- **Table:** `invoice_items` | **Timestamps:** disabled | **Guarded:** `[]`
- **Traits:** none
- **Relationships:**
  - `invoice()` → `BelongsTo` `Invoice`

### `LegacyUser`
- **File:** `app/Models/LegacyUser.php`
- **Table:** **`users`** (same table as `User`!) | **Timestamps:** enabled | **Guarded:** `[]`
- **Traits:** none | **Relationships:** none
- **Unusual:** A distinct, intentionally-lite model pointing at the same `users` table as `User`, presumably legacy/read-only or migration support.

### `LoginAttempt`
- **File:** `app/Models/LoginAttempt.php`
- **Table:** `login_attempts` | **Timestamps:** disabled | **Guarded:** `[]`
- **Traits:** none | **Relationships:** none

### `Membership`
- **File:** `app/Models/Membership.php` — the many-to-many link between a global `User` and an `Institute`; role is scoped to the membership.
- **Table:** **`institution_user`** (legacy pivot name, kept as the "real" association model) | **Timestamps:** enabled | **Guarded:** `[]`
- **Traits:** `SoftDeletes`
- **Casts:** `salary => decimal:2`, `joining_date => date`
- **Events / boot:**
  - `static::booted()` registers `creating` and `updating` hooks that call `assertRoleAllowedForAccountType()`.
  - `assertRoleAllowedForAccountType()` — resolves the role fresh from `role_id`; throws `AccountTypeMismatchException` if an `institute-owner` role is placed on a non-owner account (`staffCannotOwn()`) or a non-owner role on an owner account (`ownerCannotBeStaff()`).
- **Accessors/methods:** `hasRole()`, `hasPermission()`, `hasAnyPermission()`, `isOwner()` (same semantics as `InstituteUser`), `isActive()` (status `active` and `deleted_at === null`).
- **Relationships:**
  - `user()` → `BelongsTo` `User`
  - `institution()` → `BelongsTo` `Institute` (FK `institution_id`)
  - `role()` → `BelongsTo` `Role`
  - `branch()` → `BelongsTo` `Branch`

### `Notice`
- **File:** `app/Models/Notice.php`
- **Table:** `notices` | **Timestamps:** enabled | **Guarded:** `[]`
- **Traits:** `Concerns\BranchScoped`, `Concerns\TenantScoped`
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `branch()` → `BelongsTo` `Branch`
  - `creator()` → `BelongsTo` `InstituteUser` (FK `created_by`)

### `Notification`
- **File:** `app/Models/Notification.php`
- **Table:** `notifications` | **Timestamps:** disabled | **Guarded:** `[]`
- **Traits:** none
- **Casts:** `created_at => datetime` (manual datetime cast since timestamps are off)
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `reads()` → `HasMany` `NotificationRead`

### `NotificationRead`
- **File:** `app/Models/NotificationRead.php`
- **Table:** `notification_reads` | **Timestamps:** disabled | **Guarded:** `[]`
- **Traits:** none
- **Relationships:**
  - `notification()` → `BelongsTo` `Notification`

### `OfflineSyncQueue`
- **File:** `app/Models/OfflineSyncQueue.php`
- **Table:** `offline_sync_queue` | **Timestamps:** disabled | **Guarded:** `[]`
- **Traits:** `Concerns\TenantScoped`
- **Casts:** `payload => array`, `created_offline_at => datetime`, `synced_at => datetime`, `reviewed_at => datetime`
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `creator()` → `BelongsTo` `InstituteUser` (FK `created_by`)
  - `materializedCashMemo()` → `HasOne` `CashMemo` (FK `offline_origin_id`) — inverse of `CashMemo::offlineOrigin()`

### `Payment`
- **File:** `app/Models/Payment.php`
- **Table:** `payments` | **Timestamps:** disabled | **Guarded:** `[]`
- **Traits:** `Concerns\TenantScoped`
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `invoice()` → `BelongsTo` `Invoice`
  - `installment()` → `BelongsTo` `Installment`
  - `student()` → `BelongsTo` `Student`
  - `receivedBy()` → `BelongsTo` `InstituteUser` (FK `received_by`)

### `Permission`
- **File:** `app/Models/Permission.php`
- **Table:** `permissions` | **Timestamps:** disabled | **Guarded:** `[]`
- **Traits:** none
- **Relationships:**
  - `roles()` → `BelongsToMany` `Role` via pivot `role_permissions`

### `PlatformAdmin`
- **File:** `app/Models/PlatformAdmin.php` — platform (Super Admin) user.
- **Table:** `platform_admins` | **Timestamps:** enabled | **Guarded:** `[]`
- **Traits:** `HasUserPreferences`, `MustVerifyEmail`, `Notifiable`, `TwoFactorAuthenticatable`; extends `Authenticatable implements MustVerifyEmailContract`
- **Hidden:** `password_hash`, `two_factor_secret`
- **Casts:** `password_hash => hashed`, `is_owner => boolean`, `last_login_at => datetime`, `email_verified_at => datetime`, `two_factor_confirmed_at => datetime`, `preferences => array`
- **Notable:** `getAuthPassword()` / `getAuthPasswordName()` — auth uses `password_hash` column. `is_owner` boolean identifies the primary super-admin.
- **Relationships:** none

### `RegNoSequence`
- **File:** `app/Models/RegNoSequence.php` — registration-number sequence store.
- **Table:** `reg_no_sequence` | **Timestamps:** disabled | **Guarded:** `[]`
- **Traits:** none | **Relationships:** none

### `Result`
- **File:** `app/Models/Result.php` — course-level result (distinct from `ExamResult`).
- **Table:** `results` | **Timestamps:** enabled | **Guarded:** `[]`
- **Traits:** `Concerns\TenantScoped`
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `student()` → `BelongsTo` `Student`
  - `course()` → `BelongsTo` `Course`
  - `batch()` → `BelongsTo` `Batch`
  - `publishedBy()` → `BelongsTo` `InstituteUser` (FK `published_by`)
  - `certificate()` → `HasOne` `Certificate` (inverse of `Certificate::result()`)

### `Role`
- **File:** `app/Models/Role.php`
- **Table:** `roles` | **Timestamps:** disabled | **Guarded:** `[]`
- **Traits:** none
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `users()` → `HasMany` `InstituteUser`
  - `permissions()` → `BelongsToMany` `Permission` via pivot `role_permissions`

### `RolePermission`
- **File:** `app/Models/RolePermission.php` — pivot-style model for the role↔permission matrix.
- **Table:** `role_permissions` | **Timestamps:** disabled | **Guarded:** `[]`
- **Traits:** none
- **Relationships:**
  - `role()` → `BelongsTo` `Role`
  - `permission()` → `BelongsTo` `Permission`

### `Room`
- **File:** `app/Models/Room.php`
- **Table:** `rooms` | **Timestamps:** disabled | **Guarded:** `[]`
- **Traits:** `Concerns\BranchScoped`, `Concerns\TenantScoped`
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `branch()` → `BelongsTo` `Branch`
  - `batches()` → `HasMany` `Batch`

### `Setting`
- **File:** `app/Models/Setting.php` — key/value platform settings store.
- **Table:** `settings` | **Timestamps:** enabled | **Guarded:** `[]`
- **Traits:** none
- **Unusual / notable:**
  - Static `$encrypted = ['ai.api_key']` — secret keys encrypted at rest with the app key.
  - `get(string $key, mixed $default = null): mixed` — reads row by `key`; decrypts known encrypted keys, with legacy-plaintext fallback on decrypt failure.
  - `set(string $key, mixed $value): void` — encrypts known secret keys; `updateOrCreate` on `key`.
- **Relationships:** none

### `Student`
- **File:** `app/Models/Student.php`
- **Table:** `students` | **Timestamps:** enabled | **Fillable:** long explicit list incl. `institute_id`, `branch_id`, `student_id_number`, `registration_number`, `roll_number`, both names, photo/document, parent fields, demographics (gender/dob/blood_group/religion/nationality/nid/birth_cert/passport), contacts, `present_*`/`permanent_*` address fields with split geography columns (`present_division_id`/`district_id`/`upazila_id` OR `present_country_id`/`admin_1..3_id`), `admission_date`, `status`
- **Traits:** `Concerns\BranchScoped`, `Concerns\TenantScoped`, `SoftDeletes`
- **Casts:** `dob => date`, `admission_date => date`
- **Scope:** `scopeSearch(Builder, ?string $term)` — fuzzy multi-column `LIKE` across first/last name, id number variants, phone, email, passport, NID, birth-cert.
- **Static helper:** `nextStudentNumber(int $instituteId): string` — computes next `student_id_number` as `MAX(CAST(student_id_number AS UNSIGNED)) + 1` for the institute **including soft-deleted rows** and `->withoutGlobalScope('institute')`.
- **Accessor:** `getFullNameAttribute()` → trimmed `first_name . last_name`.
- **Relationships:**
  - `branch()` → `BelongsTo` `Branch`
  - `institute()` → `BelongsTo` `Institute`
  - `enrollments()` → `HasMany` `StudentEnrollment`
  - `examResults()` → `HasMany` `ExamResult`
  - `results()` → `HasMany` `Result`
  - `certificates()` → `HasMany` `Certificate`
  - `invoices()` → `HasMany` `Invoice`
  - `payments()` → `HasMany` `Payment`
  - `attendance()` → `HasMany` `Attendance`
  - `cashMemos()` → `HasMany` `CashMemo`
  - `batches()` → `BelongsToMany` `Batch` via pivot `student_enrollments`

### `StudentEnrollment`
- **File:** `app/Models/StudentEnrollment.php` — enrollment/registration record (also the pivot for student↔batch many-to-many).
- **Table:** `student_enrollments` | **Timestamps:** enabled | **Guarded:** `[]`
- **Traits:** `Concerns\TenantScoped` (no branch scope — branch inherited via batch, per trait docblock)
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `student()` → `BelongsTo` `Student`
  - `course()` → `BelongsTo` `Course`
  - `batch()` → `BelongsTo` `Batch`

### `Subject`
- **File:** `app/Models/Subject.php`
- **Table:** `subjects` | **Timestamps:** enabled | **Guarded:** `[]`
- **Traits:** none (shared catalog — not tenant-scoped; institute linkage via `institutes()` many-to-many and `InstituteSubject` assignments)
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute` (direct ownership column still exists)
  - `category()` → `BelongsTo` `CourseCategory` (FK `category_id`)
  - `courses()` → `BelongsToMany` `Course` via pivot `course_subjects`
  - `institutes()` → `BelongsToMany` `Institute` via pivot `institute_subjects`

### `SubjectRequest`
- **File:** `app/Models/SubjectRequest.php` — institute requests a new shared subject.
- **Table:** `subject_requests` | **Timestamps:** enabled | **Guarded:** `[]`
- **Traits:** `Concerns\TenantScoped`
- **Casts:** `reviewed_at => datetime`
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `category()` → `BelongsTo` `CourseCategory` (FK `category_id`)
  - `requestedBy()` → `BelongsTo` `InstituteUser` (FK `requested_by`)
  - `reviewedBy()` → `BelongsTo` `PlatformAdmin` (FK `reviewed_by`)

### `SubscriptionPackage`
- **File:** `app/Models/SubscriptionPackage.php` — replaces the notion of a `Plan`.
- **Table:** `subscription_packages` | **Timestamps:** enabled | **Guarded:** `[]`
- **Traits:** none
- **Relationships:**
  - `institutes()` → `HasMany` `Institute` (via `institutes.package_id`)

### `Theme`
- **File:** `app/Models/Theme.php`
- **Table:** `themes` | **Timestamps:** enabled | **Guarded:** `[]`
- **Traits:** none | **Relationships:** none

### `Transaction`
- **File:** `app/Models/Transaction.php` — bookkeeping entry linked to a payment and account head.
- **Table:** `transactions` | **Timestamps:** disabled | **Guarded:** `[]`
- **Traits:** `Concerns\BranchScoped`, `Concerns\TenantScoped`
- **Relationships:**
  - `institute()` → `BelongsTo` `Institute`
  - `branch()` → `BelongsTo` `Branch`
  - `accountHead()` → `BelongsTo` `AccountHead`
  - `payment()` → `BelongsTo` `Payment`
  - `creator()` → `BelongsTo` `InstituteUser` (FK `created_by`)

### `User`
- **File:** `app/Models/User.php` — the global AccumenAI account (person-level identity/auth), many memberships across organizations.
- **Table:** `users` | **Timestamps:** enabled | **Fillable:** `uuid`, `name`, `first_name`, `last_name`, `email`, `phone`, `preferred_language`, `preferences`, `photo`, `password_hash`, `status`, `account_type`
- **Traits:** `HasFactory`, `HasUserPreferences`, `MustVerifyEmail`, `Notifiable`, `SoftDeletes`, `TwoFactorAuthenticatable`; extends `Authenticatable implements MustVerifyEmailContract`
- **Hidden:** `password_hash`, `remember_token`, `two_factor_secret`, `two_factor_recovery_codes`
- **Casts (via `casts()` method):** `email_verified_at => datetime`, `password_hash => hashed`, `failed_login_count => integer`, `locked_until => datetime`, `last_login_at => datetime`, `two_factor_confirmed_at => datetime`, `preferences => array`
- **Events / boot:**
  - `booted()` → `saving` hook: only when existing AND `account_type` is dirty, calls `assertAccountTypeConsistentWithMemberships()`.
  - `assertAccountTypeConsistentWithMemberships()` — throws `AccountTypeMismatchException` (`staffCannotConvert()` / `ownerCannotBeConvert`→`ownerCannotConvert`) if converting an account type that would contradict its memberships' roles.
- **Methods:** `isOwnerAccount()` (account_type `owner`), `isStaffAccount()` (account_type `staff`), `isLocked()`, `getAuthPassword()`/`getAuthPasswordName()` — auth column is `password_hash`.
- **Relationships:**
  - `memberships()` → `HasMany` `Membership`
  - `institutions()` → `BelongsToMany` `Institute` via pivot `institution_user` (`user_id`/`institution_id`), pivot columns `uuid, role_id, branch_id, employee_id, designation, department, qualification, salary, joining_date, status`, `->withTimestamps()`

---

## Cross-cutting findings / anomalies

1. **Dual models on the same table:** `User` and `LegacyUser` both map to table `users` (`User` is the full auth model; `LegacyUser` is a thin unconstrained model with no traits/relations — likely for legacy data access).
2. **Membership as the "real" pivot model:** `Membership` (`institution_user`) carries `role_id`, `branch_id`, employee/payroll columns and soft-deletes; it enforces the **owner vs staff account-type invariant** on create/update by throwing `AccountTypeMismatchException`. `Institute::memberUsers()` and `User::institutions()` expose the same pivot with `withPivot`.
3. **`Batch::teacher()` points at `Membership`** (FK `teacher_id`), not `InstituteUser` — teachers are memberships.
4. **Two result concepts:** `Result` (course-level, has `published_by`, one-to-one `Certificate`) vs `ExamResult` (exam×subject-level, `entered_by`).
5. **Tenant discipline is explicit:** `TenantScoped` only on institute-owned tables; shared catalog (Course, Subject), geography (Country, AdministrativeLevel/Unit, GeoImport), platform (Role/Permission, packages) and global identity (User, Membership, Notification…) are deliberately unscoped. `BranchScoped` is applied separately where a direct `branch_id` column exists (Student, Batch, Room, Notice, Transaction, InstituteUser).
6. **`$guarded = []` is the norm** (fully mass-assignable); explicit `$fillable` only on `Student`, `User`, `Country`, `AdministrativeLevel`, `AdministrativeUnit`.
7. **Timestamps discipline:** disabled on many operational/fiscal tables (payments, cash memos, transactions, installments, invoice_items, account_heads, attendance) and all lookups; enabled on core entities.
8. **Encrypted-at-rest setting:** `Setting::$encrypted` (`ai.api_key`) with legacy-plaintext fallback.
9. **Authorization vocabulary:** roles are slug-based (`institute-owner`); `InstituteUser` and `Membership` share identical `hasRole`/`hasPermission`/`isOwner` implementations reading `role->permissions` from the `role_permissions` matrix.
10. **No Plan/PlanFeature models exist** — the billing concept is `SubscriptionPackage` + `InstituteSubscription` (`Institute::package()` via `institutes.package_id`).
11. **Geography on Student is dual-schema:** legacy `present_division/district/upazila` columns coexist with newer `present_country_id`/`present_admin_1..3_id` columns (same for permanent addresses).
12. **Offline-first trace:** `OfflineSyncQueue.payload` + `materializedCashMemo()`/`CashMemo::offlineOrigin()` link offline-created cash memos back to their sync queue origin.