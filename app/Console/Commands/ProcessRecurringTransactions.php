<?php

namespace App\Console\Commands;

use App\Services\Accounting\RecurringTransactionService;
use Illuminate\Console\Command;

class ProcessRecurringTransactions extends Command
{
    protected $signature = 'accounting:process-recurring {--limit=100}';
    protected $description = 'Process all due recurring transaction templates';

    public function handle(RecurringTransactionService $service): int
    {
        $this->info('Processing recurring transactions...');

        $result = $service->processDueTemplates((int) $this->option('limit'));

        $this->table(
            ['Processed', 'Succeeded', 'Failed'],
            [[$result['processed'], $result['succeeded'], $result['failed']]]
        );

        return $result['failed'] > 0 ? 1 : 0;
    }
}
