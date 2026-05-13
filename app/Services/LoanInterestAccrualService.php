<?php

namespace App\Services;

use App\Models\Loan;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LoanInterestAccrualService
{
    public function firstPaymentDate(Loan $loan): ?Carbon
    {
        if ($loan->first_repayment_date) {
            return Carbon::parse($loan->first_repayment_date)->endOfDay();
        }

        if (! $loan->loan_release_date) {
            return null;
        }

        return $this->firstDueMonthEnd($loan, Carbon::parse($loan->loan_release_date));
    }

    public function firstDueMonthEnd(Loan $loan, ?Carbon $releaseDate = null): Carbon
    {
        $releaseDate ??= Carbon::parse($loan->loan_release_date)->startOfDay();
        $employer = Str::upper(trim((string) ($loan->borrower?->employer ?? $loan->employer ?? '')));
        $issueMonthStart = $releaseDate->copy()->startOfMonth();

        if (str_contains($employer, 'GRZ')) {
            return $issueMonthStart->addMonth()->endOfMonth();
        }

        if (str_contains($employer, 'CNMC') || str_contains($employer, 'CMNC')) {
            return $releaseDate->day > 15
                ? $issueMonthStart->addMonth()->endOfMonth()
                : $issueMonthStart->endOfMonth();
        }

        return $issueMonthStart->endOfMonth();
    }

    public function dueInstallmentsCount(Loan $loan, Carbon|string|null $asOfDate = null): int
    {
        if (! $loan->loan_release_date) {
            return 0;
        }

        $monthEnd = $this->normalizeAsOfDate($asOfDate)->endOfMonth();
        $releaseDate = Carbon::parse($loan->loan_release_date)->startOfDay();
        $firstDueMonthEnd = $this->firstDueMonthEnd($loan, $releaseDate);

        if ($monthEnd->lt($firstDueMonthEnd)) {
            return 0;
        }

        $dueCount = $firstDueMonthEnd->diffInMonths($monthEnd) + 1;
        $duration = (int) ($loan->term_months ?? $loan->loan_duration ?? 0);

        return $duration > 0 ? min($dueCount, $duration) : $dueCount;
    }

    public function dueDateForMonth(Loan $loan, Carbon|string $dueMonth): Carbon
    {
        $month = $dueMonth instanceof Carbon
            ? $dueMonth->copy()->endOfMonth()
            : Carbon::parse($dueMonth)->endOfMonth();

        $schedules = $loan->relationLoaded('repaymentSchedules')
            ? $loan->repaymentSchedules
            : $loan->repaymentSchedules()->get();

        $schedule = $schedules
            ->first(fn ($item) => Carbon::parse($item->due_date)->isSameMonth($month));

        if ($schedule?->due_date) {
            return Carbon::parse($schedule->due_date)->startOfDay();
        }

        return $month->copy()->startOfDay();
    }

    public function accruedInterest(Loan $loan, Carbon|string|null $asOfDate = null): float
    {
        if (! $loan->loan_release_date) {
            return 0.0;
        }

        $asOf = $this->normalizeAsOfDate($asOfDate);
        $releaseDate = Carbon::parse($loan->loan_release_date)->startOfDay();

        if ($asOf->lt($releaseDate)) {
            return 0.0;
        }

        $scheduleInterest = $this->scheduledInterestAccrued($loan, $asOf);
        if ($scheduleInterest !== null) {
            return round($scheduleInterest, 2);
        }

        return round($this->fallbackInterestAccrued($loan, $asOf), 2);
    }

    public function paidInterest(Loan $loan, Carbon|string|null $asOfDate = null): float
    {
        $asOf = $this->normalizeAsOfDate($asOfDate);

        $repayments = $loan->relationLoaded('repayments')
            ? $loan->repayments
            : $loan->repayments()->get();

        return round((float) $repayments
            ->filter(function ($repayment) use ($asOf) {
                $date = $repayment->receipt_date ?? $repayment->payment_date;

                return $date ? Carbon::parse($date)->lte($asOf) : true;
            })
            ->sum('paid_interest'), 2);
    }

    public function unpaidAccruedInterest(Loan $loan, Carbon|string|null $asOfDate = null): float
    {
        $accrued = $this->accruedInterest($loan, $asOfDate);
        $paid = $this->paidInterest($loan, $asOfDate);

        return round(max($accrued - $paid, 0), 2);
    }

    public function monthlyExpectedInterest(Loan $loan, Carbon|string|null $asOfDate = null): float
    {
        $dueInstallmentCount = $this->dueInstallmentsCount($loan, $asOfDate);

        if ($dueInstallmentCount <= 0) {
            return 0.0;
        }

        $scheduledInterest = $this->scheduledMonthlyExpectedInterest($loan, $dueInstallmentCount);
        if ($scheduledInterest !== null) {
            return round($scheduledInterest, 2);
        }

        return round($this->fallbackMonthlyExpectedInterest($loan, $dueInstallmentCount), 2);
    }

    protected function scheduledInterestAccrued(Loan $loan, Carbon $asOf): ?float
    {
        $schedules = $loan->relationLoaded('repaymentSchedules')
            ? $loan->repaymentSchedules
            : $loan->repaymentSchedules()->get();

        if ($schedules->isEmpty()) {
            return null;
        }

        return (float) $schedules
            ->filter(fn ($schedule) => Carbon::parse($schedule->due_date)->lte($asOf->copy()->endOfMonth()))
            ->sum('interest_payment');
    }

    protected function scheduledMonthlyExpectedInterest(Loan $loan, int $dueInstallmentCount): ?float
    {
        $schedules = $loan->relationLoaded('repaymentSchedules')
            ? $loan->repaymentSchedules
            : $loan->repaymentSchedules()->get();

        if ($schedules->isEmpty()) {
            return null;
        }

        $schedule = $schedules
            ->sortBy('repayment_number')
            ->values()
            ->get($dueInstallmentCount - 1);

        return $schedule ? (float) ($schedule->interest_payment ?? 0) : null;
    }

    protected function fallbackInterestAccrued(Loan $loan, Carbon $asOf): float
    {
        $dueInstallmentCount = $this->dueInstallmentsCount($loan, $asOf->copy()->endOfMonth());
        if ($dueInstallmentCount <= 0) {
            return 0.0;
        }

        $principal = (float) ($loan->principal_amount ?? 0);
        $monthlyRate = ((float) ($loan->interest_rate ?? 0)) / 100 / 12;
        $monthlyInsurance = (float) ($loan->monthly_insurance ?? 0);
        $paymentExcludingInsurance = (float) ($loan->payment
            ?? max(((float) ($loan->total_monthly_repayment ?? 0)) - $monthlyInsurance, 0));

        if ($principal <= 0 || $paymentExcludingInsurance <= 0) {
            return 0.0;
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

        $contractedInterest = (float) ($loan->interest_amount ?? 0);

        if ($contractedInterest > 0) {
            return min(round($interestAccrued, 2), round($contractedInterest, 2));
        }

        return round($interestAccrued, 2);
    }

    protected function fallbackMonthlyExpectedInterest(Loan $loan, int $dueInstallmentCount): float
    {
        $principal = (float) ($loan->principal_amount ?? 0);
        $monthlyRate = ((float) ($loan->interest_rate ?? 0)) / 100 / 12;
        $monthlyInsurance = (float) ($loan->monthly_insurance ?? 0);
        $paymentExcludingInsurance = (float) ($loan->payment
            ?? max(((float) ($loan->total_monthly_repayment ?? 0)) - $monthlyInsurance, 0));

        if ($principal <= 0 || $paymentExcludingInsurance <= 0 || $dueInstallmentCount <= 0) {
            return 0.0;
        }

        $balance = $principal;
        $interest = 0.0;

        for ($i = 0; $i < $dueInstallmentCount; $i++) {
            $interest = round($balance * $monthlyRate, 2);
            $principalPortion = max(round($paymentExcludingInsurance - $interest, 2), 0);
            $balance = max(round($balance - $principalPortion, 2), 0);

            if ($balance <= 0 && $i < $dueInstallmentCount - 1) {
                return 0.0;
            }
        }

        return $interest;
    }

    protected function normalizeAsOfDate(Carbon|string|null $asOfDate): Carbon
    {
        if ($asOfDate instanceof Carbon) {
            return $asOfDate->copy()->endOfDay();
        }

        if (is_string($asOfDate) && $asOfDate !== '') {
            return Carbon::parse($asOfDate)->endOfDay();
        }

        return now()->endOfDay();
    }
}
