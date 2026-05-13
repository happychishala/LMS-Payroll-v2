<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Repayments;
use App\Models\Withholding;
use Illuminate\Support\Carbon;

class WithholdingService
{
    public function syncForLoan(Loan $loan): void
    {
        if (! $this->qualifiesForUpfrontWithholding($loan)) {
            Withholding::query()->where('loan_id', $loan->loan_id)->delete();
            return;
        }

        $borrowerCustomerId = $loan->borrower?->customer_id;
        if (! $borrowerCustomerId) {
            return;
        }

        $dueDate = $loan->first_repayment_date
            ? Carbon::parse($loan->first_repayment_date)->toDateString()
            : Carbon::parse($loan->loan_release_date)->addMonth()->toDateString();

        Withholding::query()->updateOrCreate(
            ['loan_id' => $loan->loan_id],
            [
                'borrower_id' => $borrowerCustomerId,
                'installment_due_date' => $dueDate,
                'installment_amount' => (float) ($loan->withholding_amount ?? 0),
                'withholding_status' => 'Pending',
                'remarks' => 'Upfront first installment captured for GRZ third-party refinance.',
            ]
        );
    }

    public function syncFromRepayment(Loan $loan, Repayments $repayment): void
    {
        $withholding = Withholding::query()
            ->where('loan_id', $loan->loan_id)
            ->where('withholding_status', 'Pending')
            ->first();

        if (! $withholding) {
            return;
        }

        if (strtolower((string) $repayment->payments_method) !== 'payroll') {
            return;
        }

        $withholding->update([
            'withholding_status' => 'Refund',
            'remarks' => 'First installment collected by payroll; upfront installment is due for refund.',
        ]);
    }

    public function refreshPendingStatuses(): int
    {
        $updated = 0;

        $records = Withholding::query()
            ->where('withholding_status', 'Pending')
            ->get();

        foreach ($records as $record) {
            $loan = $record->loan;
            if (! $loan) {
                continue;
            }

            $hasPayrollCollection = $loan->repayments()
                ->whereIn('payments_method', ['Payroll', 'payroll'])
                ->whereDate('receipt_date', '<=', $record->installment_due_date)
                ->exists();

            if ($hasPayrollCollection) {
                $record->update([
                    'withholding_status' => 'Refund',
                    'remarks' => 'First installment collected by payroll; upfront installment is due for refund.',
                ]);
                $updated++;
                continue;
            }

            if (now()->toDateString() > $record->installment_due_date) {
                $record->update([
                    'withholding_status' => 'Withheld',
                    'remarks' => 'No payroll first-installment collection by due date; upfront installment applied as first installment.',
                ]);
                $updated++;
            }
        }

        return $updated;
    }

    public function qualifiesForUpfrontWithholding(Loan $loan): bool
    {
        $loanName = strtolower((string) ($loan->loan_type?->loan_name ?? $loan->loan_category ?? ''));
        $thirdPartyTotal = (float) ($loan->total_third_party_balance ?? 0);

        return str_contains($loanName, 'grz') && $thirdPartyTotal > 0 && (float) ($loan->withholding_amount ?? 0) > 0;
    }
}
