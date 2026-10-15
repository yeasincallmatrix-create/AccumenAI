# Testing Setup

## Running Medical Billing Tests

```bash
php artisan test tests/Feature/Medical/
# Expected: 115 passed

php artisan test tests/Feature/Settings/MedicalBillingSettingsTest.php
# Expected: 8 passed
```

## Full Regression

```bash
php tests/shards/run.php
# Known baseline: ~1,100 failures (Academic, Admission, flat Hr*, etc.)
# Changed domains should be green: Medical, Accounting, Finance, Settings
```
