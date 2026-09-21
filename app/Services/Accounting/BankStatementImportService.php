<?php

namespace App\Services\Accounting;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use Illuminate\Support\Facades\DB;

class BankStatementImportService
{
    public function __construct(protected BankStatementParser $parser) {}

    public function import(string $filePath, array $data, ?string $format = null): array
    {
        $parsed = $this->parser->parse($filePath, $format);
        if (empty($parsed['transactions'])) {
            throw new \InvalidArgumentException('No transactions found in file.');
        }

        $hash = hash('sha256', file_get_contents($filePath) . '|' . $data['bank_account_id']);
        $existing = BankStatement::where('institute_id', $data['institute_id'])
            ->where('import_hash', $hash)
            ->first();

        if ($existing) {
            return [
                'statement_id' => $existing->id,
                'status' => 'duplicate',
                'lines_created' => 0,
            ];
        }

        return DB::transaction(function () use ($parsed, $data, $hash, $filePath) {
            $statement = BankStatement::create([
                'institute_id' => $data['institute_id'],
                'branch_id' => $data['branch_id'] ?? null,
                'bank_account_id' => $data['bank_account_id'],
                'statement_date' => $data['statement_date'] ?? now()->toDateString(),
                'opening_balance' => $data['opening_balance'] ?? 0,
                'closing_balance' => $data['closing_balance'] ?? 0,
                'file_name' => basename($filePath),
                'import_hash' => $hash,
                'import_source' => $parsed['source'],
                'original_filename' => basename($filePath),
                'imported_at' => now(),
                'status' => 'imported',
            ]);

            $lineCount = 0;
            foreach ($parsed['transactions'] as $tx) {
                BankStatementLine::create([
                    'statement_id' => $statement->id,
                    'institute_id' => $data['institute_id'],
                    'transaction_date' => $tx['date'],
                    'description' => substr($tx['description'] ?? '', 0, 255),
                    'reference' => $tx['reference'] ?? null,
                    'amount' => $tx['amount'],
                    'type' => $tx['type'],
                    'counterparty' => $tx['counterparty'] ?? null,
                    'category_status' => 'unmatched',
                ]);
                $lineCount++;
            }

            return [
                'statement_id' => $statement->id,
                'status' => 'imported',
                'lines_created' => $lineCount,
            ];
        });
    }
}
