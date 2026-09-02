<?php

namespace App\Services\Ai\Tools\Core;

use App\Services\Accounting\FinancialReportService;
use App\Services\Accounting\ReceivablesPayablesService;
use App\Services\Ai\AiContext;
use App\Services\Ai\Tools\AbstractAiTool;

/**
 * Industry-neutral financial summary backed by the Step 32 finance core.
 *
 * Reuses FinancialReportService + ReceivablesPayablesService (the single source
 * of truth for the ledger) instead of duplicating accounting logic. Read-only:
 * it only aggregates posted journal entries and never mutates anything. The
 * effective branch mirrors the actor's restriction (a branch-restricted user
 * can never ask for another branch).
 */
class GetFinancialSummaryTool extends AbstractAiTool
{
    public function __construct(
        protected FinancialReportService $reports,
        protected ReceivablesPayablesService $arp,
    ) {}

    public function name(): string
    {
        return 'get_financial_summary';
    }

    public function description(): string
    {
        return 'Summarise the organisation\'s financial position from the double-entry ledger: income statement '
            .'(income, expense, net), balance-sheet totals (assets, liabilities, equity), receivables and payables '
            .'totals with the largest customer and supplier balances, and cash/bank balances. Can be narrowed to a '
            .'date range, a snapshot date or a single section. Returns small bounded summaries, never raw ledgers.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'section' => ['type' => 'string', 'enum' => ['overview', 'income', 'balance', 'receivables', 'cash_bank']],
                'branch_id' => ['type' => 'integer'],
                'from' => ['type' => 'string', 'description' => 'YYYY-MM-DD income statement start'],
                'to' => ['type' => 'string', 'description' => 'YYYY-MM-DD income statement end'],
                'as_of' => ['type' => 'string', 'description' => 'YYYY-MM-DD snapshot date'],
                'limit' => ['type' => 'integer', 'description' => 'Rows to return, 1-50'],
            ],
        ];
    }

    public function permission(): string
    {
        return 'finance.view';
    }

    public function handle(array $args, AiContext $context): array
    {
        $this->guard($context);

        $instituteId = $context->instituteId();
        $branchId = $this->branchId($context)
            ?? (($args['branch_id'] ?? null) !== null ? (int) $args['branch_id'] : null);

        $from = $this->dateArg($args, 'from');
        $to = $this->dateArg($args, 'to');
        $asOf = $this->dateArg($args, 'as_of');

        $section = in_array($args['section'] ?? 'overview', ['overview', 'income', 'balance', 'receivables', 'cash_bank'], true)
            ? $args['section']
            : 'overview';

        $summary = [];

        if (in_array($section, ['overview', 'income'], true)) {
            $is = $this->reports->incomeStatement(
                $instituteId,
                $branchId,
                $from?->toDateString(),
                $to?->toDateString(),
            );
            $summary['income_statement'] = [
                'total_income' => round($is['total_income'], 2),
                'total_expense' => round($is['total_expense'], 2),
                'net' => round($is['net'], 2),
            ];
            if ($from !== null || $to !== null) {
                $summary['income_statement']['period'] = trim(
                    ($from?->toDateString() ?? '...').' to '.($to?->toDateString() ?? '...')
                );
            }
        }

        if (in_array($section, ['overview', 'balance'], true)) {
            $bs = $this->reports->balanceSheet($instituteId, $branchId, $asOf?->toDateString());
            $summary['balance_sheet'] = [
                'total_assets' => round($bs['total_assets'], 2),
                'total_liabilities' => round($bs['total_liabilities'], 2),
                'total_equity' => round($bs['total_equity'], 2),
                'net_income' => round($bs['net_income'], 2),
            ];
            if ($asOf !== null) {
                $summary['balance_sheet']['as_of'] = $asOf->toDateString();
            }
        }

        if (in_array($section, ['overview', 'receivables'], true)) {
            $arp = $this->arp->totals($instituteId, $branchId, $asOf?->toDateString());
            $summary['receivables_payables'] = [
                'total_receivable' => round($arp['receivable'], 2),
                'total_payable' => round($arp['payable'], 2),
                'net' => round($arp['net'], 2),
            ];

            $limit = $this->limit($args, 5, 10);
            $summary['top_customers'] = $this->arp->customerBalances($instituteId, $branchId, $asOf?->toDateString())
                ->sortByDesc('net')
                ->take($limit)
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'name' => $row->name,
                    'net_balance' => round((float) $row->net, 2),
                ])
                ->values()
                ->all();
            $summary['top_suppliers'] = $this->arp->supplierBalances($instituteId, $branchId, $asOf?->toDateString())
                ->sortByDesc('net')
                ->take($limit)
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'name' => $row->name,
                    'net_balance' => round((float) $row->net, 2),
                ])
                ->values()
                ->all();
        }

        if (in_array($section, ['overview', 'cash_bank'], true)) {
            $summary['cash_bank'] = $this->reports->cashBankSummary($instituteId, $branchId, $asOf?->toDateString())
                ->take($this->limit($args, 20, 50))
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'name' => $row->name,
                    'type' => $row->is_bank ? 'bank' : 'cash',
                    'balance' => round((float) $row->balance, 2),
                ])
                ->values()
                ->all();
        }

        return $this->result($summary);
    }
}
