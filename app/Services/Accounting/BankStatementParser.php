<?php

namespace App\Services\Accounting;

use Carbon\Carbon;
use InvalidArgumentException;

class BankStatementParser
{
    public function parse(string $filePath, ?string $format = null): array
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        $format = $format ?? $this->detectFormat($filePath);

        return match ($format) {
            'csv' => $this->parseCsv($filePath),
            'ofx', 'qfx' => $this->parseOfx($filePath),
            default => throw new InvalidArgumentException("Unsupported format: {$format}"),
        };
    }

    protected function detectFormat(string $filePath): string
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($ext) {
            'csv', 'txt' => 'csv',
            'ofx', 'qfx' => 'ofx',
            default => 'csv',
        };
    }

    protected function parseCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new InvalidArgumentException("Cannot open file: {$filePath}");
        }

        $header = null;
        $transactions = [];
        $lineNum = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNum++;
            if ($lineNum === 1) {
                $header = array_map(fn ($h) => strtolower(trim($h)), $row);
                continue;
            }
            if (count(array_filter($row)) === 0) continue;

            $data = array_combine($header, array_pad($row, count($header), null));

            $date = $this->findColumn($data, ['date', 'transaction_date', 'posted_date', 'value_date']);
            $description = $this->findColumn($data, ['description', 'narration', 'details', 'particulars']) ?? '';
            $reference = $this->findColumn($data, ['reference', 'ref', 'cheque_no', 'check_number']);
            $amount = $this->findColumn($data, ['amount', 'transaction_amount']);
            $debit = $this->findColumn($data, ['debit', 'withdrawal', 'dr']);
            $credit = $this->findColumn($data, ['credit', 'deposit', 'cr']);
            $balance = $this->findColumn($data, ['balance', 'running_balance']);
            $counterparty = $this->findColumn($data, ['counterparty', 'payee', 'party']);

            $finalAmount = null;
            $type = null;
            if ($amount !== null) {
                $parsedAmt = $this->parseAmount($amount);
                if ($parsedAmt !== null) {
                    $finalAmount = abs($parsedAmt);
                    $type = $parsedAmt >= 0 ? 'deposit' : 'withdrawal';
                }
            } elseif ($debit !== null || $credit !== null) {
                $dr = $debit !== null ? abs((float) $this->parseAmount($debit)) : 0;
                $cr = $credit !== null ? abs((float) $this->parseAmount($credit)) : 0;
                if ($cr > 0) {
                    $finalAmount = $cr;
                    $type = 'deposit';
                } elseif ($dr > 0) {
                    $finalAmount = $dr;
                    $type = 'withdrawal';
                }
            }

            if ($finalAmount === null || $finalAmount == 0) continue;
            if (!$date) continue;

            $transactions[] = [
                'date' => $this->parseDate($date),
                'description' => substr($description, 0, 255),
                'reference' => $reference ? substr($reference, 0, 255) : null,
                'amount' => $finalAmount,
                'type' => $type,
                'balance' => $balance !== null ? $this->parseAmount($balance) : null,
                'counterparty' => $counterparty ? substr($counterparty, 0, 255) : null,
            ];
        }

        fclose($handle);

        return ['source' => 'csv', 'transactions' => $transactions];
    }

    protected function parseOfx(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new InvalidArgumentException("Cannot read file: {$filePath}");
        }

        $transactions = [];

        if (preg_match_all('/<STMTTRN>(.*?)<\/STMTTRN>/is', $content, $blocks)) {
            foreach ($blocks[1] as $block) {
                $date = $this->extractOfxTag($block, 'DTPOSTED');
                $amount = $this->extractOfxTag($block, 'TRNAMT');
                $description = $this->extractOfxTag($block, 'MEMO')
                    ?? $this->extractOfxTag($block, 'NAME')
                    ?? '';
                $reference = $this->extractOfxTag($block, 'FITID')
                    ?? $this->extractOfxTag($block, 'CHECKNUM');

                if (!$date || !$amount) continue;

                $parsedAmt = (float) $amount;

                $transactions[] = [
                    'date' => $this->parseOfxDate($date),
                    'description' => trim(substr($description, 0, 255)),
                    'reference' => $reference ? trim(substr($reference, 0, 255)) : null,
                    'amount' => abs($parsedAmt),
                    'type' => $parsedAmt >= 0 ? 'deposit' : 'withdrawal',
                    'balance' => null,
                    'counterparty' => null,
                ];
            }
        }

        return ['source' => 'ofx', 'transactions' => $transactions];
    }

    protected function extractOfxTag(string $block, string $tag): ?string
    {
        if (preg_match('/<' . $tag . '>([^<\r\n]+)/i', $block, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    protected function parseOfxDate(string $value): string
    {
        $clean = preg_replace('/\..*$/', '', $value);
        $clean = preg_replace('/[+\-]\d+.*$/', '', $clean);
        if (strlen($clean) >= 8) {
            try {
                return Carbon::createFromFormat('Ymd', substr($clean, 0, 8))->toDateString();
            } catch (\Throwable $e) {
                return now()->toDateString();
            }
        }

        return now()->toDateString();
    }

    protected function findColumn(array $row, array $keys): ?string
    {
        foreach ($keys as $k) {
            if (isset($row[$k]) && trim((string) $row[$k]) !== '') {
                return (string) $row[$k];
            }
        }

        return null;
    }

    protected function parseDate(string $value): string
    {
        $value = trim($value);
        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'Y/m/d', 'd.M.Y', 'Ymd'];
        foreach ($formats as $f) {
            try {
                return Carbon::createFromFormat($f, $value)->toDateString();
            } catch (\Throwable $e) {
                continue;
            }
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return now()->toDateString();
        }
    }

    protected function parseAmount(?string $value): ?float
    {
        if ($value === null || $value === '') return null;
        $clean = preg_replace('/[^\d\.\-]/', '', $value);

        return is_numeric($clean) ? (float) $clean : null;
    }
}
