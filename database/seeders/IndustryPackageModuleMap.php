<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;

/**
 * Per-industry, per-tier module lists for industry-specific packages
 * (Part 2 - 6 industries x 3 tiers).
 *
 * Every key below was verified against module_registry (status=active)
 * in the Part 2 Step 1.2 audit:
 *
 *   real_estate 48 · manufacturing 22 · healthcare (medical.* modules) 14 ·
 *   training_center 9 · education 7
 *
 * POS is a module group (not an industry) — its tier map was removed with
 * the pos_starter/growth/enterprise packages; pos.* modules remain inside
 * the retail/restaurant maps below.
 */
class IndustryPackageModuleMap
{
    public static function all(): array
    {
        return [
            'real_estate' => [
                'starter' => [
                    'real_estate.properties', 'real_estate.buildings', 'real_estate.units',
                    'real_estate.owners', 'real_estate.leases', 'real_estate.tenants',
                    'real_estate.rent_invoices', 'real_estate.rent_collection',
                ],
                'growth' => [
                    'real_estate.properties', 'real_estate.buildings', 'real_estate.units',
                    'real_estate.owners', 'real_estate.property_types', 'real_estate.amenities',
                    'real_estate.leases', 'real_estate.tenants', 'real_estate.rent_invoices',
                    'real_estate.rent_collection', 'real_estate.security_deposits',
                    'real_estate.lease_renewals', 'real_estate.utility_billing',
                    'real_estate.leads', 'real_estate.site_visits', 'real_estate.bookings',
                    'real_estate.maintenance_requests', 'real_estate.work_orders',
                ],
                'enterprise' => [
                    'real_estate.properties', 'real_estate.buildings', 'real_estate.units',
                    'real_estate.owners', 'real_estate.property_types', 'real_estate.amenities',
                    'real_estate.documents', 'real_estate.leases', 'real_estate.tenants',
                    'real_estate.rent_invoices', 'real_estate.rent_collection',
                    'real_estate.security_deposits', 'real_estate.lease_renewals',
                    'real_estate.utility_billing', 'real_estate.cam_charges',
                    'real_estate.leads', 'real_estate.site_visits', 'real_estate.bookings',
                    'real_estate.sales_agreements', 'real_estate.installments',
                    'real_estate.handover', 'real_estate.after_sales',
                    'real_estate.maintenance_requests', 'real_estate.work_orders',
                    'real_estate.vendors', 'real_estate.inspections', 'real_estate.assets',
                    'real_estate.preventive_maintenance', 'real_estate.rent_income',
                    'real_estate.property_expenses', 'real_estate.service_charges',
                    'real_estate.tax_reports', 'real_estate.financial_reports',
                    'real_estate.owner_statements', 'real_estate.tenant_statements',
                    'real_estate.occupancy_report', 'real_estate.rent_roll',
                    'real_estate.aging_report', 'real_estate.profit_loss',
                    'real_estate.cash_flow', 'real_estate.portfolio_report',
                    'real_estate.sales_integration', 'real_estate.purchase_integration',
                    'real_estate.finance_integration', 'real_estate.accounting_integration',
                    'real_estate.hr_integration', 'real_estate.crm_integration',
                    'real_estate.inventory_integration',
                ],
            ],

            'manufacturing' => [
                'starter' => [
                    'manufacturing.bom', 'manufacturing.routing', 'manufacturing.work_centers',
                    'manufacturing.production_orders', 'manufacturing.quality_control',
                    'manufacturing.reports',
                ],
                'growth' => [
                    'manufacturing.bom', 'manufacturing.routing', 'manufacturing.work_centers',
                    'manufacturing.production_orders', 'manufacturing.quality_control',
                    'manufacturing.reports', 'manufacturing.sample_management',
                    'manufacturing.batch_tracking', 'manufacturing.expiry_tracking',
                    'manufacturing.serial_number', 'manufacturing.costing',
                    'manufacturing.warranty',
                ],
                'enterprise' => [
                    'manufacturing.bom', 'manufacturing.routing', 'manufacturing.work_centers',
                    'manufacturing.production_orders', 'manufacturing.quality_control',
                    'manufacturing.quality_lab', 'manufacturing.sample_management',
                    'manufacturing.regulatory_compliance', 'manufacturing.batch_tracking',
                    'manufacturing.expiry_tracking', 'manufacturing.serial_number',
                    'manufacturing.costing', 'manufacturing.assembly_line',
                    'manufacturing.mold_management', 'manufacturing.recipe',
                    'manufacturing.cutting', 'manufacturing.welding',
                    'manufacturing.finishing', 'manufacturing.printing',
                    'manufacturing.packaging', 'manufacturing.warranty',
                    'manufacturing.reports',
                ],
            ],

            'healthcare' => [
                'starter' => [
                    'medical.opd', 'medical.ipd', 'medical.pharmacy',
                    'medical.laboratory', 'medical.billing',
                ],
                'growth' => [
                    'medical.opd', 'medical.ipd', 'medical.pharmacy',
                    'medical.laboratory', 'medical.billing', 'medical.emergency',
                    'medical.radiology', 'medical.bloodbank', 'medical.records',
                ],
                'enterprise' => [
                    'medical.opd', 'medical.ipd', 'medical.pharmacy',
                    'medical.laboratory', 'medical.billing', 'medical.emergency',
                    'medical.radiology', 'medical.bloodbank', 'medical.physiotherapy',
                    'medical.dental', 'medical.vaccination', 'medical.ambulance',
                    'medical.diet', 'medical.records',
                ],
            ],

            'training_center' => [
                'starter' => [
                    'training_center.courses', 'training_center.batches',
                    'training_center.students', 'training_center.attendance',
                ],
                'growth' => [
                    'training_center.courses', 'training_center.batches',
                    'training_center.students', 'training_center.attendance',
                    'training_center.classes', 'training_center.exams',
                    'training_center.fees',
                ],
                'enterprise' => [
                    'training_center.courses', 'training_center.batches',
                    'training_center.students', 'training_center.attendance',
                    'training_center.classes', 'training_center.exams',
                    'training_center.certificates', 'training_center.fees',
                    'training_center.reports',
                ],
            ],

            'education' => [
                'starter' => [
                    'education.students', 'education.classes',
                    'education.attendance', 'education.fees',
                ],
                'growth' => [
                    'education.students', 'education.classes',
                    'education.attendance', 'education.fees',
                    'education.exams', 'education.guardians',
                ],
                'enterprise' => [
                    'education.students', 'education.classes',
                    'education.exams', 'education.attendance', 'education.fees',
                    'education.guardians', 'education.analytics',
                ],
            ],

            'retail' => [
                'starter' => [
                    'sales', 'purchase.invoices', 'inventory', 'sales.customers',
                    'pos.terminal', 'pos.register', 'pos.cart', 'pos.checkout',
                    'pos.receipt', 'pos.shift',
                ],
                'growth' => [
                    'sales', 'purchase.invoices', 'inventory', 'sales.customers',
                    'pos.terminal', 'pos.register', 'pos.cart', 'pos.checkout',
                    'pos.receipt', 'pos.shift',
                    'pos.cash', 'pos.card', 'pos.customer', 'pos.discount',
                    'pos.loyalty', 'pos.return', 'pos.refund',
                    'pos.daily_report', 'pos.item_report',
                    'pos.sales_integration', 'pos.inventory_integration',
                    'sales.orders', 'purchase.orders',
                ],
                'enterprise' => [
                    'sales', 'purchase.invoices', 'inventory', 'sales.customers',
                    'pos.terminal', 'pos.register', 'pos.cart', 'pos.checkout',
                    'pos.receipt', 'pos.shift',
                    'pos.cash', 'pos.card', 'pos.customer', 'pos.discount',
                    'pos.loyalty', 'pos.return', 'pos.refund',
                    'pos.daily_report', 'pos.item_report',
                    'pos.sales_integration', 'pos.inventory_integration',
                    'sales.orders', 'purchase.orders',
                    'pos.mobile_payment', 'pos.split_payment', 'pos.cash_drawer',
                    'pos.coupon', 'pos.gift_card', 'pos.exchange',
                    'pos.cashier_report', 'pos.finance_integration',
                    'pos.accounting_integration', 'pos.crm_integration',
                    'inventory.items', 'inventory.stock_ledger',
                    'inventory.warehouses', 'inventory.adjustments',
                    'inventory.batches', 'inventory.transfers',
                    'inventory.barcode', 'inventory.counts', 'inventory.reports',
                    'sales.quotations', 'sales.deliveries', 'sales.returns',
                    'sales.leads', 'sales.reports',
                    'purchase.quotations', 'purchase.requests',
                    'purchase.returns', 'purchase.receipts',
                ],
            ],
        ];
    }

    public static function filterExisting(array $modules): array
    {
        return array_values(array_filter($modules, function ($key) {
            return DB::table('module_registry')->where('key', $key)->exists();
        }));
    }
}
