<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubcategoryDefaultModuleSeeder extends Seeder
{
    public function run(): void
    {
        // Only module_registry keys are seeded — resolveEnabled() iterates the
        // registry, so any other key would be dead data.
        //
        // Deviations from the draft mapping (verified against module_registry):
        //   medical.inventory      → inventory            (registry has no medical.inventory)
        //   training_center.labs   → training_center.classes (ACTION REQUIRED rename)
        //   sales.invoice          → sales
        //   purchase.bill          → purchase.invoices
        //   sales.add_customer     → sales.customers
        //   purchase.add_vendor    → purchase (root already mandatory elsewhere → dropped here)
        //   sales.sales_receipt    → dropped (no equivalent)
        //   sales.credit_memo      → sales.returns
        //   purchase.vendor_credit → purchase.returns
        //   education.admission/library/transport/hostel/departments/research/batches/activities
        //                         → dropped (no equivalent registry keys)
        $mapping = [
            // ═══ HEALTHCARE ═══
            'healthcare.pharmacy' => [
                ['module' => 'medical.pharmacy', 'category' => 'mandatory'],
                ['module' => 'medical.billing', 'category' => 'mandatory'],
                ['module' => 'medical.records', 'category' => 'default'],
                ['module' => 'inventory', 'category' => 'default'],
                ['module' => 'medical.laboratory', 'category' => 'optional'],
            ],
            'healthcare.hospital' => [
                ['module' => 'medical.opd', 'category' => 'mandatory'],
                ['module' => 'medical.billing', 'category' => 'mandatory'],
                ['module' => 'medical.ipd', 'category' => 'default'],
                ['module' => 'medical.pharmacy', 'category' => 'default'],
                ['module' => 'medical.laboratory', 'category' => 'default'],
                ['module' => 'medical.records', 'category' => 'default'],
                ['module' => 'medical.emergency', 'category' => 'optional'],
                ['module' => 'medical.radiology', 'category' => 'optional'],
                ['module' => 'medical.bloodbank', 'category' => 'optional'],
                ['module' => 'medical.physiotherapy', 'category' => 'optional'],
                ['module' => 'medical.dental', 'category' => 'optional'],
                ['module' => 'medical.vaccination', 'category' => 'optional'],
                ['module' => 'medical.ambulance', 'category' => 'optional'],
                ['module' => 'medical.diet', 'category' => 'optional'],
            ],
            'healthcare.clinic' => [
                ['module' => 'medical.opd', 'category' => 'mandatory'],
                ['module' => 'medical.billing', 'category' => 'mandatory'],
                ['module' => 'medical.pharmacy', 'category' => 'default'],
                ['module' => 'medical.laboratory', 'category' => 'optional'],
                ['module' => 'medical.records', 'category' => 'optional'],
            ],
            'healthcare.diagnostic' => [
                ['module' => 'medical.laboratory', 'category' => 'mandatory'],
                ['module' => 'medical.billing', 'category' => 'mandatory'],
                ['module' => 'medical.records', 'category' => 'default'],
                ['module' => 'medical.radiology', 'category' => 'optional'],
                ['module' => 'medical.pharmacy', 'category' => 'optional'],
            ],
            'healthcare.dental' => [
                ['module' => 'medical.dental', 'category' => 'mandatory'],
                ['module' => 'medical.billing', 'category' => 'mandatory'],
                ['module' => 'medical.opd', 'category' => 'default'],
                ['module' => 'medical.records', 'category' => 'default'],
                ['module' => 'medical.pharmacy', 'category' => 'optional'],
                ['module' => 'medical.radiology', 'category' => 'optional'],
            ],
            'healthcare.physiotherapy' => [
                ['module' => 'medical.physiotherapy', 'category' => 'mandatory'],
                ['module' => 'medical.billing', 'category' => 'mandatory'],
                ['module' => 'medical.opd', 'category' => 'default'],
                ['module' => 'medical.records', 'category' => 'default'],
                ['module' => 'medical.pharmacy', 'category' => 'optional'],
            ],
            'healthcare.veterinary' => [
                ['module' => 'medical.opd', 'category' => 'mandatory'],
                ['module' => 'medical.billing', 'category' => 'mandatory'],
                ['module' => 'medical.pharmacy', 'category' => 'default'],
                ['module' => 'medical.laboratory', 'category' => 'optional'],
                ['module' => 'medical.records', 'category' => 'optional'],
            ],

            // ═══ EDUCATION ═══
            'education.school' => [
                ['module' => 'education.fees', 'category' => 'mandatory'],
                ['module' => 'education.students', 'category' => 'default'],
                ['module' => 'education.classes', 'category' => 'default'],
                ['module' => 'education.exams', 'category' => 'default'],
            ],
            'education.college' => [
                ['module' => 'education.fees', 'category' => 'mandatory'],
                ['module' => 'education.students', 'category' => 'default'],
                ['module' => 'education.classes', 'category' => 'default'],
                ['module' => 'education.exams', 'category' => 'default'],
            ],
            'education.university' => [
                ['module' => 'education.fees', 'category' => 'mandatory'],
                ['module' => 'education.students', 'category' => 'default'],
                ['module' => 'education.classes', 'category' => 'default'],
                ['module' => 'education.exams', 'category' => 'default'],
            ],
            'education.coaching' => [
                ['module' => 'education.fees', 'category' => 'mandatory'],
                ['module' => 'education.students', 'category' => 'default'],
                ['module' => 'education.exams', 'category' => 'optional'],
            ],
            'education.kindergarten' => [
                ['module' => 'education.fees', 'category' => 'mandatory'],
                ['module' => 'education.classes', 'category' => 'default'],
            ],

            // ═══ TRAINING CENTER ═══
            'training_center.vocational' => [
                ['module' => 'training_center.courses', 'category' => 'mandatory'],
                ['module' => 'training_center.batches', 'category' => 'mandatory'],
                ['module' => 'training_center.students', 'category' => 'default'],
                ['module' => 'training_center.certificates', 'category' => 'default'],
                ['module' => 'training_center.exams', 'category' => 'optional'],
                ['module' => 'training_center.attendance', 'category' => 'optional'],
            ],
            'training_center.it_training' => [
                ['module' => 'training_center.courses', 'category' => 'mandatory'],
                ['module' => 'training_center.batches', 'category' => 'mandatory'],
                ['module' => 'training_center.students', 'category' => 'default'],
                ['module' => 'training_center.certificates', 'category' => 'default'],
                ['module' => 'training_center.classes', 'category' => 'default'],
                ['module' => 'training_center.exams', 'category' => 'optional'],
            ],
            'training_center.language_center' => [
                ['module' => 'training_center.courses', 'category' => 'mandatory'],
                ['module' => 'training_center.batches', 'category' => 'mandatory'],
                ['module' => 'training_center.students', 'category' => 'default'],
                ['module' => 'training_center.certificates', 'category' => 'default'],
                ['module' => 'training_center.exams', 'category' => 'optional'],
                ['module' => 'training_center.attendance', 'category' => 'optional'],
            ],

            // ═══ RETAIL ═══
            'retail.grocery' => [
                ['module' => 'sales', 'category' => 'mandatory'],
                ['module' => 'purchase.invoices', 'category' => 'mandatory'],
                ['module' => 'sales.customers', 'category' => 'default'],
                ['module' => 'inventory', 'category' => 'default'],
                // POS Phase 2 — Payment & Sessions
                ['module' => 'pos.cash', 'category' => 'default'],
                ['module' => 'pos.card', 'category' => 'default'],
                ['module' => 'pos.mobile_payment', 'category' => 'optional'],
                ['module' => 'pos.split_payment', 'category' => 'optional'],
                ['module' => 'pos.shift', 'category' => 'default'],
                ['module' => 'pos.cash_drawer', 'category' => 'optional'],
                // POS Phase 3 — Customer & Promotions
                ['module' => 'pos.customer', 'category' => 'default'],
                ['module' => 'pos.loyalty', 'category' => 'default'],
                ['module' => 'pos.discount', 'category' => 'default'],
                ['module' => 'pos.coupon', 'category' => 'optional'],
                ['module' => 'pos.gift_card', 'category' => 'optional'],
                // POS Phase 4 — Returns & Reports
                ['module' => 'pos.return', 'category' => 'default'],
                ['module' => 'pos.refund', 'category' => 'default'],
                ['module' => 'pos.exchange', 'category' => 'optional'],
                ['module' => 'pos.daily_report', 'category' => 'default'],
                ['module' => 'pos.item_report', 'category' => 'default'],
                ['module' => 'pos.cashier_report', 'category' => 'optional'],
                // POS Phase 5 — Integrations
                ['module' => 'pos.inventory_integration', 'category' => 'default'],
                ['module' => 'pos.sales_integration', 'category' => 'default'],
                ['module' => 'pos.finance_integration', 'category' => 'optional'],
                ['module' => 'pos.accounting_integration', 'category' => 'optional'],
                ['module' => 'pos.crm_integration', 'category' => 'optional'],
            ],
            'retail.electronics' => [
                ['module' => 'sales', 'category' => 'mandatory'],
                ['module' => 'purchase.invoices', 'category' => 'mandatory'],
                ['module' => 'sales.customers', 'category' => 'default'],
                ['module' => 'inventory', 'category' => 'default'],
                ['module' => 'sales.returns', 'category' => 'optional'],
                ['module' => 'purchase.returns', 'category' => 'optional'],
            ],
            'retail.clothing' => [
                ['module' => 'sales', 'category' => 'mandatory'],
                ['module' => 'purchase.invoices', 'category' => 'mandatory'],
                ['module' => 'sales.customers', 'category' => 'default'],
                ['module' => 'inventory', 'category' => 'default'],
                ['module' => 'sales.returns', 'category' => 'optional'],
            ],
            'retail.restaurant' => [
                ['module' => 'sales', 'category' => 'mandatory'],
                ['module' => 'purchase.invoices', 'category' => 'mandatory'],
                ['module' => 'sales.customers', 'category' => 'default'],
                ['module' => 'inventory', 'category' => 'default'],
                ['module' => 'pos', 'category' => 'optional'],
                // POS Phase 2 — Payment & Sessions
                ['module' => 'pos.cash', 'category' => 'default'],
                ['module' => 'pos.card', 'category' => 'default'],
                ['module' => 'pos.mobile_payment', 'category' => 'optional'],
                ['module' => 'pos.split_payment', 'category' => 'optional'],
                ['module' => 'pos.shift', 'category' => 'default'],
                ['module' => 'pos.cash_drawer', 'category' => 'optional'],
                // POS Phase 3 — Customer & Promotions
                ['module' => 'pos.customer', 'category' => 'default'],
                ['module' => 'pos.loyalty', 'category' => 'default'],
                ['module' => 'pos.discount', 'category' => 'default'],
                ['module' => 'pos.coupon', 'category' => 'optional'],
                ['module' => 'pos.gift_card', 'category' => 'optional'],
                // POS Phase 4 — Returns & Reports
                ['module' => 'pos.return', 'category' => 'default'],
                ['module' => 'pos.refund', 'category' => 'default'],
                ['module' => 'pos.exchange', 'category' => 'optional'],
                ['module' => 'pos.daily_report', 'category' => 'default'],
                ['module' => 'pos.item_report', 'category' => 'default'],
                ['module' => 'pos.cashier_report', 'category' => 'optional'],
                // POS Phase 5 — Integrations
                ['module' => 'pos.inventory_integration', 'category' => 'default'],
                ['module' => 'pos.sales_integration', 'category' => 'default'],
                ['module' => 'pos.finance_integration', 'category' => 'optional'],
                ['module' => 'pos.accounting_integration', 'category' => 'optional'],
                ['module' => 'pos.crm_integration', 'category' => 'optional'],
            ],

            // ═══ MANUFACTURING ═══
            'manufacturing.general' => [
                ['module' => 'sales', 'category' => 'mandatory'],
                ['module' => 'purchase.invoices', 'category' => 'mandatory'],
                ['module' => 'sales.customers', 'category' => 'default'],
                ['module' => 'inventory', 'category' => 'default'],
                ['module' => 'manufacturing', 'category' => 'default'],
                ['module' => 'manufacturing.bom', 'category' => 'default'],
                ['module' => 'manufacturing.routing', 'category' => 'default'],
                ['module' => 'manufacturing.work_centers', 'category' => 'default'],
                ['module' => 'manufacturing.production_orders', 'category' => 'default'],
                ['module' => 'manufacturing.costing', 'category' => 'default'],
                ['module' => 'manufacturing.reports', 'category' => 'default'],
                ['module' => 'manufacturing.quality_control', 'category' => 'optional'],
                ['module' => 'manufacturing.quality_lab', 'category' => 'optional'],
                ['module' => 'manufacturing.sample_management', 'category' => 'optional'],
                ['module' => 'manufacturing.regulatory_compliance', 'category' => 'optional'],
                ['module' => 'manufacturing.batch_tracking', 'category' => 'optional'],
                ['module' => 'manufacturing.expiry_tracking', 'category' => 'optional'],
                ['module' => 'manufacturing.serial_number', 'category' => 'optional'],
                ['module' => 'manufacturing.assembly_line', 'category' => 'optional'],
                ['module' => 'manufacturing.mold_management', 'category' => 'optional'],
                ['module' => 'manufacturing.recipe', 'category' => 'optional'],
                ['module' => 'manufacturing.cutting', 'category' => 'optional'],
                ['module' => 'manufacturing.welding', 'category' => 'optional'],
                ['module' => 'manufacturing.finishing', 'category' => 'optional'],
                ['module' => 'manufacturing.printing', 'category' => 'optional'],
                ['module' => 'manufacturing.packaging', 'category' => 'optional'],
                ['module' => 'manufacturing.warranty', 'category' => 'optional'],
                ['module' => 'purchase.returns', 'category' => 'optional'],
            ],
            'manufacturing.food_processing' => [
                ['module' => 'sales', 'category' => 'mandatory'],
                ['module' => 'purchase.invoices', 'category' => 'mandatory'],
                ['module' => 'sales.customers', 'category' => 'default'],
                ['module' => 'inventory', 'category' => 'default'],
                ['module' => 'manufacturing', 'category' => 'default'],
                ['module' => 'manufacturing.bom', 'category' => 'default'],
                ['module' => 'manufacturing.routing', 'category' => 'default'],
                ['module' => 'manufacturing.work_centers', 'category' => 'default'],
                ['module' => 'manufacturing.production_orders', 'category' => 'default'],
                ['module' => 'manufacturing.costing', 'category' => 'default'],
                ['module' => 'manufacturing.reports', 'category' => 'default'],
                ['module' => 'manufacturing.quality_control', 'category' => 'optional'],
                ['module' => 'manufacturing.quality_lab', 'category' => 'optional'],
                ['module' => 'manufacturing.sample_management', 'category' => 'optional'],
                ['module' => 'manufacturing.regulatory_compliance', 'category' => 'optional'],
                ['module' => 'manufacturing.batch_tracking', 'category' => 'optional'],
                ['module' => 'manufacturing.expiry_tracking', 'category' => 'optional'],
                ['module' => 'manufacturing.serial_number', 'category' => 'optional'],
                ['module' => 'manufacturing.assembly_line', 'category' => 'optional'],
                ['module' => 'manufacturing.mold_management', 'category' => 'optional'],
                ['module' => 'manufacturing.recipe', 'category' => 'optional'],
                ['module' => 'manufacturing.cutting', 'category' => 'optional'],
                ['module' => 'manufacturing.welding', 'category' => 'optional'],
                ['module' => 'manufacturing.finishing', 'category' => 'optional'],
                ['module' => 'manufacturing.printing', 'category' => 'optional'],
                ['module' => 'manufacturing.packaging', 'category' => 'optional'],
                ['module' => 'manufacturing.warranty', 'category' => 'optional'],
                ['module' => 'purchase.returns', 'category' => 'optional'],
            ],

            // ═══ REAL ESTATE ═══
            'real_estate.property' => [
                ['module' => 'real_estate.properties', 'category' => 'mandatory'],
                ['module' => 'real_estate.owners', 'category' => 'mandatory'],
                ['module' => 'real_estate.leads', 'category' => 'default'],
                ['module' => 'real_estate.site_visits', 'category' => 'default'],
                ['module' => 'real_estate.bookings', 'category' => 'default'],
                ['module' => 'real_estate.sales_agreements', 'category' => 'default'],
                ['module' => 'real_estate.installments', 'category' => 'default'],
                ['module' => 'real_estate.handover', 'category' => 'optional'],
                ['module' => 'real_estate.after_sales', 'category' => 'optional'],
            ],
            'real_estate.rental' => [
                ['module' => 'real_estate.properties', 'category' => 'mandatory'],
                ['module' => 'real_estate.leases', 'category' => 'mandatory'],
                ['module' => 'real_estate.tenants', 'category' => 'mandatory'],
                ['module' => 'real_estate.rent_invoices', 'category' => 'default'],
                ['module' => 'real_estate.rent_collection', 'category' => 'default'],
                ['module' => 'real_estate.security_deposits', 'category' => 'optional'],
                ['module' => 'real_estate.lease_renewals', 'category' => 'optional'],
                ['module' => 'real_estate.utility_billing', 'category' => 'optional'],
                ['module' => 'real_estate.maintenance_requests', 'category' => 'default'],
                ['module' => 'real_estate.work_orders', 'category' => 'default'],
                ['module' => 'real_estate.inspections', 'category' => 'optional'],
                ['module' => 'real_estate.preventive_maintenance', 'category' => 'optional'],
                // Phase 5A — Accounting
                ['module' => 'real_estate.rent_income', 'category' => 'default'],
                ['module' => 'real_estate.property_expenses', 'category' => 'default'],
                ['module' => 'real_estate.owner_statements', 'category' => 'default'],
                ['module' => 'real_estate.tenant_statements', 'category' => 'default'],
                ['module' => 'real_estate.service_charges', 'category' => 'optional'],
                ['module' => 'real_estate.tax_reports', 'category' => 'optional'],
                ['module' => 'real_estate.financial_reports', 'category' => 'optional'],
                // Phase 5B — Reports
                ['module' => 'real_estate.occupancy_report', 'category' => 'default'],
                ['module' => 'real_estate.rent_roll', 'category' => 'default'],
                ['module' => 'real_estate.aging_report', 'category' => 'optional'],
                ['module' => 'real_estate.profit_loss', 'category' => 'optional'],
                ['module' => 'real_estate.cash_flow', 'category' => 'optional'],
                ['module' => 'real_estate.portfolio_report', 'category' => 'optional'],
                // Phase 6 — Integrations
                ['module' => 'real_estate.sales_integration', 'category' => 'optional'],
                ['module' => 'real_estate.purchase_integration', 'category' => 'optional'],
                ['module' => 'real_estate.finance_integration', 'category' => 'optional'],
                ['module' => 'real_estate.accounting_integration', 'category' => 'optional'],
                ['module' => 'real_estate.hr_integration', 'category' => 'optional'],
                ['module' => 'real_estate.crm_integration', 'category' => 'optional'],
                ['module' => 'real_estate.inventory_integration', 'category' => 'optional'],
            ],

            // ═══ RESTAURANT (Phase 1) ═══
            'restaurant.fine_dining' => [
                ['module' => 'restaurant.menu', 'category' => 'mandatory'],
                ['module' => 'restaurant.menu_category', 'category' => 'mandatory'],
                ['module' => 'restaurant.menu_item', 'category' => 'mandatory'],
                ['module' => 'restaurant.table', 'category' => 'mandatory'],
                ['module' => 'restaurant.table_layout', 'category' => 'default'],
                ['module' => 'restaurant.reservation', 'category' => 'mandatory'],
                ['module' => 'restaurant.dine_in', 'category' => 'mandatory'],
                ['module' => 'restaurant.takeaway', 'category' => 'default'],
                ['module' => 'restaurant.delivery', 'category' => 'optional'],
                ['module' => 'restaurant.order', 'category' => 'mandatory'],
                ['module' => 'restaurant.order_tracking', 'category' => 'default'],
                ['module' => 'restaurant.pre_order', 'category' => 'optional'],
                ['module' => 'restaurant.kitchen', 'category' => 'mandatory'],
                ['module' => 'restaurant.kds', 'category' => 'mandatory'],
                ['module' => 'restaurant.kot', 'category' => 'mandatory'],
                ['module' => 'restaurant.chef', 'category' => 'default'],
                ['module' => 'restaurant.station', 'category' => 'default'],
                ['module' => 'restaurant.recipe', 'category' => 'default'],
                ['module' => 'pos.cart', 'category' => 'mandatory'],
                ['module' => 'pos.checkout', 'category' => 'mandatory'],
            ],
            'restaurant.casual' => [
                ['module' => 'restaurant.menu', 'category' => 'mandatory'],
                ['module' => 'restaurant.menu_category', 'category' => 'mandatory'],
                ['module' => 'restaurant.menu_item', 'category' => 'mandatory'],
                ['module' => 'restaurant.table', 'category' => 'mandatory'],
                ['module' => 'restaurant.table_layout', 'category' => 'default'],
                ['module' => 'restaurant.reservation', 'category' => 'default'],
                ['module' => 'restaurant.dine_in', 'category' => 'mandatory'],
                ['module' => 'restaurant.takeaway', 'category' => 'default'],
                ['module' => 'restaurant.delivery', 'category' => 'default'],
                ['module' => 'restaurant.order', 'category' => 'mandatory'],
                ['module' => 'restaurant.order_tracking', 'category' => 'default'],
                ['module' => 'restaurant.pre_order', 'category' => 'optional'],
                ['module' => 'restaurant.kitchen', 'category' => 'mandatory'],
                ['module' => 'restaurant.kds', 'category' => 'mandatory'],
                ['module' => 'restaurant.kot', 'category' => 'mandatory'],
                ['module' => 'restaurant.chef', 'category' => 'default'],
                ['module' => 'restaurant.station', 'category' => 'default'],
                ['module' => 'restaurant.recipe', 'category' => 'default'],
                ['module' => 'pos.cart', 'category' => 'mandatory'],
                ['module' => 'pos.checkout', 'category' => 'mandatory'],
            ],
            'restaurant.fast_food' => [
                ['module' => 'restaurant.menu', 'category' => 'mandatory'],
                ['module' => 'restaurant.menu_category', 'category' => 'mandatory'],
                ['module' => 'restaurant.menu_item', 'category' => 'mandatory'],
                ['module' => 'restaurant.table', 'category' => 'optional'],
                ['module' => 'restaurant.takeaway', 'category' => 'mandatory'],
                ['module' => 'restaurant.delivery', 'category' => 'mandatory'],
                ['module' => 'restaurant.order', 'category' => 'mandatory'],
                ['module' => 'restaurant.order_tracking', 'category' => 'default'],
                ['module' => 'restaurant.pre_order', 'category' => 'optional'],
                ['module' => 'restaurant.kitchen', 'category' => 'mandatory'],
                ['module' => 'restaurant.kds', 'category' => 'mandatory'],
                ['module' => 'restaurant.kot', 'category' => 'mandatory'],
                ['module' => 'pos.cart', 'category' => 'mandatory'],
                ['module' => 'pos.checkout', 'category' => 'mandatory'],
            ],
            'restaurant.cafe' => [
                ['module' => 'restaurant.menu', 'category' => 'mandatory'],
                ['module' => 'restaurant.menu_category', 'category' => 'mandatory'],
                ['module' => 'restaurant.menu_item', 'category' => 'mandatory'],
                ['module' => 'restaurant.table', 'category' => 'default'],
                ['module' => 'restaurant.table_layout', 'category' => 'optional'],
                ['module' => 'restaurant.reservation', 'category' => 'optional'],
                ['module' => 'restaurant.dine_in', 'category' => 'mandatory'],
                ['module' => 'restaurant.takeaway', 'category' => 'default'],
                ['module' => 'restaurant.delivery', 'category' => 'default'],
                ['module' => 'restaurant.order', 'category' => 'mandatory'],
                ['module' => 'restaurant.order_tracking', 'category' => 'optional'],
                ['module' => 'restaurant.pre_order', 'category' => 'optional'],
                ['module' => 'restaurant.kitchen', 'category' => 'mandatory'],
                ['module' => 'restaurant.kot', 'category' => 'mandatory'],
                ['module' => 'restaurant.kds', 'category' => 'optional'],
                ['module' => 'restaurant.recipe', 'category' => 'optional'],
                ['module' => 'pos.cart', 'category' => 'mandatory'],
                ['module' => 'pos.checkout', 'category' => 'mandatory'],
            ],
            'restaurant.bakery' => [
                ['module' => 'restaurant.menu', 'category' => 'mandatory'],
                ['module' => 'restaurant.menu_category', 'category' => 'mandatory'],
                ['module' => 'restaurant.menu_item', 'category' => 'mandatory'],
                ['module' => 'restaurant.dine_in', 'category' => 'optional'],
                ['module' => 'restaurant.takeaway', 'category' => 'mandatory'],
                ['module' => 'restaurant.delivery', 'category' => 'default'],
                ['module' => 'restaurant.order', 'category' => 'mandatory'],
                ['module' => 'restaurant.order_tracking', 'category' => 'optional'],
                ['module' => 'restaurant.pre_order', 'category' => 'default'],
                ['module' => 'restaurant.kitchen', 'category' => 'mandatory'],
                ['module' => 'restaurant.recipe', 'category' => 'mandatory'],
                ['module' => 'restaurant.kot', 'category' => 'optional'],
                ['module' => 'pos.cart', 'category' => 'mandatory'],
                ['module' => 'pos.checkout', 'category' => 'mandatory'],
            ],
            'restaurant.food_court' => [
                ['module' => 'restaurant.menu', 'category' => 'mandatory'],
                ['module' => 'restaurant.menu_category', 'category' => 'mandatory'],
                ['module' => 'restaurant.menu_item', 'category' => 'mandatory'],
                ['module' => 'restaurant.table', 'category' => 'optional'],
                ['module' => 'restaurant.dine_in', 'category' => 'mandatory'],
                ['module' => 'restaurant.takeaway', 'category' => 'mandatory'],
                ['module' => 'restaurant.delivery', 'category' => 'optional'],
                ['module' => 'restaurant.order', 'category' => 'mandatory'],
                ['module' => 'restaurant.order_tracking', 'category' => 'default'],
                ['module' => 'restaurant.pre_order', 'category' => 'optional'],
                ['module' => 'restaurant.kitchen', 'category' => 'mandatory'],
                ['module' => 'restaurant.kds', 'category' => 'mandatory'],
                ['module' => 'restaurant.kot', 'category' => 'mandatory'],
                ['module' => 'pos.cart', 'category' => 'mandatory'],
                ['module' => 'pos.checkout', 'category' => 'mandatory'],
            ],
            'restaurant.cloud_kitchen' => [
                ['module' => 'restaurant.menu', 'category' => 'mandatory'],
                ['module' => 'restaurant.menu_category', 'category' => 'mandatory'],
                ['module' => 'restaurant.menu_item', 'category' => 'mandatory'],
                ['module' => 'restaurant.delivery', 'category' => 'mandatory'],
                ['module' => 'restaurant.order', 'category' => 'mandatory'],
                ['module' => 'restaurant.order_tracking', 'category' => 'mandatory'],
                ['module' => 'restaurant.pre_order', 'category' => 'optional'],
                ['module' => 'restaurant.kitchen', 'category' => 'mandatory'],
                ['module' => 'restaurant.kds', 'category' => 'mandatory'],
                ['module' => 'restaurant.kot', 'category' => 'mandatory'],
                ['module' => 'restaurant.recipe', 'category' => 'default'],
                ['module' => 'pos.cart', 'category' => 'mandatory'],
                ['module' => 'pos.checkout', 'category' => 'mandatory'],
                ['module' => 'restaurant.reservation', 'category' => 'hidden'],
            ],
        ];

        $registryKeys = DB::table('module_registry')->pluck('key')->flip();

        $count = 0;
        $skipped = [];
        foreach ($mapping as $key => $modules) {
            [$industry, $subcategory] = explode('.', $key, 2);

            $sub = DB::table('industry_subcategories')
                ->where('industry_key', $industry)
                ->where('subcategory_key', $subcategory)
                ->first();

            if (! $sub) {
                $this->command->warn("Sub-category not found: {$key}");

                continue;
            }

            foreach ($modules as $m) {
                if (! isset($registryKeys[$m['module']])) {
                    $skipped[] = "{$key} → {$m['module']}";

                    continue;
                }

                DB::table('subcategory_default_modules')->updateOrInsert(
                    ['subcategory_id' => $sub->id, 'module_key' => $m['module']],
                    ['category' => $m['category'], 'created_at' => now(), 'updated_at' => now()]
                );
                $count++;
            }
        }

        if ($skipped) {
            $this->command->warn('Skipped keys not in module_registry: '.implode(', ', $skipped));
        }

        $this->command->info('Sub-category default modules seeded: '.$count);
    }
}
