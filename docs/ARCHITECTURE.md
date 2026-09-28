# ACCUMENAI — ARCHITECTURE DIAGRAMS

Evidence-based diagrams of the system as implemented. All claims traceable to files noted beneath each diagram.

---

## 1. System architecture

```mermaid
flowchart TB
    subgraph Clients
        B[Browser - Blade/Livewire/Alpine]
        R[React islands - 4 medical pages]
        M[Mobile app - Sanctum token]
        GA[Lab analyzer / gateway - lab.device auth]
    end

    subgraph "Laravel 12 monolith (Apache / cPanel, PHP 8.2)"
        RT[Route files<br/>web.php - institute_modules.php - medical.php<br/>auth.php - guardian.php - api.php - channels.php]
        MW[Middleware<br/>SecurityHeaders - SetLocale - SetTenantContext<br/>auth guards - permission - module_access - feature<br/>deny.teacher.finance - finance.write - advanced.accounting - medical*]
        CT[358 Controllers]
        SV[392 Services<br/>business logic + transactions]
        LV[21 Livewire components]
        MD[421 Eloquent models<br/>TenantScoped / BranchScoped global scopes]
    end

    subgraph "Engines"
        JP[JournalPostingService<br/>double-entry, balance, periods]
        IS[InventoryStockService<br/>single stock write path, WAC]
        MA[ModuleAccessService<br/>package - feature - override resolution]
    end

    subgraph "Data"
        DB[(MySQL - 427 tables<br/>schema/mysql-schema.sql + 240 migrations)]
        Q[(database queue)]
        C[(cache: file/database)]
        S[(storage: local disk)]
    end

    subgraph "Async / realtime"
        QR[queue worker<br/>default, notifications]
        RV[Laravel Reverb<br/>lab analyzer broadcasts]
    end

    subgraph "External"
        AI[AI providers: Gemini / OpenAI / Anthropic]
        BK[bKash tokenized payments]
        SM[SMTP (Gmail)]
        RC[Google reCAPTCHA]
        DG[DGDA / RxNorm registries]
    end

    B --> RT
    R --> RT
    M --> RT
    GA --> RT
    RT --> MW --> CT --> SV --> MD --> DB
    CT --> LV --> MD
    SV --> JP --> DB
    SV --> IS --> DB
    SV --> MA
    SV -.dispatch.-> Q --> QR -.-> SV
    RV -.broadcast.-> R
    SV --> AI
    SV --> BK
    SV --> SM
    CT --> RC
    SV --> DG
```

**Evidence**: `composer.json`, `package.json`, `vite.config.js`, `bootstrap/app.php`, `routes/*`, `database/schema/mysql-schema.sql`, `config/reverb.php`.

---

## 2. Authentication flow

```mermaid
sequenceDiagram
    participant U as User
    participant R as Route (routes/web.php or auth.php)
    participant F as fortifyguard (SetFortifyGuard)
    participant C as UserLoginController
    participant G as Auth guard (web | platform_admin | institute_user | guardian)
    participant W as Workspace
    participant T as SetTenantContext

    U->>R: POST /login (throttle:30,15 + reCAPTCHA)
    R->>F: pin config(fortify.guard) from session login.guard
    F->>C: login(Request)
    C->>G: attempt(email|phone, password, status=active, remember)
    alt locked (locked_until future) or failed_login_count >= threshold
        C-->>U: 429/423 lockout response
    else credentials ok
        C->>C: PasswordService::rehashIfNeeded()
        C->>C: if !email_verified_at -> redirect verification.notice
        C->>W: Workspace::resolveAfterLogin(user, requested)
        Note over W: 0 memberships -> null<br/>1 membership -> auto set<br/>N -> explicit choice or picker
        W->>W: session active_institution_id = id
        C-->>U: redirect intended / workspace.picker
    end
    U->>R: next authenticated request
    R->>T: SetTenantContext (before SubstituteBindings)
    T->>W: Workspace::verify(workspaceId, userId)
    alt forged workspace id
        T-->>U: 403 Invalid workspace
    else absent workspace
        T->>T: auto-resolve first active institution_user row
    end
    T->>T: TenantContext::set(id) + BranchContext::set(membership.branch_id)
```

**Evidence**: `app/Http/Controllers/Auth/UserLoginController.php:47-233`, `app/Support/Workspace.php`, `app/Http/Middleware/SetTenantContext.php`, `app/Http/Middleware/SetFortifyGuard.php`, `bootstrap/app.php:44-67`.

```mermaid
flowchart LR
    subgraph "5 guards (config/auth.php)"
        G1[web - App\Models\User - users]
        G2[platform_admin - PlatformAdmin - platform_admins]
        G3[institute_user - InstituteUser - institute_users]
        G4[guardian - Guardian - guardians]
        G5[platform_staff - PlatformStaff - platform_staffs]
    end
    G1 -->|memberships| MU["institution_user (Membership)"]
    G3 -->|direct| MI[institutes.institute_id]
    G2 --> PA[(platform console: admin/* and super-admin/*)]
    G4 --> GD[(guardian/* portal)]
    G5 -.UNUSED - 0 routes.- X[ ]
```

---

## 3. Organization / membership flow

```mermaid
flowchart TD
    U["User<br/>users (global person)<br/>account_type: owner | staff"]
    U -->|hasMany| M["Membership<br/>table: institution_user<br/>UNIQUE(user_id, institution_id)"]
    M -->|belongsTo institution_id| I["Institute (= Organization)<br/>institutes"]
    M -->|belongsTo role_id| RO["Role<br/>slug: institute-owner | staff roles<br/>institute_id NULL = system role"]
    M -->|belongsTo branch_id nullable| BR["Branch<br/>branches (code uq globally)"]
    M -->|role_permissions| PE["Permission<br/>permissions.slug (module.action)"]
    RO --> PE

    I -->|package_id| PK["SubscriptionPackage<br/>subscription_packages"]
    PK -->|"package_modules / package_scopes / package_features"| MR["module_registry / feature_registry"]
    I -->|"institute_module_entitlements / overrides"| MR
    MR --> MAB["ModuleAccessService<br/>isEnabled() / isFeatureEnabled()"]

    U -->|"account_type + role slug invariant"| CHK{"Membership::assertRoleAllowedForAccountType()<br/>owner <-> institute-owner"}
    CHK -->|violation| EX["AccountTypeMismatchException"]

    style U fill:#e8f0fe
    style I fill:#e6f4ea
    style EX fill:#fce8e6
```

**Evidence**: `app/Models/User.php:205-218`, `app/Models/Membership.php`, `app/Models/Institute.php`, `app/Services/ModuleAccessService.php`, `database/schema/mysql-schema.sql`.

---

## 4. Multi-tenant data flow

```mermaid
flowchart TD
    REQ[HTTP request] --> A{Authenticated user type}
    A -->|Guardian| G1["TenantContext = user.institute_id<br/>BranchContext = clear (all branches)"]
    A -->|InstituteUser| G2["TenantContext = user.institute_id<br/>BranchContext = user.branch_id"]
    A -->|User (web)| G3["Workspace::verify(session active_institution_id)<br/>forged -> 403 / absent -> auto-resolve<br/>TenantContext = membership.institution_id<br/>BranchContext = membership.branch_id (null = all branches)"]
    A -->|none| G4["TenantContext = clear (scopes inactive)"]

    G1 & G2 & G3 --> SB["SubstituteBindings (reordered AFTER tenant)"]
    SB --> Q["Eloquent query on model with TenantScoped"]
    Q --> SC{"TenantContext::enabled()?"}
    SC -->|no| NOOP["NO-OP: all rows visible<br/>(CLI, platform admin)"]
    SC -->|yes| WHERE["WHERE institute_id = TenantContext::id()<br/>(hybrid: OR institute_id IS NULL AND is_system=1)"]
    WHERE --> BC{"BranchScoped + BranchContext enabled?"}
    BC -->|yes| BW["AND branch_id = BranchContext::id()"]
    BC -->|no (owner/admin)| ALLB["all branches of tenant"]

    CREATE[Model::create] --> HOOK["creating hook:<br/>force institute_id = context<br/>updating hook: revert institute_id / created_by"]
    HOOK --> DB[(MySQL)]

    style NOOP fill:#fce8e6
    style HOOK fill:#e6f4ea
```

**Where isolation IS enforced**
- `bootstrap/app.php:101` middleware priority.
- `app/Models/Concerns/TenantScoped.php:19-68` (scope + create/update hooks).
- `app/Models/Concerns/BranchScoped.php:24-48`.
- `app/Http/Middleware/EnsureInstituteContext.php` (API).
- 223 of 421 model files use `TenantScoped`.
- `SystemTenantIsolationAudit` command + `TenantIsolationAuditTest`, `Phase18BranchIsolationTest`.

**Where it is NOT framework-enforced (manual only)**
- All 76 `app/Models/Medical/*` models — 0 global scopes; controllers call `MedicalScope::instituteId()` (52 files reference it; e.g. `PatientController.php:68,207,302,463`).
- Line-item models (`InvoiceItem`, `SalesOrderLine`, `PurchaseInvoiceItem`, …) — scoped via parent only.
- Any code using `withoutGlobalScopes()` must re-filter manually (`SalesInventoryIntegration`, `CheckModuleAccess`, `CheckFeatureAccess`).
- Routes lacking the `tenant` middleware would run with disabled context (tenant groups that declare `auth` but not `tenant` are the risk surface).

---

## 5. Request lifecycle (tenant CRUD example)

```mermaid
sequenceDiagram
    participant V as Blade view (SLP form)
    participant R as routes/institute_modules.php ($tenant group)
    participant M as Middleware chain
    participant C as Controller
    participant FR as FormRequest / validate()
    participant S as Service
    participant MO as Model (TenantScoped)
    participant D as MySQL

    V->>R: POST sales/orders
    Note over R: group middleware:<br/>auth:institute_user,web - tenant - deny.teacher.finance<br/>- finance.write - verified<br/>+ module_access:sales - permission:sales.create
    R->>M: run chain (SetTenantContext before SubstituteBindings)
    M->>C: dispatch
    C->>FR: validate payload
    C->>S: SalesOrderService::store(...)
    S->>S: allocate doc no (sales_sequences), compute totals
    S->>MO: DB::transaction { create order + lines }
    MO->>MO: creating hook force institute_id / branch_id
    MO->>D: INSERT
    S->>S: (optional) inventory + accounting side effects
    S-->>C: order
    C-->>V: redirect with session('status')
```

---

## 6. Major module relationships

```mermaid
flowchart LR
    subgraph Foundation
        TEN[Tenancy<br/>TenantScoped / BranchContext]
        AUTH[Auth + RBAC<br/>guards / roles / permissions]
        ENT[Entitlements<br/>module_registry / feature_registry / packages]
    end

    subgraph Commerce
        SA[Sales]
        PU[Purchase]
        IV[Inventory]
        PA2[Parties / Customers / Suppliers]
    end

    subgraph Finance
        AC[Accounting<br/>COA / Journals / Periods]
        TX[Tax - VAT / TDS]
        FA[Fixed Assets]
        BUD[Budgets]
        REP[Reports / Aging / Executive]
    end

    subgraph People
        HR[HR + Payroll]
        CRM[CRM]
        ED[Education / Academic]
        TR[Training Center]
    end

    subgraph Industry
        MED[Medical / HMS]
        LAB[Lab analyzer integration]
    end

    subgraph Platform
        SAA[SaaS packages / entitlements / bKash]
        AI[AI assistant]
        NOT[Notifications]
        DOC[Documents]
    end

    AUTH --> SA & PU & IV & AC & HR & CRM & ED & TR & MED
    ENT --> SA & PU & IV & AC & HR & MED & TR
    SA --> IV
    SA --> PA2
    PU --> IV
    PU --> PA2
    SA & PU & IV --> AC
    HR --> AC
    ED --> AC
    FA --> AC
    TX --> AC
    BUD --> AC
    REP --> AC
    ED --> CRM
    SA --> CRM
    LAB --> MED
    AI --> AC
    SAA --> ENT
    NOT --> AUTH
```

**Dependency rules observed in code**: financial effects always funnel through `JournalPostingService`; stock effects always funnel through `InventoryStockService`; entitlement checks always funnel through `ModuleAccessService`.

---

## 7. Business workflows

### 7a. Sales cycle

```mermaid
flowchart LR
    L[Lead<br/>sales/leads] -->|convert| Q[SalesQuotation<br/>+ lines]
    Q -->|convert| O[SalesOrder<br/>states: draft submitted approved<br/>processing ready completed cancelled rejected]
    O -->|create| D[SalesDelivery]
    D -->|confirm| DI["InventoryStockService::saleIssue<br/>movements + stock_levels + COA"]
    D -->|invoice| IV[Invoice + InvoiceItems]
    IV -->|postJournal| JR[Journal posted<br/>Dr AR / Cr Revenue (+Tax)]
    IV -->|payment| P[Payment]
    Q -.->|also direct| IV
    O -.->|storeForOrder| IV
    IV -->|return| RT["SalesReturn / credit-memo<br/>approve post refund reverse"]
    RT -->|returnStock| IV
    RT -->|journal reversal| JR
```

**Evidence**: `routes/institute_modules.php` sales groups; `app/Services/Sales/*` (13 services); `app/Services/Accounting/InvoiceService.php:50-362` (`create`, `issueInventoryStock`, `postJournal`, `cancel`).

### 7b. Purchase cycle

```mermaid
flowchart LR
    PR[PurchaseRequest] --> PQ[PurchaseQuotation]
    PQ --> PO[PurchaseOrder]
    PO --> GR[GoodsReceipt]
    GR -->|"InventoryStockService::receivePurchase"| IV["movements + WAC valuation<br/>Dr Inventory / Cr AP"]
    GR --> PI[PurchaseInvoice]
    PI -->|post| PJ[Journal]
    PI --> SP[PurchaseSupplierPayment] --> PJ
    PO -->|return| PR2[PurchaseReturn / vendor credit] -->|returnStock| IV
```

**Evidence**: `app/Services/Purchase/*` (10 services), `PurchaseAccountingService`.

### 7c. Journal posting (double-entry)

```mermaid
flowchart TD
    IN[entries: debit / credit lines] --> V{"validate:<br/>>=2 lines<br/>sum(debit)==sum(credit) (eps 0.00005)<br/>no line with both sides"}
    V -->|fail| RE[ValidationException]
    V -->|ok| FY["resolve fiscal year + open period"]
    FY --> CT["DB::transaction<br/>Journal(status=draft) + JournalEntry rows<br/>journal_no allocated per institute+branch"]
    CT --> AUD["AccountingAuditService::log (create)"]
    AUD --> POST{"post_now?"}
    POST -->|yes| P["post(): re-validate balance,<br/>assertPeriodOpenForPosting,<br/>status=posted, posted_by/at"]
    P --> EV["JournalPosted::dispatch"]
    EV --> LN["LogJournalPosted (queued) -> Log"]
    P --> IMP["immutability: only reverse()/void() allowed"]
```

**Evidence**: `app/Services/Accounting/JournalPostingService.php:36-130+`, `app/Events/JournalPosted.php`, `app/Listeners/LogJournalPosted.php`.

### 7d. Inventory stock

```mermaid
flowchart TD
    SRC["inventory_movements (SOURCE OF TRUTH)"] --> CALC["sum movements per item+warehouse(+batch)"]
    CALC --> CACHE["inventory_stock_levels (cached balance)<br/>updated in same transaction<br/>SELECT ... FOR UPDATE row locks"]
    CACHE --> VAL["weighted-average costing<br/>(single supported valuation method)"]
    VAL --> ACC["InventoryAccountingService -> JournalPostingService"]

    WRITERS["ONLY writers (InventoryStockService)"] --> SRC
    W1[receivePurchase] --> WRITERS
    W2[saleIssue] --> WRITERS
    W3[transfer] --> WRITERS
    W4[postAdjustment] --> WRITERS
    W5[postCount] --> WRITERS
    W6[returnStock / returnForReference] --> WRITERS
```

**Evidence**: `app/Services/Inventory/InventoryStockService.php` class docblock + 8 public methods; grep shows it is the only file creating `InventoryMovement`.

---

## 8. Database relationship overview

```mermaid
erDiagram
    users ||--o{ institution_user : "memberships (user_id)"
    institutes ||--o{ institution_user : "institution_id"
    roles ||--o{ institution_user : "role_id"
    branches ||--o{ institution_user : "branch_id (nullable)"
    roles ||--o{ role_permissions : ""
    permissions ||--o{ role_permissions : ""
    institutes ||--o{ branches : ""
    institutes ||--o{ subscription_packages : "package_id"
    subscription_packages ||--o{ package_modules : ""
    institutes ||--o{ institute_module_entitlements : ""
    institutes ||--|| institute_settings : ""

    institutes ||--o{ students : ""
    students ||--o{ student_enrollments : ""
    batches ||--o{ student_enrollments : ""
    institutes ||--o{ parties : ""
    parties ||--o{ sales_quotations : ""
    sales_quotations ||--o{ sales_orders : "convert"
    sales_orders ||--o{ sales_deliveries : ""
    sales_deliveries ||--o{ invoices : ""
    parties ||--o{ invoices : ""
    invoices ||--o{ invoice_items : ""
    invoices ||--o{ payments : ""
    invoices ||--o{ sales_returns : ""
    institutes ||--o{ purchase_orders : ""
    purchase_orders ||--o{ goods_receipts : ""
    goods_receipts ||--o{ purchase_invoices : ""
    purchase_invoices ||--o{ purchase_supplier_payments : ""

    institutes ||--o{ inventory_items : ""
    inventory_items ||--o{ inventory_movements : ""
    inventory_warehouses ||--o{ inventory_stock_levels : ""
    inventory_items ||--o{ inventory_stock_levels : ""
    inventory_batches ||--o{ inventory_movements : ""

    institutes ||--o{ chart_of_accounts : "nullable = global hybrid"
    chart_of_accounts ||--o{ journal_entries : "coa_id"
    institutes ||--o{ journals : ""
    journals ||--o{ journal_entries : ""
    fiscal_years ||--o{ accounting_periods : ""
    journals ||--o{ accounting_periods : "period_id"

    institutes ||--o{ hr_employees : ""
    hr_employees ||--o{ hr_payrolls : ""
    hr_payroll_periods ||--o{ hr_payrolls : ""

    institutes ||--o{ patients : "manual scope"
    patients ||--o{ medical_encounters : ""
    patients ||--o{ prescriptions : ""
    prescriptions ||--o{ prescription_items : ""
    patients ||--o{ lab_orders : ""

    institutes ||--o{ crm_contacts : ""
    institutes ||--o{ documents : ""
    institutes ||--o{ notification_templates : ""
```

**Notes**
- `institution_user` is the Membership table (singular name, legacy pivot naming).
- `institute_users` (plural) is the separate legacy login realm — **not shown above to avoid confusion**.
- `chart_of_accounts.institute_id` is nullable for the hybrid global COA (`is_system = 1`).
- All `institute_id` FKs are the tenancy axis; `branch_id` is a secondary axis on branch-owned tables.

---

## 9. Deployment / runtime architecture

```mermaid
flowchart LR
    subgraph "Shared host (cPanel/Apache, PHP 8.2.12)"
        DOCROOT["Document root -> root/.htaccess rewrites to /public"]
        APP[Laravel app]
        VW["artisan view:cache / config:cache"]
    end

    subgraph "Processes"
        W1[Apache / php-fpm]
        W2["queue:listen database<br/>queues: default, notifications"]
        W3["scheduler (bootstrap/app.php)<br/>auth:audit-hashes 03:00<br/>notifications:retry every 5m<br/>accounting:health-check 04:00<br/>entitlements:expire hourly (config-gated)<br/>saas:verify-pending every 5m<br/>database:backup daily 01:00 / weekly Sun 02:00"]
        W4["optional: reverb:start<br/>(BROADCAST_CONNECTION=reverb for lab analyzer)"]
        W5["npm run build -> public/build"]
    end

    APP --> W1 & W2 & W3
    W4 --> APP
    W3 --> DB[(MySQL)]
    W2 --> DB
```

**Evidence**: `bootstrap/app.php:106-167`, `composer.json` scripts (`setup`, `dev`, `test`), `.htaccess`, `docs/cpanel-2gb-optimization/`, `scripts/*.sh`.
