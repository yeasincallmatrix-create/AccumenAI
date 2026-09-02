@push('styles')
<style>
    .report-sheet {
        margin: 24px auto;
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 10px;
        padding: 24px 28px;
        color: #212529;
    }
    .report-header {
        text-align: center;
        border-bottom: 3px double #212529;
        padding-bottom: 14px;
        margin-bottom: 18px;
    }
    .report-header .institute-name {
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: 0.02em;
    }
    .report-header .institute-address {
        font-size: 0.85rem;
        color: #6c757d;
    }
    .report-header .report-title {
        font-size: 0.95rem;
        text-transform: uppercase;
        letter-spacing: 0.2em;
        margin-top: 4px;
    }
    .meta-line {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 4px 18px;
        font-size: 0.85rem;
    }
    .meta-line .label {
        color: #6c757d;
    }
    .sheet-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.75rem;
    }
    .sheet-table th,
    .sheet-table td {
        border: 1px solid #adb5bd;
        padding: 4px 6px;
        text-align: center;
        vertical-align: middle;
    }
    .sheet-table th {
        background: #f1f3f5;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .sheet-table td.student {
        text-align: left;
    }
    .student-name {
        font-weight: 600;
        white-space: nowrap;
    }
    .student-id {
        color: #6c757d;
        font-size: 0.68rem;
    }
    .text-left {
        text-align: left;
    }
    .totals-row {
        font-weight: 700;
        background: #f8f9fa;
    }
    .status-chip {
        display: inline-block;
        padding: 1px 8px;
        border-radius: 999px;
        font-size: 0.62rem;
        font-weight: 600;
        white-space: nowrap;
    }
    .status-chip.present { background: #d1e7dd; color: #0f5132; }
    .status-chip.absent  { background: #f8d7da; color: #842029; }
    .status-chip.late    { background: #fff3cd; color: #664d03; }
    .status-chip.leave   { background: #cff4fc; color: #055160; }
    .status-chip.na      { background: #e9ecef; color: #495057; }
    .summary-cards {
        display: flex;
        flex-wrap: wrap;
        gap: 14px 28px;
        justify-content: center;
        margin-bottom: 18px;
    }
    .summary-card {
        min-width: 86px;
        text-align: center;
    }
    .summary-card .label {
        font-size: 0.68rem;
        text-transform: uppercase;
        color: #6c757d;
    }
    .summary-card .value {
        font-size: 1.35rem;
        font-weight: 700;
    }
    .notes {
        font-size: 0.72rem;
        color: #495057;
        margin-top: 10px;
    }
    .signature-block {
        margin-top: 40px;
        display: flex;
        justify-content: space-between;
        gap: 24px;
    }
    .signature-item {
        text-align: center;
        width: 200px;
    }
    .signature-item .line {
        border-bottom: 1px solid #6c757d;
        margin-bottom: 6px;
        height: 46px;
    }
    .signature-item .label {
        font-size: 0.8rem;
        color: #6c757d;
    }

    @media print {
        @page {
            size: A4 portrait;
            margin: 10mm;
        }
        .report-sheet {
            margin: 0;
            border: none;
            border-radius: 0;
            padding: 0;
        }
        .no-print {
            display: none !important;
        }
        body {
            background: #fff !important;
        }
        .topbar,
        .standalone-page-title {
            display: none !important;
        }
    }
</style>
@endpush