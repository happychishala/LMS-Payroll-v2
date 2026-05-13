<?php

namespace App\Services;

use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EmployerCsvReportQueryBuilder
{
    public static function build(string $loanName, string $month, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $monthEnd = Carbon::parse($month)->endOfMonth();
        $matchingLoanTypeIds = self::resolveLoanTypeIds($loanName);
        $interestAccruals = app(LoanInterestAccrualService::class);

        $query = Loan::with(['borrower', 'repayments', 'repaymentSchedules', 'loan_type'])
            ->when(
                $matchingLoanTypeIds->isNotEmpty(),
                fn ($q) => $q->whereIn('loan_type_id', $matchingLoanTypeIds->all()),
                fn ($q) => $q->whereHas('loan_type', fn ($loanTypeQuery) => $loanTypeQuery->where('loan_name', $loanName))
            )
            ->whereNotNull('loan_release_date');

        if ($startDate && $endDate) {
            $query->whereBetween('loan_release_date', [$startDate, $endDate]);
        }

        return $query->orderBy('loan_release_date')->get()->filter(function (Loan $loan) use ($monthEnd) {
            return self::dueInstallmentsCount($loan, $monthEnd) > 0;
        })->map(function (Loan $loan) use ($monthEnd, $interestAccruals) {
            $monthlyInstalment = (float) ($loan->total_monthly_repayment ?? 0);
            $totalPaid = (float) $loan->repayments
                ->filter(fn ($repayment) => $repayment->receipt_date && $repayment->receipt_date->lte($monthEnd))
                ->sum('receipt_amount');
            $expectedTotal = $monthlyInstalment * $interestAccruals->dueInstallmentsCount($loan, $monthEnd);
            $via = max(0, $expectedTotal - $totalPaid);
            $dia = $monthlyInstalment > 0 ? floor($via / $monthlyInstalment) * 30 : 0;
            $borrower = self::resolveBorrower($loan);
            $unpaidAccruedInterest = $interestAccruals->unpaidAccruedInterest($loan, $monthEnd);

            return [
                'Loan Name' => optional($loan->loan_type)->loan_name ?? 'Unknown',
                //'Employer Code' => $loan->employer_code,
                'Borrower ID' => $borrower?->customer_id ?: $loan->borrower_id ?: 'N/A',
                'Employee Number' => $loan->employee_no ?? 'N/A',
                'Borrower Name' => self::resolveBorrowerName($loan, $borrower) ?: 'N/A',
                'Loan ID' => $loan->loan_id ?? 'N/A',
                'Loan Type' => $loan->loan_category ?? 'N/A',
                'Instalment Amount' => $monthlyInstalment,
                'Instalment Month' => $monthEnd->format('Y-m'),
                'Outstanding Principal' => (float) ($loan->balance ?? 0),
                'Outstanding P+I' => (float) (($loan->balance ?? 0) + $unpaidAccruedInterest),
                'DIA' => $dia,
                'VIA' => $via,
                'Status' => $loan->loan_status ?? 'N/A',
                'Cycle' => self::resolveCycle($loan),
            ];
        })->values();
    }

    protected static function resolveBorrower(Loan $loan): ?Borrower
    {
        return $loan->borrower
            ?: Borrower::query()->find($loan->borrower_id)
            ?: Borrower::query()->where('customer_id', $loan->borrower_id)->first();
    }

    protected static function resolveBorrowerName(Loan $loan, ?Borrower $borrower): string
    {
        if ($borrower) {
            $name = trim(collect([
                $borrower->first_name ?: $borrower->other_names,
                $borrower->last_name,
            ])->filter()->implode(' '));

            if ($name !== '') {
                return $name;
            }

            $fullName = trim((string) ($borrower->full_name ?? ''));

            if ($fullName !== '') {
                return trim((string) preg_replace('/\s*-\s*\d+$/', '', $fullName));
            }
        }

        return trim(collect([
            $loan->other_names,
            $loan->last_name,
        ])->filter()->implode(' '));
    }

    protected static function resolveCycle(Loan $loan): ?int
    {
        if (! $loan->borrower_id || ! $loan->loan_release_date) {
            return null;
        }

        return Loan::query()
            ->where('borrower_id', $loan->borrower_id)
            ->whereNotNull('loan_release_date')
            ->whereDate('loan_release_date', '<=', $loan->loan_release_date)
            ->count();
    }

    protected static function resolveLoanTypeIds(string $loanName): Collection
    {
        $normalized = self::normalizeLookupValue($loanName);
        $aliases = self::loanTypeAliases($loanName)->map(fn (string $value) => self::normalizeLookupValue($value));

        return LoanType::query()
            ->get(['id', 'loan_name'])
            ->filter(function (LoanType $loanType) use ($normalized, $aliases) {
                $typeName = self::normalizeLookupValue($loanType->loan_name);

                if ($typeName === $normalized) {
                    return true;
                }

                if (str_contains($typeName, $normalized) || str_contains($normalized, $typeName)) {
                    return true;
                }

                return $aliases->contains(fn (string $alias) => $alias !== '' && (
                    $typeName === $alias
                    || str_contains($typeName, $alias)
                    || str_contains($alias, $typeName)
                ));
            })
            ->pluck('id')
            ->values();
    }

    protected static function loanTypeAliases(string $loanName): Collection
    {
        $normalized = self::normalizeLookupValue($loanName);
        $withoutYear = preg_replace('/(19|20)\d{2}$/', '', $normalized);

        $aliases = collect([$loanName, $withoutYear]);

        if (str_contains($normalized, 'grz')) {
            $aliases = $aliases->merge(['payroll grz', 'payroll_grz', 'grz']);
        }

        if (str_contains($normalized, 'cnmc')) {
            $aliases = $aliases->merge(['payroll cnmc', 'payroll_cnmc', 'cnmc']);
        }

        return $aliases->filter()->unique()->values();
    }

    protected static function normalizeLookupValue(?string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower(trim((string) $value)));
    }
}
