<?php

namespace App\Services\Ai\Tools\Core;

use App\Models\Transaction;
use App\Services\Ai\AiContext;
use App\Services\Ai\Tools\AbstractAiTool;
use Illuminate\Support\Carbon;

/**
 * Industry-neutral income/expense summary backed by the general ledger.
 *
 * The `transactions` table (with `account_heads` and `branches`) is shared by
 * every industry on the platform, so this tool is registered under the `core`
 * list and offered to every tenant. Only rows of the authenticated institute
 * are ever queried; the branch and date filters are optional.
 */
class IncomeExpenseTool extends AbstractAiTool
{
    public function name(): string
    {
        return 'get_income_expense';
    }

    public function description(): string
    {
        return 'Summarise income and expense from the general ledger: total income, total expense, net, and the '
            .'number of transactions. Can be grouped by account head or by month, and filtered by branch, type, '
            .'account head, or transaction date range. Returns a small summary plus recent transactions.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['income', 'expense']],
                'branch_id' => ['type' => 'integer'],
                'account_head_id' => ['type' => 'integer'],
                'from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'group_by' => ['type' => 'string', 'enum' => ['none', 'head', 'month']],
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

        $base = Transaction::query()->where('transactions.institute_id', $context->instituteId());

        if (($branchId = $this->branchId($context)) !== null) {
            $base->where('transactions.branch_id', $branchId);
        }

        if (($type = $args['type'] ?? null) !== null) {
            $base->where('transactions.type', $type);
        }
        if (($branchId = $args['branch_id'] ?? null) !== null) {
            $base->where('transactions.branch_id', (int) $branchId);
        }
        if (($headId = $args['account_head_id'] ?? null) !== null) {
            $base->where('transactions.account_head_id', (int) $headId);
        }
        if (($from = $this->dateArg($args, 'from')) !== null) {
            $base->where('transactions.transaction_date', '>=', $from);
        }
        if (($to = $this->dateArg($args, 'to')) !== null) {
            $base->where('transactions.transaction_date', '<=', $to->endOfDay());
        }

        $income = round((float) (clone $base)->where('transactions.type', 'income')->sum('transactions.amount'), 2);
        $expense = round((float) (clone $base)->where('transactions.type', 'expense')->sum('transactions.amount'), 2);

        $summary = [
            'total_transactions' => (clone $base)->count(),
            'total_income' => $income,
            'total_expense' => $expense,
            'net' => round($income - $expense, 2),
            'currency' => 'BDT',
        ];

        if (($from = $this->dateArg($args, 'from')) !== null || ($to = $this->dateArg($args, 'to')) !== null) {
            $summary['period'] = trim(
                ($this->dateArg($args, 'from')?->toDateString() ?? '...')
                .' to '
                .($this->dateArg($args, 'to')?->toDateString() ?? '...')
            );
        }

        $group = $this->groupBy($args, ['head', 'month'], 'none');
        if ($group === 'head') {
            $summary['by_head'] = (clone $base)
                ->join('account_heads', 'account_heads.id', '=', 'transactions.account_head_id')
                ->selectRaw('account_heads.name as head, transactions.type as type, SUM(transactions.amount) as total')
                ->groupBy('account_heads.name', 'transactions.type')
                ->get()
                ->groupBy('head')
                ->map(fn ($rows) => [
                    'income' => round((float) $rows->where('type', 'income')->sum('total'), 2),
                    'expense' => round((float) $rows->where('type', 'expense')->sum('total'), 2),
                ])
                ->sortByDesc(fn ($row) => $row['income'] + $row['expense'])
                ->take(15)
                ->toArray();
        } elseif ($group === 'month') {
            $summary['by_month'] = (clone $base)
                ->selectRaw("DATE_FORMAT(transaction_date, '%Y-%m') as month, transactions.type as type, "
                    .'SUM(transactions.amount) as total')
                ->groupBy('month', 'transactions.type')
                ->orderBy('month')
                ->get()
                ->groupBy('month')
                ->map(fn ($rows) => [
                    'income' => round((float) $rows->where('type', 'income')->sum('total'), 2),
                    'expense' => round((float) $rows->where('type', 'expense')->sum('total'), 2),
                ])
                ->toArray();
        }

        $rows = (clone $base)
            ->with(['accountHead:id,name', 'branch:id,name'])
            ->latest('id')
            ->limit($this->limit($args))
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'date' => $t->transaction_date
                    ? Carbon::parse($t->transaction_date)->format('Y-m-d')
                    : null,
                'type' => $t->type,
                'account_head' => $t->accountHead?->name,
                'branch' => $t->branch?->name,
                'amount' => (float) $t->amount,
                'description' => $t->description,
                'reference_no' => $t->reference_no,
            ])
            ->all();

        return $this->result($summary, $rows);
    }
}
