<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Repayments; // <-- fix this line
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RepaymentScheduleService
{
    public function generateSchedule(string $loanType, string $month): Collection
    {
        $startDate = Carbon::parse($month)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $loans = Loan::query()
            ->activeOpen()
            ->whereDate('loan_release_date', '<=', $endDate)
            ->get();
        $schedules = collect();

        foreach ($loans as $loan) {
            if ($loanType === 'GRZ' && !$this->isNewLoan($loan, $startDate)) {
                continue; // GRZ only includes newly disbursed loans
            }

            $missedInstallments = $this->countMissedInstallments($loan, $startDate);
            $installment = $loan->total_monthly_installment + ($missedInstallments * $loan->total_monthly_installment);

            $scheduleRow = $loanType === 'CNMC'
                ? $this->buildCnmcRow($loan, $installment, $startDate, $missedInstallments)
                : $this->buildGrzRow($loan, $installment, $startDate);

            $schedules->push($scheduleRow);
        }

        return $schedules;
    }

    protected function isNewLoan(Loan $loan, Carbon $month): bool
    {
        return $loan->loan_release_date->between($month->copy()->startOfMonth(), $month->copy()->endOfMonth());
    }

    protected function countMissedInstallments(Loan $loan, Carbon $targetMonth): int
    {
        $monthsSinceStart = app(LoanInterestAccrualService::class)->dueInstallmentsCount($loan, $targetMonth->copy()->endOfMonth());

        $paidMonths = Repayments::where('loan_id', $loan->loan_id)
            ->whereMonth('receipt_date', '<=', $targetMonth->month)
            ->whereYear('receipt_date', '=', $targetMonth->year)
            ->count();

        return max(0, $monthsSinceStart - $paidMonths);
    }

    protected function buildCnmcRow(Loan $loan, float $installment, Carbon $month, int $loanCycle): array
    {
        $interest = ($loan->interest_rate / 100 / 12) * $loan->principal_amount;
        $principal = $installment - $interest - $loan->monthly_insurance;

        return [
            'loan_id'              => $loan->loan_id,
            'Employer'             => $loan->employer ?? 'CNMC',
            'Deduction Code'       => $loan->deduction_code ?? '747',
            'Emp No.'              => $loan->employee_no,
            'Loan Amount'          => number_format($loan->principal_amount, 2),
            'Installment'          => number_format($loan->total_monthly_repayment, 2),
            'Principal'            => number_format($principal, 2),
            'Interest'             => number_format($interest, 2),
            'Insurance'            => number_format($loan->monthly_insurance, 2),
            'Loan Term'            => $loan->loan_duration,
            'Loan Cycle (Other)'   => $loanCycle,
            'Outstanding Balances' => number_format($loan->balance, 2),
        ];
    }

    protected function buildGrzRow(Loan $loan, float $installment, Carbon $month): array
    {
        return [
            'loan_id'     => $loan->loan_id,
            'PERNR'       => $loan->employee_no,
            'LGART'       => '8000',
            'ENDDA'       => optional($loan->loan_due_date)->copy()->endOfMonth()->format('d.m.Y'), // Last day of loan_due_date's month
            'BEGDA'       => $month->copy()->startOfMonth()->format('d.m.Y'),
            'BETRG'       => number_format($loan->total_monthly_repayment, 2),
            'EMFSL'       => 'F364',
            'ZLSCH'       => 'E',
            'NRC No.'     => $loan->borrower->identification ?? '',
            'First Name'  => Str::upper($loan->borrower->first_name ?? ''),
            'Last Name'   => Str::upper($loan->borrower->last_name ?? ''),
        ];
    }

    public function computeRepaymentNumber(\App\Models\Loan $loan, \Carbon\Carbon $receiptDate): int
    {
        $firstPaymentDate = app(LoanInterestAccrualService::class)->firstPaymentDate($loan);

        if (! $firstPaymentDate) {
            return 1;
        }

        $term = (int) ($loan->loan_duration ?? 9999);

        $repaymentNumber = min(
            max(1, $firstPaymentDate->diffInMonths($receiptDate) + 1),
            $term
        );

        return (int) $repaymentNumber;
    }
}
