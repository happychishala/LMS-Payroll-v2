<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Repayments;
use Carbon\Carbon;

class RepaymentAllocationService
{
    public function resolveRepaymentNumberForOutstandingInterest(Loan $loan, int $repaymentNumber): int
    {
        if ($repaymentNumber <= 1) {
            return $repaymentNumber;
        }

        for ($period = 1; $period < $repaymentNumber; $period++) {
            if ($this->interestOutstandingForPeriod($loan, $period) > 0.009) {
                return $period;
            }
        }

        return $repaymentNumber;
    }

    /**
     * Allocate an incoming payment amount for a given loan repayment month.
     *
     * If $allowExtraPrincipal is false, allocation will not apply extra principal beyond the scheduled principal.
     */
    public function allocateForMonth(Loan $loan, float $amount, int $repaymentNumber, Carbon $receiptDate, bool $allowExtraPrincipal = true): array
    {
        $rate = ($loan->interest_rate ?? 0) / 100 / 12;
        $monthlyPayment = $loan->total_monthly_repayment ?? 0;
        $monthlyInsurance = $loan->monthly_insurance ?? 0;

        $loanPrincipal = (float) ($loan->principal_amount ?? $loan->balance ?? 0);

        $priorPeriodPrincipalPaid = (float) Repayments::query()
            ->where('loan_id', $loan->loan_id)
            ->where('repayment_number', '<', $repaymentNumber)
            ->sum('paid_principal');

        $periodPrincipalPaid = (float) Repayments::query()
            ->where('loan_id', $loan->loan_id)
            ->where('repayment_number', $repaymentNumber)
            ->sum('paid_principal');

        $periodInterestPaid = (float) Repayments::query()
            ->where('loan_id', $loan->loan_id)
            ->where('repayment_number', $repaymentNumber)
            ->sum('paid_interest');

        $periodInsurancePaid = (float) Repayments::query()
            ->where('loan_id', $loan->loan_id)
            ->where('repayment_number', $repaymentNumber)
            ->sum('insurance_paid');

        // Scheduled dues are based on the opening balance at the start of the installment period.
        $scheduledOpening = max(0, round($loanPrincipal - $priorPeriodPrincipalPaid, 2));
        $currentOpening = max(0, round($scheduledOpening - $periodPrincipalPaid, 2));

        $scheduledInterestDue = $rate > 0
            ? round($scheduledOpening * $rate, 2)
            : 0.0;

        $scheduledPrincipalDue = max(0, round($monthlyPayment - $scheduledInterestDue, 2));

        $interestDue = max(0, round($scheduledInterestDue - $periodInterestPaid, 2));
        $insuranceDue = max(0, round((float) $monthlyInsurance - $periodInsurancePaid, 2));
        $principalDue = max(0, round($scheduledPrincipalDue - $periodPrincipalPaid, 2));

        $remaining = $amount;

        $intPaid = min($remaining, $interestDue);
        $remaining -= $intPaid;

        $insPaid = min($remaining, $insuranceDue);
        $remaining -= $insPaid;

        $prinPaid = min($remaining, min($principalDue, $currentOpening));
        $remaining -= $prinPaid;

        $extraPrincipal = 0;
        if ($allowExtraPrincipal) {
            // Any remaining amount becomes extra principal
            $extraPrincipal = min($remaining, max(0, $currentOpening - $prinPaid));
            $prinPaid += $extraPrincipal;
            $remaining -= $extraPrincipal;
        }

        $applied = $insPaid + $intPaid + $prinPaid;
        $closing = max(0, round($currentOpening - $prinPaid, 2));

        return [
            'receipt_amount' => $applied,
            'paid_principal' => $prinPaid,
            'paid_interest'  => $intPaid,
            'insurance_paid' => $insPaid,
            'extra_principal'=> $extraPrincipal,
            'unallocated'    => $remaining,
            'interest_due'   => $interestDue,
            'insurance_due'  => $insuranceDue,
            'scheduled_interest_due' => $scheduledInterestDue,
            'scheduled_principal_due' => $scheduledPrincipalDue,
            'repayment_number' => $repaymentNumber,
            'receipt_date'   => $receiptDate->toDateString(),
            'opening_balance'=> $currentOpening,
            'closing_balance'=> $closing,
        ];
    }

    private function interestOutstandingForPeriod(Loan $loan, int $repaymentNumber): float
    {
        $rate = ($loan->interest_rate ?? 0) / 100 / 12;
        if ($rate <= 0) {
            return 0.0;
        }

        $loanPrincipal = (float) ($loan->principal_amount ?? $loan->balance ?? 0);

        $priorPeriodPrincipalPaid = (float) Repayments::query()
            ->where('loan_id', $loan->loan_id)
            ->where('repayment_number', '<', $repaymentNumber)
            ->sum('paid_principal');

        $periodInterestPaid = (float) Repayments::query()
            ->where('loan_id', $loan->loan_id)
            ->where('repayment_number', $repaymentNumber)
            ->sum('paid_interest');

        $scheduledOpening = max(0, round($loanPrincipal - $priorPeriodPrincipalPaid, 2));
        $scheduledInterestDue = round($scheduledOpening * $rate, 2);

        return max(0, round($scheduledInterestDue - $periodInterestPaid, 2));
    }
}
