# Routes

## Route files

| File | Purpose |
|------|---------|
| `routes/web.php` | Portals, dashboard, institute modules, admin console |
| `routes/auth.php` | Fortify-backed password reset / 2FA / verification / security |
| `routes/console.php` | artisan commands (`migrate:memberships`, `remap:legacy-geo-ids`) |

## Auth portals (`routes/web.php`)

| Method | URI | Name | Controller | Notes |
|--------|-----|------|------------|-------|
| GET | `/login` | `login` | `Auth\UserLoginController@showLoginForm` | global user |
| POST | `/login` | `login.submit` | `@login` | `throttle:5,15` |
| GET | `/admin/login` | `admin.login` | `PlatformAdminLoginController@showLoginForm` | console |
| POST | `/admin/login` | `admin.login.submit` | `@login` | `throttle:5,15` |
| GET | `/institute/login` | `institute.login` | `InstituteUserLoginController@showLoginForm` | staff |
| POST | `/institute/login` | `institute.login.submit` | `@login` | `throttle:5,15` |
| GET | `/institute/register` | `institute.register` | `InstituteUserRegisterController` | |
| POST | `/institute/register` | `institute.register.submit` | | `throttle:10,15` |
| GET | `/register` | `owner.register` | `OwnerRegisterController` | |
| POST | `/register` | `owner.register.submit` | | `throttle:10,15` |
| POST | `/logout` | `logout` | `LogoutController` | |

## Public

| Method | URI | Name | Controller | Notes |
|--------|-----|------|------------|-------|
| GET | `/verify/certificate/{certificate_number}` | `verify.certificate` | `VerifyCertificateController@show` | no auth; `[A-Za-z0-9\-]+`; 404 if unknown |

## Shared authenticated area

Middleware: `auth:platform_admin,institute_user,web` (+ `tenant` where noted).

| Method | URI | Name | Notes |
|--------|-----|------|-------|
| GET | `/` | `dashboard` | `DashboardController`, needs `tenant` |
| GET/PUT | `/account/preferences` | `account.preferences(.update)` | `UserPreferenceController` |
| POST | `/account/preferences/theme` | `account.preferences.theme` | |
| GET | `/workspace` | `workspace.picker` | `auth:web` only |
| POST | `/workspace/switch/{institutionId}` | `workspace.switch` | `auth:web` only |

## Institute staff / workspace modules

Middleware: `auth:institute_user,web` + `tenant`. Permission-gated per row.

### students
| Method | URI | Name | Permission |
|--------|-----|------|------------|
| GET | `/students` | `students.index` | `students.view` |
| GET | `/students/create` | `students.create` | `students.manage` |
| POST | `/students` | `students.store` | `students.manage` |
| GET | `/students/{student}` | `students.show` | `students.view` |
| POST | `/students/{student}/enroll` | `students.enroll` | `students.manage` |
| GET | `/students/{student}/edit` | `students.edit` | `students.manage` |
| PUT | `/students/{student}` | `students.update` | `students.manage` |
| POST | `/students/{student}/photo` | `students.photo` | `students.manage` |
| DELETE | `/students/{student}` | `students.destroy` | `students.manage` |

### batches
| Method | URI | Name | Permission |
|--------|-----|------|------------|
| GET | `/batches` | `batches.index` | `batches.view` |
| POST | `/batches` | `batches.store` | `batches.manage` |
| PUT | `/batches/{batch}` | `batches.update` | `batches.manage` |
| DELETE | `/batches/{batch}` | `batches.destroy` | `batches.manage` |

### sync (offline finance)
| Method | URI | Name | Permission |
|--------|-----|------|------------|
| GET | `/sync` | `sync.index` | `finance.view` |
| POST | `/sync/upload` | `sync.upload` | — |
| POST | `/sync/{queue}/approve` | `sync.approve` | `finance.manage` |
| POST | `/sync/{queue}/reject` | `sync.reject` | `finance.manage` |

### misc
| Method | URI | Name | Permission |
|--------|-----|------|------------|
| GET | `/courses` | `courses.index` | `courses.view` |
| GET | `/certificates` | `certificates.index` | `certificates.view` |
| GET | `/verify` | `verify` | — |
| GET | `/settings` | `settings.index` | — |
| GET | `/settings/account` | `settings.account` | — |
| GET | `/settings/appearance` | `settings.appearance` | `settings.manage` |
| PUT | `/settings/appearance` | `settings.appearance.update` | `settings.manage` |
| GET | `/staff/invite` | `staff.invite` | `staff.manage` |
| POST | `/staff/invite` | `staff.invite.store` | `staff.manage` |

## Platform admin console (`admin.*`)

Middleware: `auth:platform_admin`. Prefix `admin`.

### institutes
| Method | URI | Name |
|--------|-----|------|
| GET | `/admin/institutes` | `admin.institutes.index` |
| GET | `/admin/institutes/bin` | `admin.institutes.bin` (recycle bin) |
| GET | `/admin/institutes/{institute}` | `admin.institutes.show` |
| GET | `/admin/institutes/{institute}/edit` | `admin.institutes.edit` |
| PUT | `/admin/institutes/{institute}` | `admin.institutes.update` |
| POST | `/admin/institutes/{institute}/action` | `admin.institutes.action` (approve/suspend/...) |
| POST | `/admin/institutes/{institute}/restore` | `admin.institutes.restore` |
| DELETE | `/admin/institutes/{institute}/force-delete` | `admin.institutes.force-delete` |

### courses
| Method | URI | Name |
|--------|-----|------|
| GET | `/admin/courses` | `admin.courses.index` |
| GET | `/admin/courses/assignment` | `admin.courses.assignment` |
| GET | `/admin/courses/requests` | `admin.courses.requests` |
| POST | `/admin/courses/requests/{courseRequest}/action` | `admin.courses.requests.action` |
| POST | `/admin/courses/requests-columns` | `admin.courses.requests-columns` |
| GET | `/admin/courses/subjects` | `admin.courses.subjects` |
| POST | `/admin/courses/subjects-columns` | `admin.courses.subjects-columns` |

### students / certificates
| Method | URI | Name |
|--------|-----|------|
| GET | `/admin/students` | `admin.students.index` |
| GET | `/admin/students/{student}` | `admin.students.show` |
| GET | `/admin/certificates` | `admin.certificates.index` | issued (active/revoked) |
| POST | `/admin/certificates/columns` | `admin.certificates.columns` |
| GET | `/admin/certificates/requests` | `admin.certificates.requests` | requests (pending/rejected) |
| POST | `/admin/certificates/requests-columns` | `admin.certificates.requests-columns` |
| GET | `/admin/certificates/{certificate}` | `admin.certificates.show` | printable certificate + QR |
| GET | `/admin/certificates/{certificate}/qr` | `admin.certificates.qr` | download QR as SVG attachment |
| POST | `/admin/certificates/{certificate}/action` | `admin.certificates.action` |
| POST | `/admin/certificates/{certificate}/restore` | `admin.certificates.restore` | `withTrashed` |
| DELETE | `/admin/certificates/{certificate}` | `admin.certificates.destroy` | soft delete |
| DELETE | `/admin/certificates/{certificate}/force-delete` | `admin.certificates.force-delete` | `withTrashed`; password required |

### notifications
| Method | URI | Name |
|--------|-----|------|
| GET | `/admin/notifications` | `admin.notifications.index` |
| POST | `/admin/notifications/read-all` | `admin.notifications.read-all` |
| POST | `/admin/notifications/{notification}/read` | `admin.notifications.read` |

### settings
| Method | URI | Name |
|--------|-----|------|
| GET | `/admin/settings` | `admin.settings.index` |
| POST | `/admin/settings/password` | `admin.settings.password` |
| POST | `/admin/settings/staff/{instituteUser}/action` | `admin.settings.staff-action` |

## Fortify features (`routes/auth.php`)

Group middleware: `web` + `fortifyguard`.

| Area | URI | Guard | Notes |
|------|-----|-------|-------|
| Forgot password | `/forgot-password` GET/POST | guest | `password.request/email` |
| Reset | `/reset-password/{token}` GET, `/reset-password` POST | guest | `password.reset/update` |
| 2FA challenge | `/two-factor-challenge` GET/POST | guest | `two-factor.login(.store)` |
| Verify email | `/email/verify`, `/email/verify/{id}/{hash}`, POST `/email/verification-notification` | `platform_admin,institute_user` | |
| Security | `/account/security` | `institute_user,web` + `tenant` | 2FA enable/confirm/disable, QR, recovery codes, session revoke |
| Security | `/admin/security` | `platform_admin` | same features |

> Guests on security routes use `guest:institute_user,platform_admin`. All
> 2FA mutation routes additionally require `verified`.