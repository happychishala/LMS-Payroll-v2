<?php

namespace App\Services;

use App\Models\Loan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ThirdPartyRefinancingQueryBuilder
{
    /**
     * Build report rows as an array collection (ready for CSV or preview).
     *
     * Filters:
     * - $startDate/$endDate: loan_release_date range (YYYY-MM-DD)
     * - $employer: loans.employer equals value
     * - $thirdPartyName: matches any of third_party_name, _2, _3
     * - $recencyFilter: one of the DIA-based labels (see map below), or null
     */
    public static function build(
        ?string $startDate,
        ?string $endDate,
        ?string $employer,
        ?string $thirdPartyName,
        ?string $recencyFilter = null
    ): Collection {
        $q = Loan::with(['borrower', 'loan_type', 'repayments'])
            ->where(function ($q) {
                $q->where(function ($q) {
                    $q->whereNotNull('third_party_name')->where('third_party_balance', '>', 0);
                })->orWhere(function ($q) {
                    $q->whereNotNull('third_party_name_2')->where('third_party_balance_2', '>', 0);
                })->orWhere(function ($q) {
                    $q->whereNotNull('third_party_name_3')->where('third_party_balance_3', '>', 0);
                })->orWhere(function ($q) {
                    $q->whereNotNull('total_third_party_balance')->where('total_third_party_balance', '>', 0);
                });
            });

        if ($startDate && $endDate) {
            $q->whereBetween('loan_release_date', [$startDate, $endDate]);
        } elseif ($startDate) {
            $q->whereDate('loan_release_date', '>=', $startDate);
        } elseif ($endDate) {
            $q->whereDate('loan_release_date', '<=', $endDate);
        }

        if ($employer) {
            $q->where('employer', $employer);
        }

        if ($thirdPartyName) {
            $q->where(function ($x) use ($thirdPartyName) {
                $x->where('third_party_name', $thirdPartyName)
                  ->orWhere('third_party_name_2', $thirdPartyName)
                  ->orWhere('third_party_name_3', $thirdPartyName);
            });
        }

        $asOf = now(); // report run date for DIA/VIA/Recency
        $loans = $q->orderBy('loan_release_date')->get();

        $rows = $loans->map(function ($loan) use ($asOf) {
            // Third-party payouts
            $tp1 = max(0, (float)($loan->third_party_balance ?? 0));
            $tp2 = max(0, (float)($loan->third_party_balance_2 ?? 0));
            $tp3 = max(0, (float)($loan->third_party_balance_3 ?? 0));
            $tpTotal = (float)($loan->total_third_party_balance ?? ($tp1 + $tp2 + $tp3));

            $netToBorrower  = (float)($loan->disbursement_amount ?? 0);
            $disbursedTotal = $netToBorrower + $tpTotal;

            // Payments & arrears
            $monthly    = (float)($loan->total_monthly_repayment ?? 0);
            $totalPaid  = (float)$loan->repayments->sum('receipt_amount');

            $releaseAt  = $loan->loan_release_date ? Carbon::parse($loan->loan_release_date) : null;
            $monthsSinceDisb = $releaseAt ? $releaseAt->diffInMonths($asOf) : 0;
            $expected   = $monthly * $monthsSinceDisb;
            $via        = max(0, $expected - $totalPaid);
            $dia        = $monthly > 0 ? floor($via / $monthly) * 30 : 0;

            // First payroll receipt month (Y/N)
            $firstReceiptAt = $loan->repayments->min('receipt_date') ?? $loan->repayments->min('created_at');
            $firstReceiptMonthYesNo = 'N';
            if ($releaseAt && $firstReceiptAt) {
                $firstExpectedPayrollMonth = $releaseAt->copy()->addMonth()->format('Y-m');
                $firstActualMonth = Carbon::parse($firstReceiptAt)->format('Y-m');
                $firstReceiptMonthYesNo = $firstActualMonth === $firstExpectedPayrollMonth ? 'Y' : 'N';
            }

            // Recency based on DIA (days in arrears)
            $recency = self::recencyFromDia((int)$dia);

            $borrowerName = self::resolveBorrowerName($loan);

            return [
                'Disbursement Date'           => $releaseAt?->format('Y-m-d'),
                'Loan ID'                     => $loan->loan_id ?? 'N/A',
                'Borrower ID'                 => $loan->borrower_id ?? 'N/A',
                'Borrower Name'               => $borrowerName ?: 'N/A',
                'Employer'                    => $loan->employer ?? 'N/A',
                'Loan Name'                   => optional($loan->loan_type)->loan_name ?? 'Unknown',
                'Third-Party Name(s)'         => collect([$loan->third_party_name, $loan->third_party_name_2, $loan->third_party_name_3])->filter()->implode(' | '),
                'Amount Paid to Third Party'  => $tpTotal,
                'Net to Borrower'             => $netToBorrower,
                'Disbursed Amount (Total)'    => $disbursedTotal,
                'Reference/POP No.'           => $loan->transaction_reference ?? null,
                'Created By'                  => $loan->from_this_account ?? null, // adjust if you have created_by
                'First Payroll Receipt (Y/N)' => $firstReceiptMonthYesNo,
                'DIA'                         => $dia,
                'VIA'                         => $via,
                'Recency'                     => $recency,
            ];
        });

        if ($recencyFilter) {
            $rows = $rows->where('Recency', $recencyFilter)->values();
        }

        return $rows->values();
    }

    /**
     * Map DIA -> Recency label per your classification.
     */
    public static function recencyFromDia(int $dia): string
    {
        if ($dia <= 0)                 return 'Pass (0 days)';
        if ($dia <= 59)                return 'Pass (1 to 59)';
        if ($dia <= 89)                return 'Special Mention (60 to 89)';
        if ($dia <= 119)               return 'Substandard (1) (90 to 119)';
        if ($dia <= 179)               return 'Substandard (2) (120 to 179)';
        if ($dia <= 269)               return 'Doubtful (1) (180 to 269)';
        if ($dia <= 364)               return 'Doubtful (2) (270 to 364)';
        return 'Loss (365+)';
    }

    /**
     * Summarize a built rows collection for the snapshot.
     */
    public static function summarize(Collection $rows): array
    {
        // Totals
        $totalLoans        = $rows->count();
        $totalThirdParty   = (float) $rows->sum('Amount Paid to Third Party');
        $totalNetToBorrow  = (float) $rows->sum('Net to Borrower');
        $totalDisbursed    = (float) $rows->sum('Disbursed Amount (Total)');
        $totalVIA          = (float) $rows->sum('VIA');

        // Recency breakdown (new labels)
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

        // DIA buckets (keep simple 0/30/60/90+ for quick glance)
        $diaBuckets = ['0' => 0, '30' => 0, '60' => 0, '90+' => 0];
        $rows->each(function ($r) use (&$diaBuckets) {
            $dia = (int) ($r['DIA'] ?? 0);
            if ($dia <= 0)      $diaBuckets['0']++;
            elseif ($dia <= 30) $diaBuckets['30']++;
            elseif ($dia <= 60) $diaBuckets['60']++;
            else                $diaBuckets['90+']++;
        });

        return [
            'totals'  => [
                'loans'           => $totalLoans,
                'third_party'     => $totalThirdParty,
                'net_to_borrower' => $totalNetToBorrow,
                'disbursed'       => $totalDisbursed,
                'via'             => $totalVIA,
            ],
            'recency' => $recencyCounts,
            'dia'     => $diaBuckets,
        ];
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
