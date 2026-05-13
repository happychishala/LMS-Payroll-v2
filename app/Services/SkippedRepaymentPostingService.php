<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\SkippedRepayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SkippedRepaymentPostingService
{
    public function post(SkippedRepayment $skipped): int
    {
        if ($skipped->posted_repayment_id) {
            return (int) $skipped->posted_repayment_id;
        }

        $loan = $this->resolveLoan($skipped);
        if (! $loan) {
            throw new \RuntimeException('No loan found for this skipped repayment. Add a valid Loan ID or Employee No first.');
        }

        if (! $skipped->receipt_date) {
            throw new \RuntimeException('Receipt date is required before posting.');
        }

        $amount = round((float) $skipped->receipt_amount, 2);
        if ($amount <= 0) {
            throw new \RuntimeException('Receipt amount must be greater than zero before posting.');
        }

        $receiptDate = Carbon::parse($skipped->receipt_date);
        $repaymentNumber = $skipped->repayment_number
            ? (int) $skipped->repayment_number
            : app(RepaymentScheduleService::class)->computeRepaymentNumber($loan, $receiptDate);
        $repaymentNumber = app(RepaymentAllocationService::class)
            ->resolveRepaymentNumberForOutstandingInterest($loan, $repaymentNumber);

        $allocation = app(RepaymentAllocationService::class)
            ->allocateForMonth($loan, $amount, $repaymentNumber, $receiptDate, true);

        if (round((float) ($allocation['receipt_amount'] ?? 0), 2) <= 0) {
            throw new \RuntimeException('The payment could not be allocated to this loan/month.');
        }

        $allocation['import_reference'] = $this->postingReference($skipped);

        return DB::transaction(function () use ($skipped, $loan, $repaymentNumber, $allocation): int {
            $repayment = app(RepaymentImportService::class)
                ->upsertAndRecompute($loan, $repaymentNumber, $allocation);

            $skipped->forceFill([
                'loan_id' => $loan->loan_id,
                'loan_number' => $loan->loan_number,
                'employee_no' => $skipped->employee_no ?: $loan->employee_no,
                'repayment_number' => $repaymentNumber,
                'status' => 'posted',
                'posted_repayment_id' => $repayment->id,
                'posted_at' => now(),
                'post_error' => null,
            ])->save();

            return (int) $repayment->id;
        });
    }

    private function resolveLoan(SkippedRepayment $skipped): ?Loan
    {
        if ($skipped->loan_id) {
            $loan = Loan::query()
                ->where('loan_id', $skipped->loan_id)
                ->orWhere('loan_number', $skipped->loan_id)
                ->first();

            if ($loan) {
                return $loan;
            }
        }

        if (! $skipped->employee_no) {
            return null;
        }

        return Loan::query()
            ->where('employee_no', $skipped->employee_no)
            ->whereIn('loan_status', ['approved', 'partially_paid'])
            ->orderBy('loan_release_date')
            ->first();
    }

    private function postingReference(SkippedRepayment $skipped): string
    {
        $reference = trim((string) $skipped->reference_number);

        return 'skipped_' . $skipped->id . ($reference !== '' ? '_' . sha1($reference) : '');
    }
}
