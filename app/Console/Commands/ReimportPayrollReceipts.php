<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\Repayments;
use App\Models\SkippedRepayment;
use App\Services\RepaymentNumberResolver;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SplFileObject;

class ReimportPayrollReceipts extends Command
{
    protected $signature = 'repayments:reimport-payroll
        {files* : One or more CSV file paths}
        {--delete-only : Delete matching payroll repayments without re-importing}
        {--dry-run : Show what would be deleted/imported without writing changes}';

    protected $description = 'Delete payroll repayments matched by CSV file contents and re-import them safely.';

    public function handle(): int
    {
        $files = (array) $this->argument('files');
        $deleteOnly = (bool) $this->option('delete-only');
        $dryRun = (bool) $this->option('dry-run');

        foreach ($files as $file) {
            if (! is_file($file)) {
                $this->error("File not found: {$file}");
                return self::FAILURE;
            }
        }

        foreach ($files as $file) {
            $rows = $this->parseRows($file);
            $loanIds = collect($rows)->pluck('loan_id')->filter()->unique()->values()->all();
            $receiptDates = collect($rows)->pluck('receipt_date')->filter()->unique()->values()->all();

            $deleteQuery = Repayments::query()
                ->whereIn('payments_method', ['Payroll', 'payroll'])
                ->whereIn('loan_id', $loanIds)
                ->whereIn('receipt_date', $receiptDates);

            $existingCount = (clone $deleteQuery)->count();
            $this->info(sprintf('%s: %d parsed row(s), %d matching payroll repayment(s) to delete', basename($file), count($rows), $existingCount));

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($deleteQuery, $rows, $deleteOnly, $file) {
                $deleteQuery->delete();

                if ($deleteOnly) {
                    return;
                }

                foreach ($rows as $row) {
                    $matchedRepaymentId = Repayments::query()
                        ->where('loan_id', $row['loan_id'])
                        ->where('employee_no', $row['employee_no'])
                        ->where('repayment_number', $row['repayment_number'])
                        ->whereDate('receipt_date', $row['receipt_date'])
                        ->where('receipt_amount', $row['receipt_amount'])
                        ->value('id');

                    if ($matchedRepaymentId) {
                        SkippedRepayment::create([
                            'csv_file' => basename($file),
                            'row_number' => $row['_row_number'] ?? null,
                            'import_type' => 'reimport_payroll',
                            'reason' => 'Duplicate repayment already exists during payroll re-import.',
                            'loan_id' => $row['loan_id'] ?? null,
                            'loan_number' => $row['loan_number'] ?? null,
                            'employee_no' => $row['employee_no'] ?? null,
                            'batch_no' => $row['batch_no'] ?? null,
                            'repayment_number' => $row['repayment_number'] ?? null,
                            'receipt_date' => $row['receipt_date'] ?? null,
                            'receipt_amount' => $row['receipt_amount'] ?? null,
                            'reference_number' => $row['reference_number'] ?? null,
                            'matched_repayment_id' => $matchedRepaymentId,
                            'source_payload' => $row,
                        ]);
                        continue;
                    }

                    unset($row['_row_number']);
                    Repayments::create($row);
                }
            });
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function parseRows(string $path): array
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
        $file->setCsvControl(',');
        $file->rewind();

        $rawHeader = $file->fgetcsv();
        $header = array_map(fn ($value) => $this->normalizeHeader($value), $rawHeader ?: []);
        $rows = [];

        $rowNumber = 1;

        while (! $file->eof()) {
            $row = $file->fgetcsv();
            $rowNumber++;
            if (! is_array($row)) {
                continue;
            }
            if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $cols = count($row) < count($header) ? array_pad($row, count($header), null) : array_slice($row, 0, count($header));
            $assoc = array_combine($header, $cols);
            $normalized = $this->normalizeRow($assoc);

            if (! $normalized) {
                continue;
            }

            $normalized['_row_number'] = $rowNumber;
            $rows[] = $normalized;
        }

        return $rows;
    }

    private function normalizeRow(array $row): ?array
    {
        $loanId = $this->clean($row['loan_id'] ?? null);
        if (! $loanId) {
            return null;
        }

        $loan = Loan::query()
            ->where('loan_id', $loanId)
            ->orWhere('loan_number', $loanId)
            ->first();

        if (! $loan) {
            return null;
        }

        $receiptDate = $this->parseDate($row['receipt_date'] ?? null);
        $loanIssueDate = $this->parseDate($row['loan_issue_date'] ?? null);
        $receiptAmount = $this->parseNumber($row['receipt_amount'] ?? null) ?? 0;
        $paidPrincipal = $this->parseNumber($row['paid_principal'] ?? null) ?? 0;
        $paidInterest = $this->parseNumber($row['paid_interest'] ?? null) ?? 0;
        $insuranceCharge = $this->parseNumber($row['insurance_charge'] ?? null) ?? 0;
        $openingBalance = $this->parseNumber($row['opening_balance'] ?? null);
        $closingBalance = $this->parseNumber($row['closing_balance'] ?? null);
        $monthlyRepayment = $this->parseNumber($row['monthly_repayment_balance'] ?? null);
        $monthlyInstallment = $this->parseNumber($row['monthly_installment_balance'] ?? null);

        $openingBalance ??= (float) ($loan->balance ?? $loan->principal_amount ?? 0);
        $closingBalance ??= max(0, round($openingBalance - $paidPrincipal, 2));
        $monthlyRepayment ??= $monthlyInstallment ?? $receiptAmount ?? (float) ($loan->total_monthly_repayment ?? 0);
        $monthlyInstallment ??= $monthlyRepayment;

        return [
            'loan_id' => $loan->loan_id,
            'employee_no' => $this->clean($row['employee_no'] ?? null),
            'nrc' => $this->clean($row['nrc'] ?? null),
            'client_id' => $this->clean($row['client_id'] ?? null),
            'batch_no' => $this->clean($row['batch_no'] ?? null),
            'receipt_date' => $receiptDate,
            'receipt_amount' => $receiptAmount,
            'paid_principal' => $paidPrincipal,
            'paid_interest' => $paidInterest,
            'insurance_paid' => $this->parseNumber($row['insurance_paid'] ?? $row['insurance_charge'] ?? null) ?? 0,
            'monthly_installment_balance' => $monthlyInstallment,
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'repayment_number' => app(RepaymentNumberResolver::class)->resolve(
                $loan,
                $receiptDate,
                (int) ($this->parseNumber($row['repayment_number'] ?? null) ?? 0)
            ) ?? 0,
            'monthly_repayment_balance' => $monthlyRepayment,
            'payment_status' => $this->clean($row['payment_status'] ?? null) ?? 'Paid',
            'extra_payments' => $this->parseNumber($row['extra_payments'] ?? null) ?? 0,
            'refund' => $this->parseNumber($row['refund'] ?? null) ?? 0,
            'insurance_charge' => $insuranceCharge,
            'zed_fin' => $this->parseNumber($row['zed_fin'] ?? null) ?? 0,
            'sanlam' => $this->parseNumber($row['sanlam'] ?? null) ?? 0,
            'interest_rate' => $this->parseNumber($row['interest_rate'] ?? null) ?? (float) ($loan->interest_rate ?? 0),
            'loan_amount' => $this->parseNumber($row['loan_amount'] ?? null) ?? (float) ($loan->principal_amount ?? 0),
            'term' => (int) ($this->parseNumber($row['term'] ?? null) ?? (int) ($loan->loan_duration ?? 0)),
            'loan_issue_date' => $loanIssueDate ?? optional($loan->loan_release_date)->toDateString(),
            'employer' => $this->clean($row['employer'] ?? null) ?? ($loan->borrower->employer ?? $loan->employer ?? null),
            'status' => $this->clean($row['status'] ?? null),
            'balance' => $closingBalance,
            'payments' => $receiptAmount,
            'principal' => $paidPrincipal,
            'payments_method' => $this->clean($row['payment_type'] ?? $row['payments_method'] ?? null) ?? 'Payroll',
            'reference_number' => $this->clean($row['reference_number'] ?? null) ?? uniqid('reimport_', false),
            'loan_number' => $this->clean($row['loan_number'] ?? null) ?? ($loan->loan_number ?? $loan->loan_id),
            'payment_date' => $receiptDate,
        ];
    }

    private function normalizeHeader(?string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', (string) $value);
        $value = trim($value);
        $value = str_replace(['-', ' '], '_', $value);

        return Str::snake($value);
    }

    private function parseDate(?string $value): ?string
    {
        $value = $this->clean($value);
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            $serial = (float) $value;
            if ($serial > 0) {
                return Carbon::create(1899, 12, 30)->addDays((int) floor($serial))->toDateString();
            }
        }

        foreach (['j/n/Y', 'j/n/y', 'd/m/Y', 'd/m/y', 'd-m-Y', 'd-m-y', 'Y-m-d', 'n/j/Y', 'n/j/y', 'm/d/Y', 'm/d/y'] as $format) {
            $parsed = $this->parseDateWithFormat($format, $value);
            if ($parsed) {
                return $parsed;
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function parseDateWithFormat(string $format, string $value): ?string
    {
        try {
            $date = Carbon::createFromFormat($format, $value);
            $errors = Carbon::getLastErrors();

            if ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) {
                return null;
            }

            return $date->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function parseNumber($value): ?float
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $clean = preg_replace('/[^\d.\-]/', '', $value);

        return $clean === '' || ! is_numeric($clean) ? null : (float) $clean;
    }

    private function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
