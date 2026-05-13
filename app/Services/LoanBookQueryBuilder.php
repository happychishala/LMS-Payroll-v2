<?php

namespace App\Services;

use App\Models\Loan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LoanBookQueryBuilder
{
    public static function build(
        ?string $asOfDate,
        ?string $employer,
        ?string $loanName,
        ?string $status
    ): Collection {
        $asOf = $asOfDate ? Carbon::parse($asOfDate)->endOfDay() : now();
        $interestAccruals = app(LoanInterestAccrualService::class);

        $q = Loan::with(['borrower', 'loan_type', 'repayments', 'repaymentSchedules']);

        if ($employer) {
            $q->where('employer', $employer);
        }
        if ($loanName) {
            $q->whereHas('loan_type', fn ($x) => $x->where('loan_name', $loanName));
        }
        if ($status) {
            $q->where('loan_status', $status);
        }

        $q->whereNotNull('loan_release_date');

        $loans = $q->orderBy('loan_release_date', 'desc')->get();

        return $loans->map(function ($loan) use ($asOf, $interestAccruals) {
            $principalOutstanding = (float) ($loan->balance ?? 0);
            $remainingInterest    = $interestAccruals->unpaidAccruedInterest($loan, $asOf);
            $remainingInsurance   = (float) ($loan->insurance_fee ?? 0);

            $principalPlusInterest = $principalOutstanding + $remainingInterest;

            $startingRecoverable = (float) ($loan->total_recoverable ?? ($principalOutstanding + $remainingInterest + $remainingInsurance));
            $totalPaid = (float) $loan->repayments->sum('receipt_amount');
            $currentRecoverable = max(0, $startingRecoverable - $totalPaid);

            $monthly = (float) ($loan->total_monthly_repayment ?? 0);

            $monthsSinceDisb = $loan->loan_release_date
                ? Carbon::parse($loan->loan_release_date)->diffInMonths($asOf)
                : 0;
            $expected = $monthly * $monthsSinceDisb;
            $via = max(0, $expected - $totalPaid);

            $dia = $monthly > 0 ? floor($via / max(1e-9, $monthly)) * 30 : 0;
            $recency = self::recencyFromDia((int) $dia);

            $remainingTerm = $monthly > 0 ? (int) ceil($currentRecoverable / $monthly) : null;

            $cycle = null;
            if ($loan->borrower_id && $loan->loan_release_date) {
                $cycle = Loan::where('borrower_id', $loan->borrower_id)
                    ->whereNotNull('loan_release_date')
                    ->whereDate('loan_release_date', '<=', $loan->loan_release_date)
                    ->count();
            }

            return [
                'Loan ID'                     => $loan->loan_id ?? 'N/A',
                'Borrower ID'                 => self::resolveBorrowerIdentifier($loan),
                'Borrower Name'               => self::resolveBorrowerName($loan) ?: 'N/A',
                'Employer'                    => $loan->employer ?? 'N/A',
                'Loan Name'                   => optional($loan->loan_type)->loan_name ?? 'Unknown',
                'Issue Date'                  => $loan->loan_release_date ? Carbon::parse($loan->loan_release_date)->format('Y-m-d') : null,
                'Loan Cycle'                  => $cycle,
                'Original Term (Months)'      => $loan->term_months ?? $loan->loan_duration ?? null,
                'Monthly Instalment'          => $monthly,
                'Principal Outstanding'       => $principalOutstanding,
                'Principal + Interest (GRZ)'  => $principalPlusInterest,
                'Current Total Recoverable'   => $currentRecoverable,
                'Remaining Term (Recalc)'     => $remainingTerm,
                'Last Payment Date'           => $loan->repayments->max('receipt_date') ?? $loan->repayments->max('created_at'),
                'DIA'                         => $dia,
                'VIA'                         => $via,
                'Recency'                     => $recency,
                'Status'                      => $loan->loan_status ?? 'N/A',
            ];
        })->values();
    }

    /** Snapshot summary for header cards/tables */
    public static function summarize(Collection $rows): array
    {
        $count = $rows->count();

        $sumPrincipal   = (float) $rows->sum('Principal Outstanding');
        $sumPI          = (float) $rows->sum('Principal + Interest (GRZ)');
        $sumRecoverable = (float) $rows->sum('Current Total Recoverable');
        $sumVia         = (float) $rows->sum('VIA');

        $avgDia = $count ? round($rows->avg('DIA'), 1) : 0.0;

        // Remaining Term numeric only
        $remainingTerms = $rows->pluck('Remaining Term (Recalc)')->filter(fn($v) => is_numeric($v));
        $avgRemainingTerm = $remainingTerms->count() ? round($remainingTerms->avg(), 1) : 0.0;

        // Recency breakdown (your taxonomy)
        $labels = [
            'Pass (0 days)',
            'Pass (1 to 59)',
            'Special Mention (60 to 89)',
            'Substandard (1) (90 to 119)',
            'Substandard (2) (120 to 179)',
            'Doubtful (1) (180 to 269)',
            'Doubtful (2) (270 to 364)',
            'Loss (365+)',
        ];
        $recencyCounts = [];
        foreach ($labels as $label) {
            $recencyCounts[$label] = $rows->where('Recency', $label)->count();
        }

        return [
            'totals' => [
                'loans'               => $count,
                'principal'           => $sumPrincipal,
                'principal_interest'  => $sumPI,
                'current_recoverable' => $sumRecoverable,
                'via'                 => $sumVia,
                'avg_dia'             => $avgDia,
                'avg_remaining_term'  => $avgRemainingTerm,
            ],
            'recency' => $recencyCounts,
        ];
    }

    /** DIA → Recency labels (your taxonomy) */
    public static function recencyFromDia(int $dia): string
    {
        if ($dia <= 0)   return 'Pass (0 days)';
        if ($dia <= 59)  return 'Pass (1 to 59)';
        if ($dia <= 89)  return 'Special Mention (60 to 89)';
        if ($dia <= 119) return 'Substandard (1) (90 to 119)';
        if ($dia <= 179) return 'Substandard (2) (120 to 179)';
        if ($dia <= 269) return 'Doubtful (1) (180 to 269)';
        if ($dia <= 364) return 'Doubtful (2) (270 to 364)';
        return 'Loss (365+)';
    }

    protected static function resolveBorrowerIdentifier(Loan $loan): string
    {
        return (string) ($loan->borrower?->customer_id ?: $loan->borrower_id ?: 'N/A');
    }

    protected static function resolveBorrowerName(Loan $loan): string
    {
        $borrower = $loan->borrower;

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
                return trim(preg_replace('/\s*-\s*\d+$/', '', $fullName));
            }
        }

        return trim(collect([
            $loan->other_names,
            $loan->last_name,
        ])->filter()->implode(' '));
    }
}
