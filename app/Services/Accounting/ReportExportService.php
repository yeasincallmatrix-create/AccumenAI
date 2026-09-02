<?php

namespace App\Services\Accounting;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * STEP 85 — Enterprise Reporting Export Engine.
 *
 * Native CSV export with extensible PDF/Excel hooks. All exports are
 * tenant-scoped, streaming for large datasets, and return download responses.
 */
class ReportExportService
{
    /**
     * Export a collection as CSV and return a streamed download response.
     */
    public function csv(Collection $data, string $filename, array $headers = []): StreamedResponse
    {
        if ($data->isEmpty()) {
            $headers = $headers ?: ['No Data'];
        }

        $callback = function () use ($data, $headers) {
            $handle = fopen('php://output', 'w');

            if (!empty($headers)) {
                fputcsv($handle, $headers);
            }

            if ($data->isNotEmpty()) {
                $first = $data->first();
                $columns = $headers ? array_keys($headers) : array_keys(is_array($first) ? $first : (array) $first);

                if (empty($headers)) {
                    fputcsv($handle, $columns);
                }

                $data->each(function ($row) use ($handle, $columns) {
                    $row = is_array($row) ? $row : (array) $row;
                    $values = array_map(fn ($col) => $row[$col] ?? '', $columns);
                    fputcsv($handle, $values);
                });
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Export trial balance as CSV.
     */
    public function trialBalanceCsv(int $instituteId, ?int $branchId, string $from, string $to): StreamedResponse
    {
        $service = app(FinancialReportService::class);
        $data = $service->trialBalance($instituteId, $branchId, $to);

        $collection = collect($data['accounts'] ?? [])->map(fn ($a) => [
            'Code' => $a['code'] ?? '',
            'Name' => $a['name'] ?? '',
            'Debit' => number_format($a['debit'] ?? 0, 2),
            'Credit' => number_format($a['credit'] ?? 0, 2),
            'Balance' => number_format($a['balance'] ?? 0, 2),
        ]);

        return $this->csv($collection, "trial_balance_{$from}_{$to}.csv");
    }

    /**
     * Export income statement as CSV.
     */
    public function incomeStatementCsv(int $instituteId, ?int $branchId, string $from, string $to): StreamedResponse
    {
        $service = app(FinancialReportService::class);
        $data = $service->incomeStatement($instituteId, $branchId, $from, $to);

        $rows = collect();
        foreach ($data['revenue'] ?? [] as $item) {
            $rows->push(['Type' => 'Revenue', 'Account' => $item['name'] ?? '', 'Amount' => number_format($item['total'] ?? 0, 2)]);
        }
        foreach ($data['expenses'] ?? [] as $item) {
            $rows->push(['Type' => 'Expense', 'Account' => $item['name'] ?? '', 'Amount' => number_format($item['total'] ?? 0, 2)]);
        }
        $rows->push(['Type' => 'NET INCOME', 'Account' => '', 'Amount' => number_format($data['net_income'] ?? 0, 2)]);

        return $this->csv($rows, "income_statement_{$from}_{$to}.csv");
    }

    /**
     * Export balance sheet as CSV.
     */
    public function balanceSheetCsv(int $instituteId, ?int $branchId, string $asOfDate): StreamedResponse
    {
        $service = app(FinancialReportService::class);
        $data = $service->balanceSheet($instituteId, $branchId, $asOfDate);

        $rows = collect();
        foreach ($data['assets'] ?? [] as $item) {
            $rows->push(['Section' => 'Assets', 'Account' => $item['name'] ?? '', 'Amount' => number_format($item['total'] ?? 0, 2)]);
        }
        foreach ($data['liabilities'] ?? [] as $item) {
            $rows->push(['Section' => 'Liabilities', 'Account' => $item['name'] ?? '', 'Amount' => number_format($item['total'] ?? 0, 2)]);
        }
        foreach ($data['equity'] ?? [] as $item) {
            $rows->push(['Section' => 'Equity', 'Account' => $item['name'] ?? '', 'Amount' => number_format($item['total'] ?? 0, 2)]);
        }

        return $this->csv($rows, "balance_sheet_{$asOfDate}.csv");
    }

    /**
     * Export any report data as CSV with custom filename.
     */
    public function reportCsv(Collection $data, string $reportName, array $columnMap = []): StreamedResponse
    {
        $headers = !empty($columnMap) ? $columnMap : [];
        $filename = preg_replace('/[^a-z0-9_]+/', '_', strtolower($reportName)) . '_' . now()->format('Y-m-d_His') . '.csv';

        return $this->csv($data, $filename, $headers);
    }
}
