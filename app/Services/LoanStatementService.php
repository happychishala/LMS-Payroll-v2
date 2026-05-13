<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\MissedInstallment;
use App\Models\Repayments;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LoanStatementService
{
    public function build(string $loanId, ?Carbon $asOfDate = null): array
    {
        $asOfDate ??= now();
        $interestAccruals = app(LoanInterestAccrualService::class);
        $loanBalanceService = app(LoanBalanceService::class);

        $loan = Loan::query()
            ->with(['borrower', 'loan_type', 'repaymentSchedules', 'statusReason'])
            ->where('loan_id', $loanId)
            ->firstOrFail();

        $repayments = $this->repaymentsForLoan($loan, $asOfDate);
        $recordedMissedInstallments = MissedInstallment::query()
            ->where('loan_id', $loan->loan_id)
            ->where('status', 'missed')
            ->whereDate('due_month', '<=', $asOfDate->copy()->endOfMonth()->toDateString())
            ->orderBy('due_month')
            ->get();
        $computedInstallments = $this->computeInstallmentStatus($loan, $repayments, $asOfDate);
        $currentBalance = $loanBalanceService->resolveOutstandingBalance($loan);
        $missedInstallments = $currentBalance > 0
            ? $computedInstallments
                ->where('shortfall_amount', '>', 0)
                ->values()
            : collect();
        $dueInstallmentCount = $computedInstallments->count();

        $principalPaid = round((float) $repayments->sum('paid_principal'), 2);
        $interestPaid = round((float) $repayments->sum('paid_interest'), 2);
        $insurancePaid = round((float) $repayments->sum('insurance_paid'), 2);
        $totalPaid = round((float) $repayments->sum(fn ($repayment) => $this->repaymentAmount($repayment)), 2);
        $scheduledInsuranceAccrued = round((float) $loan->repaymentSchedules
            ->filter(fn ($schedule) => Carbon::parse($schedule->due_date)->lte($asOfDate->copy()->endOfMonth()))
            ->sum('insurance_payment'), 2);
        [, $fallbackInsuranceAccrued] = $this->fallbackAccruedAmounts($loan, $dueInstallmentCount);
        $interestAccrued = round(max($interestAccruals->accruedInterest($loan, $asOfDate), $interestPaid), 2);
        $insuranceAccrued = round(max($scheduledInsuranceAccrued, $fallbackInsuranceAccrued, $insurancePaid), 2);
        $unpaidInterest = round(max($interestAccrued - $interestPaid, 0), 2);
        $unpaidInsurance = round(max($insuranceAccrued - $insurancePaid, 0), 2);

        $currentInstallment = $computedInstallments
            ->first(fn ($installment) => Carbon::parse($installment->due_month)->isSameMonth($asOfDate));

        $dueTodayAmount = round((float) ($currentInstallment->shortfall_amount ?? 0), 2);
        $missedAmount = round((float) $computedInstallments
            ->filter(fn ($installment) => Carbon::parse($installment->due_month)->lt($asOfDate->copy()->startOfMonth()))
            ->sum('shortfall_amount'), 2);
        $currentBalance = round($currentBalance, 2);
        $todayAndArrears = round($dueTodayAmount + $missedAmount, 2);
        $maturityDate = $this->resolveMaturityDate($loan);
        $closeOffAmount = round($currentBalance + $todayAndArrears, 2);

        $lastPayment = $repayments
            ->sortBy(fn ($repayment) => Carbon::parse($repayment->receipt_date ?? $repayment->payment_date)->timestamp)
            ->last();

        $history = $repayments
            ->groupBy(fn ($repayment) => Carbon::parse($repayment->receipt_date ?? $repayment->payment_date)->format('Y-m'))
            ->map(function (Collection $rows, string $month) {
                $firstDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

                return [
                    'month' => $firstDate->format('F Y'),
                    'total_paid' => round((float) $rows->sum(fn ($repayment) => $this->repaymentAmount($repayment)), 2),
                    'principal_paid' => round((float) $rows->sum('paid_principal'), 2),
                    'interest_paid' => round((float) $rows->sum('paid_interest'), 2),
                    'insurance_paid' => round((float) $rows->sum('insurance_paid'), 2),
                    'payments_count' => $rows->count(),
                ];
            })
            ->sortKeys()
            ->values();

        return [
            'loan' => $loan,
            'borrower' => $loan->borrower,
            'repayments' => $repayments,
            'missed_installments' => $missedInstallments,
            'recorded_missed_installments' => $recordedMissedInstallments,
            'installment_status' => $computedInstallments,
            'history' => $history,
            'summary' => [
                'principal_paid' => $principalPaid,
                'interest_paid' => $interestPaid,
                'insurance_paid' => $insurancePaid,
                'interest_accrued' => $interestAccrued,
                'insurance_accrued' => $insuranceAccrued,
                'unpaid_interest' => $unpaidInterest,
                'unpaid_insurance' => $unpaidInsurance,
                'total_paid' => $totalPaid,
                'current_balance' => $currentBalance,
                'close_off_amount' => $closeOffAmount,
                'due_today_amount' => $dueTodayAmount,
                'missed_amount' => $missedAmount,
                'today_and_arrears' => $todayAndArrears,
                'monthly_repayment' => round((float) ($loan->total_monthly_repayment ?? $loan->payment ?? 0), 2),
                'maturity_date' => $maturityDate,
                'last_payment_date' => $lastPayment?->receipt_date ?? $lastPayment?->payment_date,
                'last_payment_amount' => $lastPayment ? $this->repaymentAmount($lastPayment) : 0,
                'repayments_made' => $repayments->count(),
            ],
            'as_of_date' => $asOfDate,
        ];
    }

    protected function repaymentsForLoan(Loan $loan, Carbon $asOfDate): Collection
    {
        return Repayments::query()
            ->where(function ($query) use ($loan) {
                $query->where('loan_id', $loan->loan_id);

                if ($loan->id !== null) {
                    $query->orWhere('loan_id', (string) $loan->id);
                }
            })
            ->get()
            ->filter(function ($repayment) use ($asOfDate) {
                $date = $repayment->receipt_date ?? $repayment->payment_date;

                return $date ? Carbon::parse($date)->lte($asOfDate) : true;
            })
            ->values();
    }

    protected function repaymentAmount(Repayments $repayment): float
    {
        return round((float) ($repayment->receipt_amount ?? $repayment->payments ?? 0), 2);
    }

    protected function computeInstallmentStatus(Loan $loan, Collection $repayments, Carbon $asOfDate): Collection
    {
        $installments = $this->expectedInstallments($loan, $asOfDate);
        $remainingPaid = round((float) $repayments->sum(fn ($repayment) => $this->repaymentAmount($repayment)), 2);

        return $installments->map(function ($installment) use (&$remainingPaid) {
            $expected = round((float) $installment['expected_amount'], 2);
            $allocated = round(min($remainingPaid, $expected), 2);
            $remainingPaid = round(max($remainingPaid - $allocated, 0), 2);
            $shortfall = round(max($expected - $allocated, 0), 2);

            return (object) [
                'due_month' => $installment['due_month'],
                'missed_date' => $installment['missed_date'],
                'expected_amount' => $expected,
                'paid_amount' => $allocated,
                'shortfall_amount' => $shortfall,
                'repayment_count' => 0,
                'status' => $shortfall > 0 ? 'missed' : 'resolved',
            ];
        })->values();
    }

    protected function expectedInstallments(Loan $loan, Carbon $asOfDate): Collection
    {
        $monthlyRepayment = round((float) ($loan->total_monthly_repayment ?? $loan->payment ?? 0), 2);
        $duration = (int) ($loan->loan_duration ?? $loan->term_months ?? 0);
        $interestAccruals = app(LoanInterestAccrualService::class);

        if ($monthlyRepayment <= 0 || $duration <= 0 || ! $loan->loan_release_date) {
            return collect();
        }

        $firstDueMonth = $interestAccruals->firstDueMonthEnd($loan);
        $lastDueMonth = $firstDueMonth->copy()->addMonths($duration - 1)->endOfMonth();
        $cutoff = $asOfDate->copy()->endOfMonth()->min($lastDueMonth);

        $months = collect();
        $cursor = $firstDueMonth->copy();
        $scheduleByMonth = $loan->repaymentSchedules
            ->groupBy(fn ($schedule) => Carbon::parse($schedule->due_date)->format('Y-m'));

        while ($cursor->lte($cutoff)) {
            $monthKey = $cursor->format('Y-m');
            $scheduledAmount = round((float) $scheduleByMonth
                ->get($monthKey, collect())
                ->sum('monthly_payment'), 2);

            $months->push([
                'due_month' => $cursor->copy(),
                'missed_date' => $interestAccruals->dueDateForMonth($loan, $cursor),
                'expected_amount' => $scheduledAmount > 0 ? $scheduledAmount : $monthlyRepayment,
            ]);

            $cursor = $cursor->copy()->startOfMonth()->addMonthNoOverflow()->endOfMonth();
        }

        return $months;
    }

    protected function resolveMaturityDate(Loan $loan): ?Carbon
    {
        $interestAccruals = app(LoanInterestAccrualService::class);

        if ($loan->maturity_date) {
            return Carbon::parse($loan->maturity_date);
        }

        if ($loan->loan_due_date) {
            return Carbon::parse($loan->loan_due_date);
        }

        $scheduleMaturity = $loan->repaymentSchedules
            ->map(fn ($schedule) => Carbon::parse($schedule->due_date))
            ->sortByDesc->timestamp
            ->first();

        if ($scheduleMaturity) {
            return $scheduleMaturity;
        }

        if (! $loan->loan_release_date) {
            return null;
        }

        $duration = (int) ($loan->loan_duration ?? $loan->term_months ?? 0);

        if ($duration <= 0) {
            return null;
        }

        return $interestAccruals->firstDueMonthEnd($loan)
            ->copy()
            ->addMonths($duration - 1)
            ->endOfMonth();
    }

    protected function fallbackAccruedAmounts(Loan $loan, int $dueInstallmentCount): array
    {
        if ($dueInstallmentCount <= 0) {
            return [0.0, 0.0];
        }

        $principal = (float) ($loan->principal_amount ?? 0);
        $monthlyRate = ((float) ($loan->interest_rate ?? 0)) / 100 / 12;
        $monthlyInsurance = (float) ($loan->monthly_insurance ?? 0);
        $paymentExcludingInsurance = (float) ($loan->payment
            ?? max(((float) ($loan->total_monthly_repayment ?? 0)) - $monthlyInsurance, 0));

        if ($principal <= 0 || $paymentExcludingInsurance <= 0) {
            return [0.0, round($monthlyInsurance * $dueInstallmentCount, 2)];
        }

        $balance = $principal;
        $interestAccrued = 0.0;

        for ($i = 0; $i < $dueInstallmentCount; $i++) {
            $interest = round($balance * $monthlyRate, 2);
            $principalPortion = max(round($paymentExcludingInsurance - $interest, 2), 0);
            $interestAccrued += $interest;
            $balance = max(round($balance - $principalPortion, 2), 0);

            if ($balance <= 0) {
                break;
            }
        }

        return [
            round($interestAccrued, 2),
            round($monthlyInsurance * $dueInstallmentCount, 2),
        ];
    }
}
