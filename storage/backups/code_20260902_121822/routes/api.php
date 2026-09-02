<?php

use App\Http\Controllers\Api\AssessmentController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\CrmContactController;
use App\Http\Controllers\Api\CrmLeadController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\GoodsReceiptController;
use App\Http\Controllers\Api\HrApiController;
use App\Http\Controllers\Api\InventoryApiController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\SalesApiController;
use App\Http\Controllers\Api\StudentController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::get('verify/certificate/{number}', [CertificateController::class, 'verify'])
    ->middleware('throttle:10,1')
    ->name('api.certificate.verify');

Route::middleware(['auth:sanctum', 'ensure.institute.context', 'throttle:60,1'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('profile', [AuthController::class, 'profile']);
    Route::get('institute', [AuthController::class, 'institute']);
    Route::get('branches', [AuthController::class, 'branches']);

    Route::get('students', [StudentController::class, 'index'])->middleware('permission:students.view', 'module_access:education');
    Route::get('students/{id}', [StudentController::class, 'show'])->middleware('permission:students.view', 'module_access:education');

    Route::get('courses', [CourseController::class, 'index'])->middleware('permission:courses.view', 'module_access:education');
    Route::get('courses/{id}', [CourseController::class, 'show'])->middleware('permission:courses.view', 'module_access:education');

    Route::get('batches', [BatchController::class, 'index'])->middleware('permission:batches.view', 'module_access:education');
    Route::get('batches/{id}', [BatchController::class, 'show'])->middleware('permission:batches.view', 'module_access:education');

    Route::get('enrollments', [EnrollmentController::class, 'index'])->middleware('permission:students.view', 'module_access:education');

    Route::get('attendance', [AttendanceController::class, 'index'])->middleware('permission:attendance.view', 'module_access:education');
    Route::post('attendance', [AttendanceController::class, 'store'])->middleware('permission:attendance.manage', 'module_access:education');

    Route::get('assessments', [AssessmentController::class, 'index'])->middleware('permission:education.manage', 'module_access:education');
    Route::get('assessments/{id}/results', [AssessmentController::class, 'results'])->middleware('permission:education.manage', 'module_access:education');

    Route::get('invoices', [InvoiceController::class, 'index'])->middleware('permission:finance.view', 'module_access:finance');
    Route::get('invoices/{id}', [InvoiceController::class, 'show'])->middleware('permission:finance.view', 'module_access:finance');
    Route::get('payments', [PaymentController::class, 'index'])->middleware('permission:finance.view', 'module_access:finance');

    Route::get('crm/contacts', [CrmContactController::class, 'index'])->middleware('permission:crm.view', 'module_access:crm');
    Route::get('crm/contacts/{id}', [CrmContactController::class, 'show'])->middleware('permission:crm.view', 'module_access:crm');
    Route::get('crm/leads', [CrmLeadController::class, 'index'])->middleware('permission:crm.view', 'module_access:crm');
    Route::get('crm/leads/{id}', [CrmLeadController::class, 'show'])->middleware('permission:crm.view', 'module_access:crm');

    Route::get('certificates', [CertificateController::class, 'index'])->middleware('permission:certificates.view', 'module_access:education');

    Route::get('notifications', [NotificationController::class, 'index'])->middleware('permission:notifications.view');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->middleware('permission:notifications.view');

    // Purchase Orders
    Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])
        ->middleware('permission:purchase_order.view', 'module_access:purchase');
    Route::get('purchase-orders/{id}', [PurchaseOrderController::class, 'show'])
        ->middleware('permission:purchase_order.view', 'module_access:purchase');
    Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])
        ->middleware('permission:purchase_order.create', 'module_access:purchase');
    Route::post('purchase-orders/{id}/submit', [PurchaseOrderController::class, 'submit'])
        ->middleware('permission:purchase_order.update', 'module_access:purchase');
    Route::post('purchase-orders/{id}/approve', [PurchaseOrderController::class, 'approve'])
        ->middleware('permission:purchase_order.approve', 'module_access:purchase');
    Route::post('purchase-orders/{id}/cancel', [PurchaseOrderController::class, 'cancel'])
        ->middleware('permission:purchase_order.delete', 'module_access:purchase');

    // Goods Receipts
    Route::get('goods-receipts', [GoodsReceiptController::class, 'index'])
        ->middleware('permission:goods_receipt.view', 'module_access:purchase');
    Route::get('goods-receipts/{id}', [GoodsReceiptController::class, 'show'])
        ->middleware('permission:goods_receipt.view', 'module_access:purchase');
    Route::post('goods-receipts', [GoodsReceiptController::class, 'store'])
        ->middleware('permission:goods_receipt.create', 'module_access:purchase');
    Route::post('goods-receipts/{id}/confirm', [GoodsReceiptController::class, 'confirm'])
        ->middleware('permission:goods_receipt.confirm', 'module_access:purchase');
    Route::post('goods-receipts/{id}/cancel', [GoodsReceiptController::class, 'cancel'])
        ->middleware('permission:goods_receipt.cancel', 'module_access:purchase');
    Route::post('goods-receipts/{id}/reverse', [GoodsReceiptController::class, 'reverse'])
        ->middleware('permission:goods_receipt.reverse', 'module_access:purchase');

    // Sales API
    Route::get('sales/quotations', [SalesApiController::class, 'quotations'])
        ->middleware('permission:sales.view', 'module_access:sales');
    Route::get('sales/orders', [SalesApiController::class, 'orders'])
        ->middleware('permission:sales.view', 'module_access:sales');
    Route::get('sales/deliveries', [SalesApiController::class, 'deliveries'])
        ->middleware('permission:sales.view', 'module_access:sales');
    Route::get('sales/invoices', [SalesApiController::class, 'invoices'])
        ->middleware('permission:sales.view', 'module_access:sales');

    // HR API
    Route::get('hr/employees', [HrApiController::class, 'employees'])
        ->middleware('permission:hr.view', 'module_access:hr');
    Route::get('hr/attendance', [HrApiController::class, 'attendance'])
        ->middleware('permission:hr.view', 'module_access:hr');
    Route::get('hr/payroll', [HrApiController::class, 'payroll'])
        ->middleware('permission:hr.view', 'module_access:hr');

    // Inventory API
    Route::get('inventory/items', [InventoryApiController::class, 'items'])
        ->middleware('permission:inventory.view', 'module_access:inventory');
    Route::get('inventory/stock', [InventoryApiController::class, 'stock'])
        ->middleware('permission:inventory.view', 'module_access:inventory');
    Route::get('inventory/movements', [InventoryApiController::class, 'movements'])
        ->middleware('permission:inventory.view', 'module_access:inventory');
});
