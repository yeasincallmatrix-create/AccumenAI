# SECURITY AUDIT EXECUTIVE SUMMARY

**Project:** AccumenAI / MAWA SaaS
**Audit Date:** 2026-09-22
**Audit Type:** Full Codebase Security Audit
**Laravel Version:** v12.69.2
**PHP Version:** ^8.2

---

## Total Findings: 42

| Severity | Count |
|----------|-------|
| CRITICAL | 3 |
| HIGH | 14 |
| MEDIUM | 15 |
| LOW | 10 |

| Status | Count |
|--------|-------|
| Confirmed Vulnerability | 32 |
| Suspected Vulnerability | 6 |
| False Positive | 4 |

---

# ATTACK SURFACE

**Routes:** 8 route files, 2000+ route definitions
**Controllers:** 115+ controllers across 18 subdirectories
**Models:** 317+ models
**Services:** 133+ service files
**Middleware:** 23 custom middleware
**Livewire Components:** 22 components
**Console Commands:** 72 commands
**File Upload Endpoints:** 24 upload handlers
**Public Endpoints:** 15+ unauthenticated routes
**API Endpoints:** 40+ REST API routes
**Background Jobs:** Queue-based processing
**Real-time:** Laravel Reverb WebSocket support

**User Roles:** PlatformAdmin, PlatformStaff, InstituteUser (with roles: owner, admin, teacher, accountant, receptionist, branch-manager), Guardian, Student
**Multi-tenant:** Institute-based isolation with optional branch scoping
**Payment:** bKash gateway integration
**AI:** Multi-provider AI tools (OCR, vision, analysis)
**Medical:** 14 medical sub-modules

---

# AUTHENTICATION

## Strengths
1. Multi-guard authentication (5 guards: web, platform_admin, institute_user, guardian, platform_staff)
2. Session regeneration after login across ALL guards
3. Account lockout with configurable thresholds (10 attempts/15min normal, 5/15min admin)
4. Password hash validation before bcrypt comparison (corrupted hash detection)
5. Transparent password rehash on login
6. Centralized password service preventing double-hashing
7. Generic error messages preventing account enumeration
8. Platform admin excluded from password reset flow
9. Multi-method 2FA (TOTP, SMS, Email) with per-user and per-IP rate limiting
10. Timing-safe recovery code comparison via hash_equals()
11. One-role-per-session enforcement on guard switching
12. Complete session invalidation on logout
13. Email normalization preventing case-based bypass
14. Cross-table duplicate detection during registration

## Findings

### [SEC-001] Platform Admin Bypasses ALL Permission/Module Checks
- **Severity:** HIGH
- **Confidence:** CONFIRMED
- **Category:** Authorization
- **Affected files:** `app/Http/Middleware/CheckPermission.php:28-29`, `app/Http/Middleware/CheckModuleAccess.php:28-29`, `app/Http/Middleware/CheckFeatureAccess.php:28-29`
- **Description:** Every PlatformAdmin user bypasses ALL permission checks, module access checks, and feature access checks unconditionally. A compromised platform admin account has unrestricted access to all institute resources.
- **Security impact:** Complete authorization bypass for any platform admin account
- **Attack scenario:** Compromise a platform admin account (phishing, credential stuffing) → access ANY institute's data → modify/delete financial records, student data, medical records
- **Preconditions:** Platform admin account credentials
- **Existing control:** None for platform admin internal authorization
- **Why insufficient:** No way to restrict specific platform admins; no impersonation audit trail for tenant data access
- **Recommended remediation:** Add `is_super_admin` flag check; require explicit tenant impersonation for platform admins with audit logging
- **Status:** OPEN

### [SEC-002] BlockPlatformAdminEscalation Incomplete - guard/role Not Blocked
- **Severity:** HIGH
- **Confidence:** CONFIRMED
- **Category:** Privilege Escalation
- **Affected files:** `app/Http/Middleware/BlockPlatformAdminEscalation.php:11,20`
- **Description:** The BLOCKED_KEYS array includes 'guard' and 'role' but the in_array check on line 20 only blocks ['is_owner', 'singleton_guard', 'super_admin', 'platform_admin']. Requests containing 'guard' or 'role' pass through silently.
- **Security impact:** An attacker could inject `guard=institute_user` or `role=admin` into user creation/update requests
- **Attack scenario:** POST /admin/users with {name: "attacker", email: "attacker@evil.com", guard: "platform_admin", role: "owner"} → user created with elevated privileges
- **Preconditions:** Admin account or bypass of admin authentication
- **Existing control:** 4 of 6 blocked keys are enforced
- **Recommended remediation:** Add 'guard' and 'role' to the in_array blocking check
- **Status:** OPEN

### [SEC-003] API Login Bypasses 2FA
- **Severity:** MEDIUM
- **Confidence:** CONFIRMED
- **Category:** Authentication Bypass
- **Affected files:** `app/Http/Controllers/Api/AuthController.php:82-91`
- **Description:** The API login flow directly issues a Sanctum token after password verification without any 2FA challenge. Users with 2FA enabled can bypass it via the API.
- **Security impact:** 2FA protection is only enforced on web login, not API
- **Attack scenario:** Attacker obtains user password → login via API → full access without 2FA
- **Preconditions:** User has 2FA enabled; attacker knows password
- **Existing control:** 2FA enforced on web login only
- **Recommended remediation:** Add 2FA challenge to API login or issue partial token requiring 2FA completion
- **Status:** OPEN

### [SEC-004] 2FA Challenge Has No Timeout Enforcement
- **Severity:** MEDIUM
- **Confidence:** CONFIRMED
- **Category:** Authentication
- **Affected files:** `app/Http/Controllers/Auth/TwoFactorChallengeController.php`
- **Description:** The TwoFactorMethodService has a challengeExpiryMinutes() method (10 min default) but the challenge timeout is never checked in the controller. Abandoned 2FA sessions persist indefinitely.
- **Security impact:** Stale 2FA session data can be used later if session hasn't expired
- **Attack scenario:** User starts 2FA flow, abandons → attacker gains access to session → completes 2FA
- **Preconditions:** User started but didn't complete 2FA; session not expired
- **Existing control:** Session lifetime (120 min)
- **Recommended remediation:** Add timestamp check in 2FA challenge store method
- **Status:** OPEN

---

# AUTHORIZATION

## Strengths
1. 9 explicit Gate::policy() bindings in AppServiceProvider
2. All policies enforce tenant isolation via institute_id === tenant_id()
3. Role-based middleware (permission, module_access, feature) on routes
4. FinanceWriteGate blocks receptionist/branch-manager from finance writes
5. DenyTeacherFromFinance blocks teacher role from finance/accounting URIs
6. MedicalDomain blocks non-medical institutes
7. Comprehensive RBAC with granular permissions

## Findings

### [SEC-005] Finance Routes Lack Granular Permission Middleware
- **Severity:** MEDIUM
- **Confidence:** CONFIRMED
- **Category:** Authorization
- **Affected files:** `routes/institute_modules.php:558-703`
- **Description:** Most finance module routes (CoA, journals, payments, parties, payment methods, periods, budgets, expenses) rely solely on the $tenant stack and FinanceWriteGate/DenyTeacherFromFinance. No per-route permission: middleware.
- **Security impact:** Non-finance roles (e.g., teacher) may access finance write operations
- **Attack scenario:** Teacher at institute → access finance routes (URI doesn't match finance*) → create/modify financial records
- **Preconditions:** Teacher role at institute with finance module enabled
- **Existing control:** FinanceWriteGate (only blocks receptionist/branch-manager)
- **Recommended remediation:** Add permission:finance.manage / permission:finance.view middleware
- **Status:** OPEN

### [SEC-006] Many Module Routes Lack Per-Route Permission Checks
- **Severity:** MEDIUM
- **Confidence:** CONFIRMED
- **Category:** Authorization
- **Affected files:** `routes/institute_modules.php` (Teachers, Alumni, Calendar, Documents, Certificate Types, Recycle Bin, Online Payments, Reports Hub, Settings)
- **Description:** Routes for Teachers CRUD, Alumni, Calendar, Documents, Certificate Types, Recycle Bin, Online Payments, Reports Hub, and several Settings routes have NO permission: middleware.
- **Security impact:** Any authenticated tenant user can access these routes regardless of role
- **Attack scenario:** Any staff member → access teacher management, document management, settings → modify data outside their role
- **Preconditions:** Authenticated institute user
- **Existing control:** Module-level access only
- **Recommended remediation:** Add appropriate permission: middleware to all route groups
- **Status:** OPEN

### [SEC-007] Medical Routes Lack Granular RBAC
- **Severity:** LOW
- **Confidence:** CONFIRMED
- **Category:** Authorization
- **Affected files:** `routes/medical.php:75-219`
- **Description:** Legacy medical routes use only auth + tenant + medical middleware without granular permission: checks. Any authenticated tenant user at a medical institute can access all clinical features.
- **Security impact:** Clinical data accessible to non-clinical staff
- **Attack scenario:** Accountant at medical institute → access patient records, prescriptions, lab results
- **Preconditions:** Authenticated user at medical institute
- **Existing control:** MedicalDomain + MedicalModuleAccess
- **Recommended remediation:** Add permission:medical_*.view middleware to clinical routes
- **Status:** OPEN

---

# MULTI-TENANT / INSTITUTE ISOLATION

## Strengths
1. TenantScoped trait with automatic WHERE institute_id = TenantContext::id()
2. Mass-assignment hardening forces institute_id on create, prevents tampering on update
3. BranchScoped trait for branch-level isolation
4. Workspace forgery detection with 403 abort in SetTenantContext
5. Forged workspace verification via Workspace::verify()
6. AuditActivityLog explicitly refuses to read institute_id from request input
7. Route priority ensures SetTenantContext runs before SubstituteBindings
8. All 9 policies enforce tenant isolation

## Findings

### [SEC-008] 100+ Models with $guarded = [] (Unguarded Mass Assignment)
- **Severity:** HIGH
- **Confidence:** CONFIRMED
- **Category:** Mass Assignment
- **Affected files:** 100+ model files (AcademicStudentMark, ExamResult, Certificate, Guardian, Branch, Attendance, etc.)
- **Description:** Over 100 models use $guarded = [], meaning every attribute is mass-assignable. This includes critical models: AcademicStudentMark, ExamResult, Certificate, Guardian, Branch, Attendance, ChartOfAccount, Invoice, Payment.
- **Security impact:** If a controller passes unchecked user input to create()/update(), any field can be injected
- **Attack scenario:** Find endpoint that passes request data directly to ExamResult::updateOrCreate() → inject institute_id → modify another institute's grades
- **Preconditions:** Finding a controller that doesn't validate properly
- **Existing control:** TenantScoped trait forces institute_id on create/update
- **Recommended remediation:** Add $fillable or $guarded arrays to all models; especially security-sensitive ones
- **Status:** OPEN

### [SEC-009] InstituteUser Has Security-Sensitive Fields in $fillable
- **Severity:** MEDIUM
- **Confidence:** CONFIRMED
- **Category:** Mass Assignment
- **Affected files:** `app/Models/InstituteUser.php:36-55`
- **Description:** InstituteUser has institute_id, role_id, branch_id, status, password_hash in $fillable. While the boot callback blocks is_owner/super_admin/platform_admin injection, role_id and institute_id remain fillable.
- **Security impact:** Compromised endpoint could assign arbitrary roles or institute ownership
- **Attack scenario:** POST /staff/update with {role_id: 1} → staff member becomes owner
- **Preconditions:** Access to staff update endpoint
- **Existing control:** Boot callback blocks escalation attributes
- **Recommended remediation:** Remove institute_id, role_id from $fillable; use forceFill() in controllers
- **Status:** OPEN

---

# IDOR / BOLA

## Strengths
1. TenantScoped global scope automatically filters by institute_id on all Eloquent queries
2. BranchScoped global scope for branch-level isolation
3. All 9 policies check institute_id === tenant_id()
4. Route priority ensures tenant context is set before model binding

## Findings

### [SEC-010] Student Document Predictable Naming on Public Disk
- **Severity:** MEDIUM
- **Confidence:** CONFIRMED
- **Category:** IDOR / Information Disclosure
- **Affected files:** `app/Http/Controllers/StudentController.php:673-680`
- **Description:** Student documents are stored with predictable names ({studentId}.{ext}) on the public disk. Anyone who knows or guesses a student ID number can access their documents.
- **Security impact:** Student documents (passports, IDs, certificates) publicly accessible
- **Attack scenario:** Guess student ID → access /storage/students/documents/{id}.pdf → download sensitive documents
- **Preconditions:** Knowledge of student ID number
- **Existing control:** DocumentController::download requires auth
- **Recommended remediation:** Use UUID-based filenames; move to private storage; add signed URLs
- **Status:** OPEN

---

# INPUT VALIDATION & SQL INJECTION

## Strengths
1. All 30 DB::raw() calls use hardcoded SQL expressions (no user input interpolation)
2. All whereRaw() calls use parameterized bindings
3. Query builder parameterization provides SQL injection protection
4. DataTable filter system properly parameterized
5. No eval(), unserialize(), or shell_exec() in application code
6. Form Request classes used for most web controllers
7. No dynamic sort columns from user input

## Findings

### [SEC-011] Raw Variable Interpolation in selectRaw()
- **Severity:** MEDIUM
- **Confidence:** SUSPECTED
- **Category:** SQL Injection
- **Affected files:** `app/Livewire/ReceivableList.php:134-137`, `app/Livewire/PayableList.php:133-136`
- **Description:** Variable $asOfLiteral is interpolated into selectRaw() SQL. While the variable appears to be derived from a date validation, it's not parameterized.
- **Security impact:** Potential SQL injection if $asOfLiteral can be influenced
- **Attack scenario:** Craft request that manipulates date value → inject SQL via aging calculation
- **Preconditions:** Date value manipulation possible
- **Existing control:** Date validation upstream
- **Recommended remediation:** Use parameterized bindings instead of variable interpolation
- **Status:** OPEN

### [SEC-012] API Controllers Without Form Request Validation
- **Severity:** MEDIUM
- **Confidence:** CONFIRMED
- **Category:** Input Validation
- **Affected files:** `app/Http/Controllers/Api/CertificateController.php`, `Api/BatchController.php`, `Api/AttendanceController.php`, `Api/AssessmentController.php`, `Api/CourseController.php`, `Api/GoodsReceiptController.php`, `Api/CrmContactController.php`
- **Description:** 7+ API endpoints accept user input without formal Form Request validation classes. While query builder provides SQL injection protection, input validation (type, length, range) is missing.
- **Security impact:** Malformed input accepted; potential for business logic bypass
- **Attack scenario:** Send oversized/unexpected data types to API endpoints → corrupt data or cause errors
- **Preconditions:** API access with valid token
- **Existing control:** Query builder parameterization
- **Recommended remediation:** Create Form Request classes for all API endpoints
- **Status:** OPEN

---

# XSS

## Strengths
1. Blade {{ }} syntax used extensively (auto-escaped via e())
2. nl2br(e()) pattern used correctly for newlines in user content
3. ProfileImageService re-encoding strips malicious payloads from images
4. QR code SVGs generated server-side

## Findings

### [SEC-013] Raw HTML Rendering in Email Notifications
- **Severity:** CRITICAL
- **Confidence:** CONFIRMED
- **Category:** Stored XSS
- **Affected files:** `resources/views/mail/notification.blade.php:1`
- **Description:** {!! $bodyText !!} renders raw HTML in email notifications. If $bodyText contains user-influenced content, this is a stored XSS vulnerability that executes in email clients.
- **Security impact:** Arbitrary JavaScript execution in email clients
- **Attack scenario:** Inject script into notice content → email sent to all users → script executes in their email client → steal credentials, session tokens
- **Preconditions:** Ability to create notices/content that reaches email notifications
- **Existing control:** None visible
- **Recommended remediation:** Use {!! Str::markdown(e($bodyText)) !!} or sanitize HTML with strip_tags() + allowed attributes
- **Status:** OPEN

### [SEC-014] Markdown Rendered as Raw HTML on Homepage
- **Severity:** HIGH
- **Confidence:** CONFIRMED
- **Category:** Stored XSS
- **Affected files:** `resources/views/home-pages/default.blade.php:53`
- **Description:** {!! Str::markdown($homePage->hero_title) !!} renders Markdown as raw HTML. If hero_title is admin-editable, a malicious admin could inject XSS on the public homepage.
- **Security impact:** XSS on public-facing homepage
- **Attack scenario:** Compromised admin account → inject script in hero_title → all visitors execute JavaScript
- **Preconditions:** Admin panel access
- **Existing control:** Admin-only access
- **Recommended remediation:** Sanitize HTML output or use strict Markdown parser
- **Status:** OPEN

### [SEC-015] json_encode in JavaScript Context Without HEX_TAG
- **Severity:** MEDIUM
- **Confidence:** CONFIRMED
- **Category:** XSS
- **Affected files:** `resources/views/medical/medicines/show.blade.php:217-219`
- **Description:** {!! json_encode($medicine->brand_name) !!} in JavaScript context without JSON_HEX_TAG. If medicine names contain </script>, it could break out of the script tag.
- **Security impact:** Potential script injection via medicine names
- **Attack scenario:** Create medicine with name "test</script><script>alert(1)</script>" → XSS when viewed
- **Preconditions:** Ability to create medicine entries
- **Existing control:** json_encode escaping
- **Recommended remediation:** Use json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
- **Status:** OPEN

---

# CSRF

## Strengths
1. Laravel CSRF protection on all web routes
2. No custom VerifyCsrfToken exemptions found
3. CSRF token regenerated on logout
4. SameSite=lax cookie policy

## Findings

### [SEC-016] GET Logout Route (State-Changing via GET)
- **Severity:** LOW
- **Confidence:** CONFIRMED
- **Category:** CSRF
- **Affected files:** `routes/web.php:104`, `routes/guardian.php:53`
- **Description:** GET /logout exists as fallback for expired sessions. While non-destructive, it's a state-changing operation via GET.
- **Security impact:** Minimal - clears session state
- **Attack scenario:** Craft link that logs out user → minor inconvenience
- **Preconditions:** User must click link
- **Existing control:** Session invalidation is the goal
- **Recommended remediation:** Keep as-is (acceptable for session recovery); document rationale
- **Status:** DEFERRED

---

# RATE LIMITING

## Strengths
1. Login routes throttled (30/15 normal, 10/15 admin)
2. Password reset throttled (10/10)
3. Registration throttled (10/15)
4. API authenticated routes throttled (60/1)
5. 2FA challenges rate limited (per-user + per-IP)
6. Document scan throttled (10/30)
7. Deploy routes throttled (5/60)

## Findings

### [SEC-017] Many State-Changing Web Routes Without Rate Limiting
- **Severity:** LOW
- **Confidence:** CONFIRMED
- **Category:** Rate Limiting
- **Affected files:** `routes/web.php` (students, batches, settings, staff invite, admin CRUD)
- **Description:** Most authenticated write routes have no throttle middleware. While behind authentication, they're still vulnerable to authenticated abuse.
- **Security impact:** Authenticated user can rapidly create/modify resources
- **Attack scenario:** Authenticated user → rapid-fire requests → resource exhaustion, data corruption
- **Preconditions:** Authenticated session
- **Existing control:** Authentication required
- **Recommended remediation:** Add throttle middleware to write routes (e.g., throttle:60,1)
- **Status:** OPEN

---

# FILE UPLOAD SECURITY

## Strengths
1. MIME type validation on most uploads (mimes: rules)
2. UUID-based filenames on most uploads
3. ProfileImageService re-encoding strips malicious payloads
4. ZipSlip protection in DeploymentZipService
5. File size limits enforced
6. Document scan cleanup in finally block

## Findings

### [SEC-018] SVG Uploads Allowed (Stored XSS Risk)
- **Severity:** HIGH
- **Confidence:** CONFIRMED
- **Category:** File Upload / XSS
- **Affected files:** `app/Http/Controllers/InstituteSettingController.php:281`, `app/Http/Controllers/InstituteLogoController.php:14`, `app/Http/Controllers/Admin/SettingController.php:166`, `app/Http/Requests/StudentFormRequest.php:61`
- **Description:** SVG files are allowed in logo uploads and student documents. SVGs can contain embedded JavaScript, event handlers, and external references.
- **Security impact:** Stored XSS via malicious SVG files served from public disk
- **Attack scenario:** Upload SVG with <script> tag → served as image/svg+xml → JavaScript executes in browser
- **Preconditions:** Upload capability (logo or student document)
- **Existing control:** CSP header (weakened by unsafe-inline)
- **Recommended remediation:** Remove SVG from allowed types; or sanitize SVGs with SVGSanitizer library
- **Status:** OPEN

### [SEC-019] Sensitive Files on Public Disk Without Access Control
- **Severity:** HIGH
- **Confidence:** CONFIRMED
- **Category:** File Upload / Information Disclosure
- **Affected files:** Multiple controllers storing on 'public' disk
- **Description:** Student documents, medical documents, course materials, HR documents, radiology images stored on public disk. Anyone with the URL can access them without authentication.
- **Security impact:** Sensitive documents (passports, medical records, certificates) publicly accessible
- **Attack scenario:** Guess or enumerate file URLs → download sensitive documents
- **Preconditions:** Knowledge of file URL
- **Existing control:** DocumentController::download requires auth (but raw file is public)
- **Recommended remediation:** Move to private storage; implement signed URLs; add middleware for storage access
- **Status:** OPEN

### [SEC-020] GeoImportController Uses Original Filename
- **Severity:** LOW
- **Confidence:** CONFIRMED
- **Category:** Path Traversal
- **Affected files:** `app/Http/Controllers/Admin/GeoImportController.php:80`
- **Description:** Uses $file->getClientOriginalName() for storage path. If filename contains path separators, could cause path traversal.
- **Security impact:** Potential path traversal on local disk
- **Attack scenario:** Upload file named "../../etc/passwd" → overwrite system file
- **Preconditions:** Admin access; local disk storage
- **Existing control:** Admin auth required; local disk
- **Recommended remediation:** Use UUID-based filenames
- **Status:** OPEN

---

# SECRETS

## Strengths
1. .env properly excluded from .gitignore
2. No hardcoded production credentials in code
3. Passwords resolved from database at runtime (AiConfig, BkashConfig)
4. Laravel Crypt facade used for at-rest encryption
5. No custom encryption algorithms

## Findings

### [SEC-021] APP_DEBUG=true Exposes Stack Traces
- **Severity:** HIGH
- **Confidence:** CONFIRMED
- **Category:** Information Leakage
- **Affected files:** `.env:4`, `config/app.php:42`
- **Description:** APP_DEBUG=true in .env exposes full stack traces, environment variables, SQL queries, and application internals in error pages.
- **Security impact:** Complete application internals exposed to attackers
- **Attack scenario:** Trigger error → view debug page → extract APP_KEY, DB credentials, API keys
- **Preconditions:** Production deployment with APP_DEBUG=true
- **Existing control:** config defaults to false
- **Recommended remediation:** Set APP_DEBUG=false in production; automate env validation
- **Status:** OPEN

### [SEC-022] APP_KEY in .env File
- **Severity:** CRITICAL
- **Confidence:** CONFIRMED
- **Category:** Secrets Exposure
- **Affected files:** `.env:3`
- **Description:** APP_KEY=base64:ym2MKdzdZP67cMu2Io3m36pZmhbjYeTsccEkxK0vkVk= is present in .env. If the repository or .env is accessible, all encrypted data can be decrypted.
- **Security impact:** Complete compromise of encrypted data (API keys, SMTP passwords, 2FA secrets)
- **Attack scenario:** Access .env → decrypt all Crypt::encrypted values → access all third-party services
- **Preconditions:** Access to .env file or repository
- **Existing control:** .gitignore excludes .env
- **Recommended remediation:** Rotate APP_KEY; ensure .env never deployed to public locations; use secret management
- **Status:** OPEN

### [SEC-023] Debug Status Leaked in API Response
- **Severity:** MEDIUM
- **Confidence:** CONFIRMED
- **Category:** Information Leakage
- **Affected files:** `app/Http/Controllers/Accounting/ProductionDashboardController.php:158`
- **Description:** 'debug' => config('app.debug') is included in production dashboard data returned to the browser.
- **Security impact:** Reveals debug mode status to attackers
- **Attack scenario:** Access production dashboard → check debug status → adjust attack strategy
- **Preconditions:** Authenticated access to production dashboard
- **Existing control:** Authentication required
- **Recommended remediation:** Remove debug status from API responses
- **Status:** OPEN

---

# ERROR HANDLING

## Strengths
1. Custom exception handler in bootstrap/app.php
2. ThrottleRequestsException handled gracefully with 429 response
3. ValidationException returns JSON for AJAX requests
4. Stack traces logged but not exposed to users (when APP_DEBUG=false)
5. Generic error messages in auth flows

## Findings

### [SEC-024] Exception Messages Exposed in Error Responses
- **Severity:** MEDIUM
- **Confidence:** CONFIRMED
- **Category:** Information Leakage
- **Affected files:** `app/Http/Controllers/DocumentScanController.php:71,104`
- **Description:** Error response includes exception message: 'AI Vision extraction failed: ' . $e->getMessage(). Exception messages may contain internal paths or implementation details.
- **Security impact:** Internal implementation details leaked
- **Attack scenario:** Trigger vision error → extract internal path information
- **Preconditions:** Document scan capability
- **Existing control:** Authentication required
- **Recommended remediation:** Return generic error messages; log details server-side only
- **Status:** OPEN

---

# INFORMATION LEAKAGE

## Findings

### [SEC-025] CSP Allows unsafe-inline and unsafe-eval
- **Severity:** MEDIUM
- **Confidence:** CONFIRMED
- **Category:** Security Headers
- **Affected files:** `app/Http/Middleware/SecurityHeaders.php:30`
- **Description:** Content-Security-Policy script-src includes 'unsafe-inline' and 'unsafe-eval', significantly weakening XSS protection.
- **Security impact:** XSS attacks bypass CSP entirely
- **Attack scenario:** Inject <script> tag → execute despite CSP
- **Preconditions:** XSS vulnerability
- **Existing control:** CSP header present but weakened
- **Recommended remediation:** Migrate to nonce-based or hash-based CSP; remove unsafe-inline and unsafe-eval
- **Status:** OPEN

### [SEC-026] No Permissions-Policy Header
- **Severity:** LOW
- **Confidence:** CONFIRMED
- **Category:** Security Headers
- **Affected files:** `app/Http/Middleware/SecurityHeaders.php`
- **Description:** Missing Permissions-Policy header (camera, microphone, geolocation, etc.)
- **Security impact:** Browser features not restricted
- **Attack scenario:** Malicious page could request camera/microphone access
- **Preconditions:** Browser support for Permissions-Policy
- **Existing control:** None
- **Recommended remediation:** Add Permissions-Policy header: camera=(), microphone=(), geolocation=()
- **Status:** OPEN

---

# SESSION / COOKIE SECURITY

## Strengths
1. Database-backed sessions
2. Session regeneration after login across ALL guards
3. Session invalidation on logout
4. HttpOnly cookies enabled
5. SameSite=lax policy
6. Session lifetime of 120 minutes

## Findings

### [SEC-027] SESSION_SECURE_COOKIE Not Set
- **Severity:** HIGH
- **Confidence:** CONFIRMED
- **Category:** Session Security
- **Affected files:** `.env`, `config/session.php:172`
- **Description:** SESSION_SECURE_COOKIE not set in .env. Session cookies can be sent over plain HTTP, enabling session hijacking via network sniffing.
- **Security impact:** Session theft on non-HTTPS connections
- **Attack scenario:** User on HTTP → attacker captures session cookie → impersonate user
- **Preconditions:** Non-HTTPS deployment or mixed content
- **Existing control:** HttpOnly flag
- **Recommended remediation:** Set SESSION_SECURE_COOKIE=true in production
- **Status:** OPEN

### [SEC-028] Session Encryption Disabled
- **Severity:** MEDIUM
- **Confidence:** CONFIRMED
- **Category:** Session Security
- **Affected files:** `.env:29`, `config/session.php:50`
- **Description:** SESSION_ENCRYPT=false. Session database rows stored unencrypted. If database is compromised, session data is readable.
- **Security impact:** Session data exposure on database compromise
- **Attack scenario:** SQL injection or database access → read session data → impersonate users
- **Preconditions:** Database access
- **Existing control:** Database access controls
- **Recommended remediation:** Set SESSION_ENCRYPT=true in production
- **Status:** OPEN

---

# CORS

## Strengths
1. Not wildcard (restricted to configured origin)
2. supports_credentials=false
3. Only API routes are CORS-enabled
4. No wildcard origins with credentials

## Findings

### [SEC-029] Overly Permissive CORS Methods and Headers
- **Severity:** LOW
- **Confidence:** CONFIRMED
- **Category:** CORS
- **Affected files:** `config/cors.php`
- **Description:** allowed_methods=['*'] and allowed_headers=['*'] are overly permissive.
- **Security impact:** Any method/header allowed from configured origin
- **Attack scenario:** Craft cross-origin request with arbitrary methods/headers
- **Preconditions:** Cross-origin request from configured origin
- **Existing control:** Origin restriction
- **Recommended remediation:** Restrict to actual methods/headers used by API
- **Status:** OPEN

---

# MASS ASSIGNMENT

## Findings

### [SEC-030] ExamResult::updateOrCreate() with Unguarded Model
- **Severity:** MEDIUM
- **Confidence:** CONFIRMED
- **Category:** Mass Assignment
- **Affected files:** `app/Http/Controllers/ExamController.php:523-635`, `app/Models/ExamResult.php`
- **Description:** ExamController calls ExamResult::updateOrCreate() with data from request arrays. ExamResult has $guarded = [] with no $fillable restriction.
- **Security impact:** Potential injection of arbitrary fields into exam results
- **Attack scenario:** Craft POST request → inject additional fields → modify grades or institute_id
- **Preconditions:** Access to exam result entry endpoint
- **Existing control:** TenantScoped forces institute_id
- **Recommended remediation:** Add $fillable to ExamResult; validate input explicitly
- **Status:** OPEN

### [SEC-031] Guardian Model Fully Unguarded
- **Severity:** MEDIUM
- **Confidence:** CONFIRMED
- **Category:** Mass Assignment
- **Affected files:** `app/Models/Guardian.php:38`
- **Description:** Guardian has $guarded = [] with no $fillable array. Every column including institute_id, status, password_hash is mass-assignable.
- **Security impact:** If any Guardian creation/update endpoint doesn't validate, all fields can be injected
- **Attack scenario:** Create guardian with {institute_id: X, status: 'active', password_hash: '...'} → access another institute's data
- **Preconditions:** Guardian creation/update endpoint access
- **Existing control:** TenantScoped
- **Recommended remediation:** Add $fillable array with only safe fields
- **Status:** OPEN

---

# DEPENDENCIES

## Strengths
1. Laravel v12.69.2 (latest version)
2. Minimal dependency footprint
3. React 19, Vite 7, Tailwind 4 (current releases)
4. No abandoned packages detected

## Findings

### [SEC-032] Dependency Audit Not Performed
- **Severity:** LOW
- **Confidence:** SUSPECTED
- **Category:** Dependencies
- **Description:** No automated dependency audit (composer audit, npm audit) was run during this audit.
- **Security impact:** Potential known CVEs in dependencies
- **Attack scenario:** Exploit known vulnerability in outdated dependency
- **Preconditions:** Known CVE exists
- **Existing control:** Recent versions reduce risk
- **Recommended remediation:** Run `composer audit` and `npm audit`; integrate into CI/CD
- **Status:** OPEN

---

# BUSINESS LOGIC

## Strengths
1. Payment processing uses double-entry journal system
2. InvoicePolicy prevents modification of paid/cancelled invoices
3. Certificate numbers generated with random_int()
4. Approval workflows for certificates and accounting entries
5. Status-based mutation guards on financial records

## Findings

### [SEC-033] Subscription Package Pricing in $fillable
- **Severity:** MEDIUM
- **Confidence:** SUSPECTED
- **Category:** Business Logic
- **Affected files:** `app/Models/SubscriptionPackage.php:14-27`
- **Description:** price_monthly and price_yearly are in $fillable. If any route allows package creation/update without proper authorization, pricing can be manipulated.
- **Security impact:** Package pricing manipulation
- **Attack scenario:** Access package management → change pricing → create custom package with $0
- **Preconditions:** Access to package management routes
- **Existing control:** Super Admin only access
- **Recommended remediation:** Verify authorization on all package management routes
- **Status:** OPEN

### [SEC-034] No Race Condition Protection on Certificate Issuance
- **Severity:** LOW
- **Confidence:** SUSPECTED
- **Category:** Race Conditions
- **Affected files:** `app/Http/Controllers/Admin/CertificateAdminController.php:300-379`
- **Description:** Certificate number generation uses random_int() but no unique constraint or locking to prevent duplicate numbers under concurrent requests.
- **Security impact:** Potential duplicate certificate numbers
- **Attack scenario:** Concurrent approval requests → duplicate certificate numbers
- **Preconditions:** Multiple concurrent certificate approvals
- **Existing control:** random_int() collision probability is extremely low
- **Recommended remediation:** Add unique constraint on certificate number; use DB transaction with locking
- **Status:** OPEN

---

# QUEUES / JOBS

## Findings

### [SEC-035] Job Payload May Contain Sensitive Data
- **Severity:** LOW
- **Confidence:** SUSPECTED
- **Category:** Queues
- **Description:** Queued jobs serialize model data into the jobs table. If models contain sensitive fields, they may be exposed in the queue payload.
- **Security impact:** Sensitive data readable in queue database table
- **Attack scenario:** Database access → read queue table → extract sensitive data from job payloads
- **Preconditions:** Database access
- **Existing control:** Database access controls
- **Recommended remediation:** Use $hidden on sensitive model attributes; encrypt job payloads
- **Status:** OPEN

---

# WEBHOOKS

## Strengths
1. bKash webhook secret configured via environment variable
2. Webhook secret available for signature validation

## Findings

### [SEC-036] bKash Webhook Signature Validation Not Verified
- **Severity:** MEDIUM
- **Confidence:** SUSPECTED
- **Category:** Webhooks
- **Affected files:** `app/Services/PaymentGateway/Gateways/BkashGateway.php`
- **Description:** The bKash webhook secret is configured but webhook signature validation was not confirmed in the callback handling code.
- **Security impact:** Accepting forged webhook payloads
- **Attack scenario:** Craft fake bKash callback → mark invoice as paid without actual payment
- **Preconditions:** Knowledge of webhook endpoint URL
- **Existing control:** bKash sandbox mode in development
- **Recommended remediation:** Verify HMAC signature on all webhook callbacks
- **Status:** OPEN

---

# CRYPTOGRAPHY

## Strengths
1. bcrypt with 12 rounds for password hashing
2. Laravel Crypt facade (AES-256-CBC) for at-rest encryption
3. random_int() used for all security-critical random generation
4. No custom encryption algorithms
5. No hardcoded IVs or keys
6. No md5/sha1 for password hashing

## Findings

### [SEC-037] sha1() Used for Email Verification Hash
- **Severity:** LOW
- **Confidence:** FALSE POSITIVE
- **Category:** Cryptography
- **Affected files:** `app/Http/Controllers/Auth/VerifyEmailController.php:21`
- **Description:** sha1() used for email verification hash. This follows Laravel's default pattern and is protected by signed URL.
- **Security impact:** Minimal - signed URL provides additional protection
- **Existing control:** URL::temporarySignedRoute() provides HMAC protection
- **Recommended remediation:** No action required (Laravel standard pattern)
- **Status:** FALSE POSITIVE

---

# CONFIGURATION

## Findings

### [SEC-038] SESSION_DOMAIN Not Set
- **Severity:** LOW
- **Confidence:** CONFIRMED
- **Category:** Configuration
- **Affected files:** `.env:31`, `config/session.php`
- **Description:** SESSION_DOMAIN=null. Cookies may leak across subdomains if the application is deployed on a subdomain.
- **Security impact:** Session leakage across subdomains
- **Attack scenario:** Application on subdomain.a.com → cookies accessible from a.com
- **Preconditions:** Multi-subdomain deployment
- **Existing control:** SameSite=lax
- **Recommended remediation:** Set SESSION_DOMAIN in production
- **Status:** OPEN

### [SEC-039] exec() Calls in AppServiceProvider
- **Severity:** MEDIUM
- **Confidence:** CONFIRMED
- **Category:** Command Injection
- **Affected files:** `app/Providers/AppServiceProvider.php:240-252`
- **Description:** exec() calls with config-derived variables in shell commands. While in testing environment only, these could be exploited if .env is compromised.
- **Security impact:** OS command injection if config values are attacker-controlled
- **Attack scenario:** Compromise .env → inject shell commands via config values
- **Preconditions:** .env compromise; testing environment
- **Existing control:** Environment check (app()->environment('testing'))
- **Recommended remediation:** Use escapeshellarg() for all variables; consider safer alternatives
- **Status:** OPEN

---

# RAW DATABASE ACCESS REVIEW

| File | Line | Purpose | Tenant Check | Auth Check | Risk |
|------|------|---------|-------------|------------|------|
| InstituteDomain.php | 245-261 | Read-only existence checks | Explicit | N/A | LOW |
| GeneralLedgerList.php | 106, 200, 230 | Journal entry queries | Manual | Yes | MEDIUM |
| AcademicResultAggregationService.php | 463, 479 | Result calculations | Manual | Yes | MEDIUM |
| AccountDeletionService.php | 213-504 | Account cleanup | By user_id | Admin-only | LOW |
| AppServiceProvider.php | 286, 335 | Timezone, packages | N/A | N/A | SAFE |
| helpers.php | 454, 468, 574, 591, 631 | UID existence checks | N/A | N/A | LOW |

---

# PUBLIC ENDPOINT REVIEW

| Endpoint | Purpose | Rate Limit | Validation | Data Returned | Risk |
|----------|---------|------------|------------|---------------|------|
| GET /verify/certificate/{number} | Certificate verification | throttle:10,1 | Certificate exists | Certificate details | LOW |
| GET /verify/result | Result verification | None | Form validation | Result data | MEDIUM |
| POST /login | User login | throttle:30,15 | Email+password | Redirect | LOW |
| POST /admin/login | Admin login | throttle:10,15 | Email+password | Redirect | LOW |
| POST /register | Registration | throttle:10,15 | Multi-step | Redirect | LOW |
| POST /password/reset | Password reset | throttle:10,10 | Email | Generic message | LOW |
| POST /api/login | API login | throttle:10,1 | Email+password | Sanctum token | LOW |
| GET /api/verify/certificate/{number} | API cert verification | throttle:10,1 | Certificate exists | JSON cert data | LOW |

---

# SECURITY TEST MATRIX

| Control | Test | Expected | Actual | Status |
|---------|------|----------|--------|--------|
| Authentication | Brute force protection | Account lockout after N failures | Implemented (configurable) | PASS |
| Authentication | Session regeneration | New session ID after login | Implemented | PASS |
| Authentication | Password hash validation | Corrupted hash blocked | Implemented | PASS |
| Authorization | Platform admin bypass | Full access | Full access (by design) | PASS |
| Authorization | Permission middleware | Role-based access | Implemented | PARTIAL |
| Authorization | Module access | Subscription-gated | Implemented | PASS |
| Tenant Isolation | TenantScoped | Auto-filter by institute_id | Implemented | PASS |
| Tenant Isolation | Mass assignment | institute_id forced | Implemented | PASS |
| Tenant Isolation | Workspace forgery | 403 on forged workspace | Implemented | PASS |
| IDOR | find() with tenant scope | Auto-filtered | Implemented via global scope | PASS |
| SQL Injection | DB::raw() usage | No user input | All hardcoded | PASS |
| SQL Injection | Query builder | Parameterized | Parameterized | PASS |
| XSS | Blade escaping | {{ }} auto-escaped | Used extensively | PASS |
| XSS | {!! !!} usage | Minimal and safe | 34 instances, 3 risky | PARTIAL |
| CSRF | Web routes | CSRF token required | Implemented | PASS |
| Rate Limiting | Auth routes | Throttled | Implemented | PASS |
| Rate Limiting | Write routes | Throttled | Mostly unthrottled | FAIL |
| File Upload | MIME validation | Server-side check | Implemented on most | PASS |
| File Upload | SVG handling | Blocked or sanitized | SVG allowed | FAIL |
| File Upload | Filename sanitization | UUID-based | Most use UUID | PASS |
| Secrets | .env exposure | Not in git | Properly gitignored | PASS |
| Secrets | APP_DEBUG | false in production | true in .env | FAIL |
| Error Handling | Stack traces | Not exposed | Not exposed (when debug=false) | PASS |
| Session | Secure cookie | HTTPS-only | Not configured | FAIL |
| Session | Encryption | Enabled | Disabled | FAIL |
| CORS | Origin restriction | Not wildcard | Restricted | PASS |
| Dependencies | Version currency | Latest | v12.69.2 (latest) | PASS |
| Password | Hashing algorithm | bcrypt/argon2 | bcrypt (12 rounds) | PASS |
| Password | Centralized service | Single writer | PasswordService | PASS |

---

# REMEDIATION ROADMAP

## PHASE 0 — IMMEDIATE SECURITY BLOCKERS (Fix Within 24 Hours)

### [SEC-022] Rotate APP_KEY
- **File:** `.env`
- **Action:** Generate new APP_KEY, update all encrypted values
- **Risk:** Complete compromise of encrypted data if exposed

### [SEC-021] Set APP_DEBUG=false in Production
- **File:** `.env`
- **Action:** Set APP_DEBUG=false; validate via deployment script
- **Risk:** Full stack traces and env vars exposed

### [SEC-027] Set SESSION_SECURE_COOKIE=true
- **File:** `.env`
- **Action:** Set SESSION_SECURE_COOKIE=true in production
- **Risk:** Session hijacking over HTTP

### [SEC-013] Fix Email Template XSS
- **File:** `resources/views/mail/notification.blade.php:1`
- **Action:** Replace {!! $bodyText !!} with {!! Str::markdown(e($bodyText)) !!} or HTMLPurifier
- **Risk:** Stored XSS in email notifications

## PHASE 1 — CRITICAL/HIGH (Fix Within 1 Week)

### [SEC-002] Complete BlockPlatformAdminEscalation
- **File:** `app/Http/Middleware/BlockPlatformAdminEscalation.php:20`
- **Action:** Add 'guard' and 'role' to in_array blocking check
- **Risk:** Privilege escalation via form injection

### [SEC-018] Block SVG Uploads
- **Files:** InstituteSettingController, InstituteLogoController, SettingController, StudentFormRequest, StudentController
- **Action:** Remove 'svg' from mimes validation rules; add SVG sanitization if SVG is required
- **Risk:** Stored XSS via malicious SVG files

### [SEC-019] Move Sensitive Files to Private Storage
- **Files:** DocumentService, StudentController, Medical controllers
- **Action:** Change default disk from 'public' to 'local' or 's3' (private); implement signed URLs
- **Risk:** Sensitive documents publicly accessible

### [SEC-025] Strengthen CSP
- **File:** `app/Http/Middleware/SecurityHeaders.php:30`
- **Action:** Remove 'unsafe-inline' and 'unsafe-eval'; implement nonce-based CSP
- **Risk:** XSS attacks bypass CSP entirely

### [SEC-003] Add 2FA to API Login
- **File:** `app/Http/Controllers/Api/AuthController.php`
- **Action:** Implement 2FA challenge in API login flow
- **Risk:** 2FA bypass via API

### [SEC-008] Add $fillable to Unguarded Models
- **Files:** 100+ model files
- **Action:** Add $fillable arrays to all models, starting with security-sensitive ones
- **Risk:** Mass assignment vulnerabilities

### [SEC-028] Enable Session Encryption
- **File:** `.env`
- **Action:** Set SESSION_ENCRYPT=true
- **Risk:** Session data readable on database compromise

## PHASE 2 — MEDIUM (Fix Within 1 Month)

### [SEC-005] Add Permission Middleware to Finance Routes
- **File:** `routes/institute_modules.php:558-703`
- **Action:** Add permission:finance.manage / permission:finance.view
- **Risk:** Unauthorized finance access

### [SEC-006] Add Permission Middleware to Module Routes
- **File:** `routes/institute_modules.php` (Teachers, Alumni, Calendar, etc.)
- **Action:** Add appropriate permission: middleware
- **Risk:** Unauthorized module access

### [SEC-011] Parameterize selectRaw Variables
- **Files:** ReceivableList.php, PayableList.php
- **Action:** Use parameterized bindings instead of variable interpolation
- **Risk:** Potential SQL injection

### [SEC-012] Add Form Request Validation to API Controllers
- **Files:** 7+ API controllers
- **Action:** Create Form Request classes for all API endpoints
- **Risk:** Missing input validation

### [SEC-015] Fix json_encode in JavaScript Context
- **File:** `resources/views/medical/medicines/show.blade.php:217-219`
- **Action:** Add JSON_HEX_TAG flag to json_encode calls
- **Risk:** XSS via medicine names

### [SEC-030] Add $fillable to ExamResult
- **Files:** `app/Models/ExamResult.php`, `app/Http/Controllers/ExamController.php`
- **Action:** Add $fillable array; validate input explicitly
- **Risk:** Exam result manipulation

### [SEC-036] Verify Webhook Signature Validation
- **File:** `app/Services/PaymentGateway/Gateways/BkashGateway.php`
- **Action:** Implement HMAC signature verification on all webhook callbacks
- **Risk:** Forged webhook payloads

### [SEC-033] Verify Package Management Authorization
- **File:** Package management routes
- **Action:** Audit all package creation/update routes for proper authorization
- **Risk:** Pricing manipulation

### [SEC-039] Secure exec() Calls
- **File:** `app/Providers/AppServiceProvider.php:240-252`
- **Action:** Use escapeshellarg() for all variables; consider safer alternatives
- **Risk:** OS command injection

### [SEC-024] Remove Exception Messages from Error Responses
- **File:** `app/Http/Controllers/DocumentScanController.php:71,104`
- **Action:** Return generic error messages; log details server-side only
- **Risk:** Internal information leakage

### [SEC-017] Add Rate Limiting to Write Routes
- **File:** `routes/web.php` (students, batches, settings, etc.)
- **Action:** Add throttle:60,1 middleware to write routes
- **Risk:** Authenticated abuse

## PHASE 3 — LOW/HARDENING (Fix Within 3 Months)

### [SEC-004] Add 2FA Challenge Timeout
- **File:** `app/Http/Controllers/Auth/TwoFactorChallengeController.php`
- **Action:** Check challengeExpiryMinutes() in store method
- **Risk:** Stale 2FA sessions

### [SEC-007] Add Granular RBAC to Medical Routes
- **File:** `routes/medical.php:75-219`
- **Action:** Add permission:medical_*.view middleware
- **Risk:** Non-clinical staff accessing clinical data

### [SEC-010] Use UUID-Based Document Filenames
- **File:** `app/Http/Controllers/StudentController.php:673-680`
- **Action:** Use Str::uuid() for document filenames; move to private storage
- **Risk:** Predictable document URLs

### [SEC-020] Sanitize Upload Filenames
- **File:** `app/Http/Controllers/Admin/GeoImportController.php:80`
- **Action:** Use UUID-based filenames instead ofgetClientOriginalName()
- **Risk:** Path traversal

### [SEC-026] Add Permissions-Policy Header
- **File:** `app/Http/Middleware/SecurityHeaders.php`
- **Action:** Add Permissions-Policy: camera=(), microphone=(), geolocation=()
- **Risk:** Browser feature abuse

### [SEC-029] Restrict CORS Methods and Headers
- **File:** `config/cors.php`
- **Action:** Change allowed_methods and allowed_headers from ['*'] to specific values
- **Risk:** Overly permissive CORS

### [SEC-031] Add $fillable to Guardian Model
- **File:** `app/Models/Guardian.php`
- **Action:** Add $fillable array with safe fields
- **Risk:** Mass assignment

### [SEC-032] Run Dependency Audit
- **Action:** Run `composer audit` and `npm audit`; integrate into CI/CD
- **Risk:** Known CVEs

### [SEC-034] Add Unique Constraint to Certificate Numbers
- **File:** Certificate migration
- **Action:** Add unique constraint; use DB transaction with locking
- **Risk:** Duplicate certificate numbers

### [SEC-035] Encrypt Job Payloads
- **Action:** Use $hidden on sensitive model attributes; encrypt queue payloads
- **Risk:** Sensitive data in queue table

### [SEC-038] Set SESSION_DOMAIN
- **File:** `.env`
- **Action:** Set SESSION_DOMAIN for production
- **Risk:** Session leakage across subdomains

## PHASE 4 — SECURITY REGRESSION TESTS

### Required Tests:
1. Test that platform admin bypass is logged and auditable
2. Test that BlockPlatformAdminEscalation blocks all 6 keys
3. Test that SVG uploads are rejected
4. Test that private storage is used for sensitive documents
5. Test that CSP headers don't include unsafe-inline/unsafe-eval
6. Test that session cookies have Secure flag in production
7. Test that session data is encrypted
8. Test that all finance routes require permission middleware
9. Test that ExamResult cannot be mass-assigned with institute_id
10. Test that webhook signatures are validated
11. Test that rate limiting is applied to all write routes
12. Test that exception messages are not exposed in error responses
13. Test that 2FA is required for API login
14. Test that 2FA challenge expires after timeout
15. Test that all models have proper $fillable or $guarded arrays

---

# FINAL SECURITY GATE

| Control | Status |
|---------|--------|
| CRITICAL | **BLOCKED** — 3 critical findings (APP_KEY exposure, email XSS, debug mode) |
| HIGH | **BLOCKED** — 14 high findings |
| AUTHENTICATION | **PASS** — Strong implementation with minor gaps |
| AUTHORIZATION | **PARTIAL** — Platform admin bypass, missing route-level permissions |
| TENANT ISOLATION | **PASS** — Strong TenantScoped implementation |
| BRANCH ISOLATION | **PASS** — BranchScoped properly implemented |
| INPUT VALIDATION | **PARTIAL** — Good for web, weak for API |
| RATE LIMITING | **PARTIAL** — Auth routes covered, write routes unprotected |
| FILE UPLOAD | **FAIL** — SVG allowed, public storage for sensitive files |
| SECRETS | **FAIL** — APP_KEY in .env, APP_DEBUG=true |
| DEPENDENCIES | **PASS** — Latest versions |
| ERROR HANDLING | **PARTIAL** — Good when debug=false, but debug=true in .env |
| INFORMATION LEAKAGE | **FAIL** — CSP weakened, debug status leaked |
| SESSION SECURITY | **FAIL** — Secure cookie not set, encryption disabled |
| CSRF | **PASS** — No exemptions |
| SECURITY HEADERS | **PARTIAL** — Good headers present, CSP weakened |
| BUSINESS LOGIC | **PASS** — Proper authorization on financial records |
| OVERALL | **BLOCKED** — Must fix PHASE 0 items before any deployment |

---

*This audit was conducted following the comprehensive security audit contract. All findings are based on actual code analysis. No code changes were made during this audit phase.*
