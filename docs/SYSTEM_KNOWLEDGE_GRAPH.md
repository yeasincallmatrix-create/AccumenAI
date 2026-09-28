# ACCUMENAI — SYSTEM KNOWLEDGE GRAPH

Node/edge maps of the system: module dependencies, core data flow, tenancy chain, and the central domain entities. Evidence = file paths (or route prefix + table names from `database/schema/mysql-schema.sql`).

---

## 1. Module dependency graph

```mermaid
graph TD
  subgraph Platform
    SA[SuperAdmin / Platform Admin<br/>admin/* 294, super-admin/* 20]
    SAAS[saas subscriptions + bKash<br/>saas/*, SubscriptionPackage]
    GEO[Geo / Countries / Currency<br/>Support/GeoHierarchy, countries]
    AI[AI Assistant<br/>ai/*, Services/Ai/*]
  end

  subgraph Core
    ORG[Institute + Branch + Settings<br/>institutes, branches, module_registry]
    RBAC[Users / Membership / Roles<br/>users, institution_user, roles, permissions]
    NOTIF[Notifications<br/>notification_*, SendNotificationJob]
    WF[Workflow / Approvals<br/>workflows/*, ApprovalRequest]
    DOC[Documents<br/>documents/*]
  end

  subgraph Domain
    PARTY[Parties<br/>parties]
    INV[Inventory<br/>inventory_movements = truth]
    SALES[Sales<br/>sales/* 119]
    PURCH[Purchase<br/>purchase/* 116]
    FIN[Accounting/Finance<br/>finance/* 132 + accounting/* 66]
    TAX[Tax / TDS / VAT<br/>settings/tax*, TaxRate]
    FA[Fixed Assets<br/>fixed-assets/* 16]
    HR[HR / Payroll<br/>hr/* 145]
    CRM[CRM<br/>crm/* 34]
    EDU[Education<br/>students/courses/exams ~152]
    MED[Medical / HMS<br/>medical 490]
    TRAIN[Training<br/>training/* 86]
    POS[POS — PLANNED]
    MFG[Manufacturing — PLANNED]
    RE[Real Estate — PLANNED]
    REST[Restaurant — PLANNED]
  end

  SAAS --> ORG
  SA --> ORG
  SA --> SAAS
  ORG --> RBAC
  ORG --> GEO
  RBAC --> WF
  ORG --> NOTIF

  RBAC --> SALES & PURCH & FIN & HR & CRM & EDU & MED & TRAIN
  SALES --> INV & PARTY & FIN
  PURCH --> INV & PARTY & FIN
  INV --> FIN
  FA --> FIN
  HR --> FIN
  TAX --> FIN
  FIN --> WF
  EDU --> CRM
  EDU --> FIN
  MED --> FIN
  MED --> INV
  TRAIN --> FIN
  DOC --> RBAC
  AI --> FIN & HR & CRM

  POS -. planned .-> INV
  MFG -. planned .-> INV
  RE -. planned .-> ORG
  REST -. planned .-> INV
```

Legend: solid `-->` = implemented hard dependency; dotted `-.->` = PLANNED (config + registry migrations only, zero routes).

---

## 2. Request → context → authorization pipeline

```mermaid
sequenceDiagram
  participant B as Browser
  participant R as routes/*.php
  participant M as Middleware (bootstrap/app.php)
  participant C as Controller
  participant S as Services
  participant D as MySQL (queue/cache: database/file)

  B->>R: HTTP request
  R->>M: group middleware
  Note over M: 1. SecurityHeaders (global)<br/>2. SetLocale<br/>3. ForceJsonResponse (api)<br/>4. auth guard (web / platform_admin / guardian)<br/>5. ensure.institute.context / SetTenantContext (BEFORE SubstituteBindings :101)<br/>6. module_access / feature / permission gates<br/>7. SubstituteBindings
  M->>C: route-model binding already tenant-scoped
  C->>C: FormRequest validation / $this->authorize(Policy)
  C->>S: domain call (never SQL inline)
  S->>D: TenantScoped queries + JournalPostingService / InventoryStockService
  S-->>C: entities
  C-->>B: Blade view (institute/admin layout) or JSON Resource
```

Guard selection: `SetFortifyGuard` + `config/auth.php` (5 guards; `platform_staff` unused).

---

## 3. Tenancy & workspace chain

```mermaid
graph LR
  SES["session.active_institution_id<br/>(Support\\Workspace)"] --> STI["SetTenantContext middleware<br/>prevents forged workspace → 403"]
  STI --> TC["TenantContext::instituteId()<br/>static holder"]
  STI --> BC["BranchContext::branchId()<br/>(cleared for guardian)"]
  TC --> SC["Support\\MedicalScope::instituteId()"]
  TC --> TS["Models\\Concerns\\TenantScoped<br/>global scope + create/update hooks"]
  TC --> BS["BranchScoped / BranchScopedOrShared"]
  TS --> M1["223 trait-scoped models"]
  SC --> M2["76 Medical models — MANUAL where()"]
  BS --> M3["75 branch-scoped usages"]
```

Key facts:
- Column: `institute_id` everywhere (`organization_id` does not exist).
- Trait hooks force `institute_id`/`created_by` on create and revert tampering on update.
- Medical: no global scope — enforcement by convention (`MedicalScope` used in 52 files).
- Session workspace = `active_institution_id`; mismatch/forge → 403.

---

## 4. Core transaction flows

### 4a. Sales order → cash

```mermaid
graph LR
  Q[Quotation] --> SO[SalesOrder]
  SO --> D[Delivery] -->|InventoryStockService.saleIssue| MOV[(inventory_movements)]
  MOV --> SL[(inventory_stock_levels)]
  D --> INV[Invoice]
  INV -->|Accounting\\InvoiceService| J["JournalPostingService::post()"]
  J --> JRN[(journals + journal_entries)]
  INV --> P[Payment] --> J
  J -.event.-> JP[JournalPosted → LogJournalPosted queued]
  R[SalesReturn] -->|returnStock| MOV
  R --> J
```

### 4b. Purchase → stock + AP

```mermaid
graph LR
  PR[PurchaseRequest] --> PQ[PurchaseQuotation] --> PO[PurchaseOrder]
  PO --> GRN[GoodsReceipt] -->|InventoryStockService.receivePurchase| MOV[(inventory_movements)]
  GRN -->|InventoryAccountingService| J[JournalPostingService: Dr Inventory / Cr AP]
  GRN --> PI[PurchaseInvoice] --> PP[SupplierPayment] --> J
  PRt[PurchaseReturn] -->|returnStock| MOV
```

### 4c. Inventory write path (single choke point)

```mermaid
graph TD
  C1[sales delivery] --> ISS
  C2[purchase GRN] --> ISS
  C3[adjustment/count/transfer] --> ISS
  C4[returns] --> ISS
  C5[medical dispense] --> ISS
  ISS["InventoryStockService<br/>receivePurchase | saleIssue | transfer |<br/>postAdjustment | postCount | returnStock | returnForReference"]
  ISS -->|row lock| SL[(inventory_stock_levels<br/>cached balance + avg_cost)]
  ISS --> MV[(inventory_movements<br/>SOURCE OF TRUTH)]
  ISS --> AC[InventoryAccountingService] --> J[JournalPostingService]
```

### 4d. HR payroll → finance

```mermaid
graph LR
  EMP[hr_employees] --> ATT[attendance/leave] --> PAY[hr_payroll_periods/items]
  PAY --> HRF[HrPayrollFinanceService] --> J[JournalPostingService]
  PAY --> PAYSLIP[payslip + hr_documents]
```

---

## 5. Central entities & relationships

```mermaid
erDiagram
  INSTITUTE ||--o{ BRANCH : has
  INSTITUTE ||--o{ MEMBERSHIP : "institution_user"
  USER ||--|| MEMBERSHIP : "account_type owner|staff"
  MEMBERSHIP }o--|| ROLE : "one role per session"
  ROLE }o--o{ PERMISSION : role_permissions
  USER ||--o{ USER_MODULE_ACCESS : "user_module_access"
  INSTITUTE ||--o{ INSTITUTE_MODULE_ENTITLEMENT : module_registry
  INSTITUTE ||--o{ INSTITUTE_FEATURE_OVERRIDE : "feature overrides"

  INSTITUTE ||--o{ PARTY : "parties"
  PARTY ||--o{ INVOICE : "sales"
  PARTY ||--o{ PURCHASE_INVOICE : "purchase"
  INVOICE ||--o{ INVOICE_ITEM : lines
  INVOICE ||--o{ PAYMENT : received
  PURCHASE_INVOICE ||--o{ GOODS_RECEIPT : "GRN"
  INVENTORY_ITEM ||--o{ INVENTORY_STOCK_LEVEL : "per warehouse"
  INVENTORY_ITEM ||--o{ INVENTORY_MOVEMENT : "truth"
  INVENTORY_WAREHOUSE ||--o{ INVENTORY_STOCK_LEVEL : has
  CHART_OF_ACCOUNT ||--o{ JOURNAL_ENTRY : "via journals"
  JOURNAL ||--o{ JOURNAL_ENTRY : lines
  FISCAL_YEAR ||--o{ ACCOUNTING_PERIOD : periods

  STUDENT ||--o{ STUDENT_ENROLLMENT : enrolls
  BATCH ||--o{ STUDENT_ENROLLMENT : has
  COURSE ||--o{ BATCH : offers
  STUDENT ||--o{ EXAM_RESULT : results
  PATIENT ||--o{ APPOINTMENT : books
  PATIENT ||--o{ MEDICAL_ENCOUNTER : visits
  MEDICAL_ENCOUNTER ||--o{ PRESCRIPTION : writes
  LAB_ORDER ||--o{ LAB_RESULT : "analyzer via Reverb"
  EMPLOYEE ||--o{ HR_ATTENDANCE : tracks
  EMPLOYEE ||--o{ HR_PAYROLL_ITEM : paid
  LEAD ||--o{ CRM_ACTIVITY : "crm_leads"
  DOCUMENT ||--o{ DOCUMENT_VERSION : versions
  WORKFLOW ||--o{ WORKFLOW_STEP : steps
  APPROVAL_REQUEST ||--o{ APPROVAL_STEP : gates
```

Notes:
- `MEMBERSHIP` = model `Membership`, **table `institution_user`**, FK `institution_id`.
- `CHART_OF_ACCOUNT` rows are hybrid: `institute_id NULL AND is_system=1` (global) + tenant rows.
- Cross-module FKs are discipline-based: e.g. sales `invoices`/`parties` ↔ finance journals are linked by `journal` posting references, not hard FKs — verify per migration.

---

## 6. Cross-cutting service graph

```mermaid
graph TD
  subgraph Money
    JPS[JournalPostingService]:::core
    IS[Accounting\\InvoiceService] --> JPS
    IPS[PurchaseAccountingService] --> JPS
    IAS[InventoryAccountingService] --> JPS
    HRF[HrPayrollFinanceService] --> JPS
    FAS[FixedAssetAccountingService] --> JPS
    TAS[TaxAccountingService] --> JPS
    APS[AssetDepreciation] --> JPS
    RPS[RecurringTransactionService] --> JPS
    BRS[ProgressiveInvoiceService] --> JPS
  end
  subgraph Stock
    ISS[InventoryStockService]:::core
  end
  subgraph Access
    MAS[ModuleAccessService]:::core
    MRS[RoleTemplateService] --> MAS
    UMS[UserModuleAccessService] --> MAS
  end
  subgraph Audit
    AAS[AccountingAuditService]
    WFS[WorkflowService]
    LAS[LabAnalyzer audit]
  end
  JPS -->|JournalPosted event| LOG[LogJournalPosted queued]
  JPS --> AAS
  MAS --> LOG2[module_access_logs]
  classDef core fill:#ffe,stroke:#c90,stroke-width:2px;
```

---

## 7. Event / job / schedule graph

| Trigger | Event/Job | Where |
|---|---|---|
| Journal posted | `JournalPosted` → `LogJournalPosted` (queued) | `JournalPostingService.php:103` |
| Email verification | `QueuedVerifyEmail` notification (queued) | `Auth/*` |
| Notification send | `SendNotificationJob` | `Support/NotificationCenter` |
| Analyzer result | `LabAnalyzerResultStored` + Reverb broadcast | `LabIntegration/*` |
| Depreciation run | `DepreciationRunJob` | `FixedAsset/*` |
| Every 5 min | `notifications:retry` schedule | `bootstrap/app.php` schedule |
| Every minute | `payment-gateway:verify-pending`, entitlement expiry, queue work | `bootstrap/app.php` |
| Daily/weekly | `ProcessExpiredGrants`, `EntitlementsExpire`, `auth:audit-hashes`, archive/report jobs | `bootstrap/app.php` |
| `InvoicePaid` event | **NEVER dispatched** (listener dead) | `app/Events/InvoicePaid.php` |
| `AuditActivityLog` middleware | **NEVER registered** | `app/Http/Middleware/AuditActivityLog.php` |

Queue: `config/queue.php` → `database` driver (`jobs`/`failed_jobs` tables). Cache: `file` (local). Sessions: `database`.

---

## 8. Frontend rendering graph

```mermaid
graph LR
  V[Vite 7 build] --> CSS[resources/css/app.css — Tailwind 4 + 4 static CSS files public/css/*]
  V --> JS[resources/js — React 19: 4 medical pages only]
  BL[Blade views 974] --> L1[x-layouts.institute 1832 ln]
  BL --> L2[x-layouts.admin 942 ln]
  BL --> L3[standalone / app layouts]
  LW[Livewire 21 components] --> BL
  BL --> D["@moduleEnabled / @featureEnabled / @term<br/>AppServiceProvider directives"]
  BL --> I[Inertia? NO — plain Blade + fetch/ajax for notifications]
```

---

## 9. Trust boundaries

1. **Public**: login, register, password reset, geo lookup, verification callbacks → throttled + reCAPTCHA.
2. **Institute session**: `web`/`institute_user` guards, tenant-scoped, RBAC-gated.
3. **Guardian session**: separate guard, branch context cleared, own-children only.
4. **Platform admin**: `platform_admin` guard, cross-tenant by design (super-admin destructive routes are the most sensitive in the app).
5. **API**: `auth:sanctum` tokens + `ensure.institute.context` + rate limit 60/min.
6. **Devices**: lab analyzers via separate credential tables (`lab_integration.device_credentials`), queued parsing.
7. **Webhooks**: bKash callback `/saas/callback` with webhook secret — signature-verified.

---

## 10. Dead / dangling nodes (present in graph but disconnected)

- `AuditActivityLog` middleware → 0 registrations.
- `InvoicePaid` event + `LogInvoicePaid` listener → 0 dispatches.
- `SalesInventoryIntegration` service → 0 callers.
- `platform_staff` guard → 0 routes.
- `LegacyUser` model → usage unverified (treat as legacy).
- POS / Manufacturing / Real Estate / Restaurant configs + registry migrations → 0 routes/controllers/models (PLANNED nodes, dotted in §1).
