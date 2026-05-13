<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Repayments;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RepaymentImportService
{
    /**
     * Upsert a repayment for a loan & repayment number, then recompute ledger.
     *
     * $allocation should contain:
     *  - receipt_amount, paid_principal, paid_interest, insurance_paid, receipt_date (YYYY-MM-DD)
     */
    public function upsertAndRecompute(Loan $loan, int $repaymentNumber, array $allocation): Repayments
    {
        return DB::transaction(function () use ($loan, $repaymentNumber, $allocation) {
            $receiptAmount = $allocation['receipt_amount'] ?? 0;
            $paidPrincipal = $allocation['paid_principal'] ?? 0;
            $paidInterest  = $allocation['paid_interest'] ?? 0;
            $insurancePaid = $allocation['insurance_paid'] ?? 0;
            $receiptDate   = $allocation['receipt_date'] ?? now()->toDateString();
            $receiptDate   = Carbon::parse($receiptDate)->toDateString();
            $importReference = trim((string) ($allocation['import_reference'] ?? ''));

            if ($importReference !== '') {
                $duplicate = Repayments::where('loan_id', $loan->loan_id)
                    ->where(function ($query) use ($importReference) {
                        $query->where('reference_number', $importReference)
                            ->orWhere('reference_number', 'like', $importReference . '|%')
                            ->orWhere('reference_number', 'like', '%|' . $importReference . '|%')
                            ->orWhere('reference_number', 'like', '%|' . $importReference);
                    })
                    ->lockForUpdate()
                    ->first();

                if ($duplicate) {
                    $duplicate->import_skipped_duplicate = true;

                    return $duplicate;
                }
            }

            // Only merge if the receipt_date matches; otherwise create a new record
            $rep = Repayments::where('loan_id', $loan->loan_id)
                ->where('repayment_number', $repaymentNumber)
                ->whereDate('receipt_date', $receiptDate)
                ->lockForUpdate()
                ->first();

            // compute opening balance (sum prior paid_principal)
            $opening = $this->computeOpeningBalanceForRepayment($loan, $repaymentNumber, $receiptDate);

            if ($rep) {
                $rep->receipt_amount = ($rep->receipt_amount ?? 0) + $receiptAmount;
                $rep->paid_principal = ($rep->paid_principal ?? 0) + $paidPrincipal;
                $rep->paid_interest  = ($rep->paid_interest ?? 0) + $paidInterest;
                $rep->insurance_paid = ($rep->insurance_paid ?? 0) + $insurancePaid;
                $rep->receipt_date   = $receiptDate;
                $rep->opening_balance = $opening;
                $rep->closing_balance = max(0, $opening - $rep->paid_principal);
                $rep->reference_number = $this->appendImportReference($rep->reference_number, $importReference);
                $rep->save();
            } else {
                // Even if the same repayment_number exists on a different date,
                // we create a new record rather than overwrite.
                $rep = Repayments::create([
                    'loan_id' => $loan->loan_id,
                    'employee_no' => $loan->employee_no ?? null,
                    'nrc' => $loan->nrc ?? null,
                    'receipt_date' => $receiptDate,
                    'receipt_amount' => $receiptAmount,
                    'paid_principal' => $paidPrincipal,
                    'paid_interest' => $paidInterest,
                    'insurance_paid' => $insurancePaid,
                    'opening_balance' => $opening,
                    'closing_balance' => max(0, $opening - $paidPrincipal),
                    'repayment_number' => $repaymentNumber,
                    'monthly_repayment_balance' => $loan->total_monthly_repayment ?? 0,
                    'payment_status' => 'Paid',
                    'sanlam' => $loan->monthly_insurance ?? 0,
                    'zed_fin' => $insurancePaid,
                    'interest_rate' => $loan->interest_rate,
                    'loan_amount' => $loan->principal_amount,
                    'term' => $loan->loan_duration,
                    'loan_issue_date' => $loan->loan_release_date,
                    'employer' => $loan->borrower->employer ?? $loan->employer ?? null,
                    'balance' => max(0, $opening - $paidPrincipal),
                    'payments_method' => 'payroll',
                    'reference_number' => $importReference !== '' ? $importReference : null,
                    'loan_number' => $loan->loan_number,
                    'payment_date' => $receiptDate,
                ]);
            }

            // recompute subsequent ledger and update loan balance
            $this->recomputeLoanLedger($loan);

            return $rep->refresh();
        });
    }

    private function appendImportReference(?string $existing, string $reference): ?string
    {
        $reference = trim($reference);
        if ($reference === '') {
            return $existing;
        }

        $tokens = collect(explode('|', (string) $existing))
            ->map(fn ($token) => trim($token))
            ->filter()
            ->values()
            ->all();

        if (! in_array($reference, $tokens, true)) {
            $tokens[] = $reference;
        }

        return empty($tokens) ? null : implode('|', $tokens);
    }

    private function computeOpeningBalanceForRepayment(Loan $loan, int $repaymentNumber, ?string $receiptDate = null): float
    {
        $opening = $loan->principal_amount ?? $loan->balance ?? 0;
        $query = Repayments::where('loan_id', $loan->loan_id);
        if ($receiptDate) {
            $query->where(function ($q) use ($repaymentNumber, $receiptDate) {
                $q->where('repayment_number', '<', $repaymentNumber)
                    ->orWhere(function ($q2) use ($repaymentNumber, $receiptDate) {
                        $q2->where('repayment_number', $repaymentNumber)
                            ->whereDate('receipt_date', '<', $receiptDate);
                    });
            });
        } else {
            $query->where('repayment_number', '<', $repaymentNumber);
        }
        $prevPaidPrincipal = $query->sum('paid_principal');

        return max(0, $opening - $prevPaidPrincipal);
    }

    public function recomputeLoanLedger(Loan $loan): void
    {
        $rate = ($loan->interest_rate ?? 0) / 100 / 12;
        $monthlyPayment = $loan->total_monthly_repayment ?? 0;
        $monthlyInsurance = $loan->monthly_insurance ?? 0;

        $balance = $loan->principal_amount ?? $loan->balance ?? 0;

        $reps = Repayments::where('loan_id', $loan->loan_id)
            ->orderBy('repayment_number', 'asc')
            ->orderBy('receipt_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($reps as $rep) {
            $opening = $balance;
            $interestDue = round($opening * $rate, 2);
            $scheduledPrincipal = max(0, round($monthlyPayment - $interestDue, 2));
            $paidPrincipal = $rep->paid_principal ?? 0;

            $closing = max(0, $opening - $paidPrincipal);
            $rep->opening_balance = $opening;
            $rep->closing_balance = $closing;
            $rep->monthly_repayment_balance = $monthlyPayment;
            $rep->sanlam = $monthlyInsurance;
            $rep->save();

            $balance = $closing;
        }

        $loan->balance = $balance;
        $loan->save();
    }
}
