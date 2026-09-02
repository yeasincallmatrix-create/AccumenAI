# Database

MySQL database `monetix`. Schema is split between the original import
(`demo/monetix_backup_20260813.sql`) and later migrations in
`database/migrations/` (which add columns/tables on top).

## Entity groups

### Platform & identity
| Model | Table | Notes |
|-------|-------|-------|
| `PlatformAdmin` | `platform_admins` | login via `password_hash` |
| `InstituteUser` | `institute_users` | staff; tied to institute, has role/permissions |
| `User` | `users` | global owner/staff account |
| `Membership` | `memberships` (pivot user↔institute) | holds `role_id`, status, permissions via `institution_user`? see below |
| `Role` / `Permission` | `roles`, `permissions` | static RBAC |
| `RolePermission` | `role_permissions` | matrix |
| `Institute` | `institutes` | the tenant; soft-deletes |
| `SubscriptionPackage` | `subscription_packages` | package tiers |
| `InstituteSetting` | `institute_settings` | language etc. |

> `Membership` pivots global `User` → `Institute` and stores the staff role
> and status (`active`). `institute_users` are **not** pivots — they are real
> accounts that log in with `password_hash`.

### Institute domain (tenant-scoped)
`students`, `branches`, `rooms`, `batches`, `exams`, `exam_types`,
`results`, `attendance`/`attendances`, `notices`, `gallery_albums`,
`gallery_media`, `certificates`, `invoices`, `invoice_items`, `payments`,
`cash_memos`, `account_heads`, `transactions`, `subscriptions`.

All carry `institute_id` and use `Concerns\TenantScoped`.

### Catalog (NOT tenant-scoped)
`courses`, `subjects`, `course_categories`, `course_sub_categories`,
`course_subjects`, `institute_courses` (assignment), `institute_subjects`,
`course_requests`.

### Platform / runtime
`notifications` (scope: `platform`/`institute`/`user`),
`notification_reads` (per-recipient read markers), `offline_sync_queue`,
`reg_no_sequences`, `login_attempts`, `sessions`, `audit_logs`/`activity_logs`,
`legacy_users`, `themes`.

## Key models & relationships

### Institute
```php
class Institute extends Model {
    protected $guarded = [];
    protected $casts = ['status' => ...]; // approved | pending | suspended | rejected
    public function owner()        // User owner
    public function staff()        // InstituteUser
    public function students()     // HasMany
    public function branches()     // HasMany
    public function batches()      // HasMany
    public function rooms()        // HasMany
    public function exams()        // HasMany
    public function results()      // HasMany
    public function certificates() // HasMany
    public function notices()      // HasMany
    public function galleryAlbums()
    public function invoices()
    public function instituteCourses()
    public function courseRequests()
    public function subscription()      // SubscriptionPackage
    public function activeSubscription()
    public function settings()          // InstituteSetting
}
```
- Columns: `name`, `slug`, `code`, `email`, `phone`, `password_hash`,
  `address`, `established_year`, `logo_path`, `verification_status`,
  `verified_at`, `industry`, `sub_industry`, `country` (default `Bangladesh`),
  `package_id`, `subscription_expires_at`, `status`, `deleted_at`,
  `deleted_by`.
- Soft-deleted institutes (recycle bin) keep `deleted_by` for audit.
- `scopeCountry($country)`, `scopeIndustry($industry)` filters used by
  dashboard + admin lists.

### Course / Catalog
`Course` (catalog, shared): `slug`, `code`, `title`, `description`, `status`,
`meta`. `institute_courses` assignment pivot; `course_requests` for
approve/reject flow (`status`: pending/approved/rejected, `review_note`).

### Student
`institute_id`, `branch_id`, `batch_id`, `uid`, `first_name`, `last_name`,
`email`, `phone`, `gender`, `date_of_birth`, `address`, `photo_path`,
`father_name`, `mother_name`, `blood_group`, `status`, `enrolled_at`,
`admission_date`. UID via `Concerns\HasUid`.

### User / Membership
`User` has `preferred_language`, `theme` (HasUserPreferences trait). Active
membership resolved through `App\Support\Workspace`.

## Notifications engine
```sql
notifications(id, institute_id NULL, scope ENUM(platform,institute,user),
              category ENUM(info,success,warning,error,security),
              title, message, created_at)
notification_reads(id, notification_id, user_type ENUM(platform_admin,institute_user,student),
                   user_id, read_at,
                   UNIQUE(notification_id, user_type, user_id))
```
See [notifications.md](notifications.md).

## Migrations inventory (recent)
- `..._add_industry_to_institutes_table`
- `..._add_sub_industry_to_institutes_table`
- `..._create_notification_reads_table`
- `..._seed_default_role_permissions`
- membership/geo remap commands live under `app/Console/Commands/`.

## Conventions
- Institute-owned models: `institute_id` column + `TenantScoped` trait.
- Timestamps standard (`created_at`/`updated_at`); soft deletes via
  `deleted_at` (binary if present).
- Money columns stored as decimals; IDs as unsigned bigint.
- `password_hash` (not `password`) for admin/staff logins.