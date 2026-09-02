# Project Structure

Standard Laravel 12 layout with a few custom additions. Paths below are
relative to the project root (currently `C:\xampp\htdocs\monetix`).

```
root
├── app/
│   ├── Console/Commands/               # artisan commands
│   │   ├── MigrateMemberships.php
│   │   └── RemapLegacyGeoIds.php
│   ├── Exceptions/                     # custom exceptions
│   ├── Helpers: helpers.php            # global mawa_* helpers (autoloaded via composer)
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/                  # platform-admin console controllers
│   │   │   │   ├── InstituteAdminController.php
│   │   │   │   ├── CourseAdminController.php
│   │   │   │   ├── StudentAdminController.php
│   │   │   │   ├── CertificateAdminController.php
│   │   │   │   ├── NotificationController.php
│   │   │   │   └── SettingController.php
│   │   │   ├── Auth/                   # login/register/password/2FA controllers
│   │   │   ├── DashboardController.php # route('dashboard') — role-aware home
│   │   │   ├── StudentController.php   # institute-side student CRUD
│   │   │   ├── BatchController.php
│   │   │   ├── CourseController.php
│   │   │   ├── CertificateController.php
│   │   │   ├── InstituteSettingController.php
│   │   │   ├── OfflineSyncController.php
│   │   │   ├── StaffInvitationController.php
│   │   │   ├── UserPreferenceController.php
│   │   │   └── WorkspaceController.php
│   │   ├── Middleware/
│   │   │   ├── SetTenantContext.php    # alias: tenant
│   │   │   ├── SetLocale.php           # alias: setlocale
│   │   │   ├── SetFortifyGuard.php     # alias: fortifyguard
│   │   │   └── CheckPermission.php     # alias: permission
│   │   └── Requests/                   # form requests (students, offline sync, settings)
│   │
│   ├── Models/                         # Eloquent models (see database.md)
│   │   └── Concerns/
│   │       ├── TenantScoped.php        # institute_id global scope
│   │       ├── HasUid.php
│   │       └── HasUserPreferences.php
│   ├── Providers/AppServiceProvider.php
│   ├── Services/                       # MembershipService, OfflineSyncService, UserAccountService
│   └── Support/
│       ├── CountryCodes.php            # country => dial code
│       ├── Industries.php              # locale-aware industry/sub-category labels
│       ├── TenantContext.php           # runtime tenant id (static)
│       ├── Workspace.php               # active organization resolution
│       ├── SessionAgent.php
│       └── BdGeo.php                   # Bangladesh division/district/upazila geo
│
├── bootstrap/app.php                   # middleware aliases + routing config
├── config/                             # Laravel config + app-specific lists
│   ├── industries.php                  # industry slugs => {en,bn}
│   ├── sub_industries.php              # industry => sub-category slugs => {en,bn}
│   └── ...
├── database/
│   ├── factories/
│   ├── migrations/                     # see database.md for table coverage
│   └── seeders/
├── Documentation/                     # <-- this documentation
├── lang/mawa/{en,bn}.php               # translation dictionaries
├── public/                             # web root (index.php sits here for Apache)
│   ├── build/assets/                   # Vite build output
│   └── css/                            # base.css, layout.css, components.css, pages.css
├── resources/
│   ├── views/                          # Blade templates
│   │   ├── layouts/admin.blade.php     # platform-admin shell (sidebar + topbar)
│   │   ├── layouts/*.blade.php         # app layouts
│   │   ├── admin/                      # admin console views (institutes, courses, ...)
│   │   ├── students/ batches/ courses/ certificates/ sync/ staff/ settings/
│   │   ├── security/ account/ workspace/
│   │   ├── auth/                       # login/register/password/2FA views
│   │   └── partials/                   # phone.blade.php, ...
│   ├── css/, js/
│   └── views/vendor/                   # published vendor views (pagination, ...)
├── routes/
│   ├── web.php                         # login portals, institute + admin routes
│   ├── auth.php                        # password reset / 2FA / verification / security
│   └── console.php
├── tests/
├── demo/                               # legacy HTML mockups + SQL backups
│   ├── monetix_backup_20260813.sql
│   └── ...
├── composer.json / package.json        # PHP ^8.2, Laravel 12, Fortify; Vite/Tailwind
└── .env                                # local environment (not committed)
```

## Where to look first

| Task | File(s) |
|------|---------|
| Add an admin menu item | `resources/views/layouts/admin.blade.php` sidebar nav |
| Add a route | `routes/web.php` (admin group) or `routes/auth.php` (Fortify features) |
| Change institute<->course flow | `app/Http/Controllers/Admin/CourseAdminController.php` |
| Modify institute profile fields | `config/industries.php`, `config/sub_industries.php`, `admin/institutes/edit.blade.php` |
| Add a translation string | `lang/mawa/en.php` + `lang/mawa/bn.php` |
| Notification copy | `app/Http/Controllers/Admin/*` `notifyInstitute()` methods |
| Role/permission matrix | `CheckPermission` middleware + role/permission models |