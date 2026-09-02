<?php

use App\Services\Ai\Tools\Core\GetCrmSummaryTool;
use App\Services\Ai\Tools\Core\GetFinancialSummaryTool;
use App\Services\Ai\Tools\Core\IncomeExpenseTool;
use App\Services\Ai\Tools\Education\AttendanceTool;
use App\Services\Ai\Tools\Education\BatchesTool;
use App\Services\Ai\Tools\Education\CertificatesTool;
use App\Services\Ai\Tools\Education\CoursesTool;
use App\Services\Ai\Tools\Education\EnrollmentTool;
use App\Services\Ai\Tools\Education\ExamResultsTool;
use App\Services\Ai\Tools\Education\FeesTool;
use App\Services\Ai\Tools\Education\StudentsTool;
use App\Services\Ai\Tools\Hr\HrAttendanceSummaryTool;
use App\Services\Ai\Tools\Hr\HrEmployeeSummaryTool;
use App\Services\Ai\Tools\Hr\HrLeaveSummaryTool;
use App\Services\Ai\Tools\Hr\HrPayrollSummaryTool;
use App\Services\Ai\Tools\Hr\HrPerformanceSummaryTool;
use App\Services\Ai\Tools\Hr\HrRecruitmentSummaryTool;
use App\Services\Ai\Tools\Hr\HrTrainingSummaryTool;
use App\Services\Ai\Tools\Hr\HrWorkforceSummaryTool;

return [

    /*
    |--------------------------------------------------------------------------
    | Shared (industry-neutral) tools
    |--------------------------------------------------------------------------
    | Tools listed under `core` are offered to EVERY industry. Use this for
    | common business tools (get_income_expense, get_customers, ...) so they
    | are reusable across Education, Real Estate, Transportation, Restaurant,
    | etc. without being duplicated per industry.
    |--------------------------------------------------------------------------
    */

    'core' => [
        IncomeExpenseTool::class,
        GetFinancialSummaryTool::class,
        GetCrmSummaryTool::class,
        HrEmployeeSummaryTool::class,
        HrWorkforceSummaryTool::class,
        HrAttendanceSummaryTool::class,
        HrLeaveSummaryTool::class,
        HrPayrollSummaryTool::class,
        HrRecruitmentSummaryTool::class,
        HrPerformanceSummaryTool::class,
        HrTrainingSummaryTool::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Industry → tool mapping
    |--------------------------------------------------------------------------
    | Each industry maps to the tool classes it may use. To support a new
    | industry, add the industry slug here with its tool classes. Tools are
    | filtered at runtime by the tenant's industry AND feature AND the actor's
    | permission, so the model can only ever call tools the current user is
    | authorized for. The core AI engine never needs to change when an industry
    | is added.
    |--------------------------------------------------------------------------
    */

    'education' => [
        StudentsTool::class,
        CoursesTool::class,
        BatchesTool::class,
        EnrollmentTool::class,
        ExamResultsTool::class,
        AttendanceTool::class,
        FeesTool::class,
        CertificatesTool::class,
    ],

    // Real Estate / Transportation / Restaurant domain tables do NOT exist in
    // the current schema (no properties, vehicles, menu items, orders, etc.).
    // These industries therefore get ONLY the shared core tools until their
    // business modules are built. When a module lands, add its tool classes
    // below — the AI engine, AiService and registry never change.
    'real_estate' => [
        // \App\Services\Ai\Tools\RealEstate\PropertiesTool::class,
        // \App\Services\Ai\Tools\RealEstate\PropertyLeadsTool::class,
        // \App\Services\Ai\Tools\RealEstate\PropertySalesTool::class,
    ],

    'transportation' => [
        // \App\Services\Ai\Tools\Transportation\VehiclesTool::class,
        // \App\Services\Ai\Tools\Transportation\TripsTool::class,
        // \App\Services\Ai\Tools\Transportation\FuelUsageTool::class,
    ],

    'restaurant' => [
        // \App\Services\Ai\Tools\Restaurant\MenuItemsTool::class,
        // \App\Services\Ai\Tools\Restaurant\OrdersTool::class,
        // \App\Services\Ai\Tools\Restaurant\InventoryTool::class,
    ],
];
