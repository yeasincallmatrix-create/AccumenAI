# Tenant Naming Convention

## Synonyms
| Concept | Concrete Name | Location |
|---------|---------------|----------|
| Business entity | Tenant | Conceptual/docs |
| Model class | `Institute` | app/Models/Institute.php |
| DB column | `institute_id` | All tables |
| Helper function | `tenant_id()` | Global helpers |
| Trait | `TenantScoped` | app/Models/Concerns/ |
| FK alias | `institute_id` | Relations |

## Rules
- **Do NOT rename** — these are intentional synonyms
- Column: always `institute_id`
- Helper: always `tenant_id()`
- Model: always `Institute`
- Trait: always `TenantScoped`

## Critical Gotcha
`withoutGlobalScope('institute')` — NOT `'tenant'`

Wrong name = silent no-op → hybrid scope never lifted → global rows hidden.

## Hybrid Row Semantics
- **Global rows**: `institute_id = NULL AND is_system = 1` (shared, read-only)
- **Tenant rows**: `institute_id = X` (private, editable)

## Scope Reference
| Scope | Returns |
|-------|---------|
| `globalOnly()` | Global rows only |
| `tenantOnly($id)` | Tenant's own rows only |
| `visibleTo($id)` | Globals + tenant's own rows |
