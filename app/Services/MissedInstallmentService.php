<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\MissedInstallment;
use App\Models\Repayments;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MissedInstallmentService
{
    public function generateForRange(Carbon|string $fromMonth, Carbon|string $toMonth): array
    {
        $startMonth = $fromMonth instanceof Carbon ? $fromMonth->copy() : Carbon::parse($fromMonth);
        $endMonth = $toMonth instanceof Carbon ? $toMonth->copy() : Carbon::parse($toMonth);

        if ($startMonth->gt($endMonth)) {
            [$startMonth, $endMonth] = [$endMonth, $startMonth];
        }

        $cursor = $startMonth->copy()->startOfMonth();
        $endCursor = $endMonth->copy()->startOfMonth();
        $results = [];
        $created = 0;
        $updated = 0;
        $deleted = 0;

        while ($cursor->lte($endCursor)) {
            $result = $this->generateForMonth($cursor->copy());
            $results[] = $result;
            $created += (int) ($result['created'] ?? 0);
            $updated += (int) ($result['updated'] ?? 0);
            $deleted += (int) ($result['deleted'] ?? 0);
            $cursor->addMonthNoOverflow();
        }

        return [
            'from_month' => $startMonth->copy()->startOfMonth()->toDateString(),
            'to_month' => $endMonth->copy()->startOfMonth()->toDateString(),
            'months_processed' => count($results),
            'created' => $created,
            'updated' => $updated,
            'deleted' => $deleted,
            'results' => $results,
        ];
    }

    public function generateForMonth(Carbon|string $month): array
    {
        $monthDate = $month instanceof Carbon ? $month->copy() : Carbon::parse($month);
        $monthStart = $monthDate->copy()->startOfMonth();
        $monthEnd = $monthDate->copy()->endOfMonth();
        $dueMonth = $monthDate->copy()->endOfMonth();
        $generatedAt = now();

        $created = 0;
        $updated = 0;
        $deleted = 0;
        $loanBalanceService = app(LoanBalanceService::class);
        $hasMissedDateColumn = Schema::hasColumn('missed_installments', 'missed_date');
        $hasLoanStatusAtGenerationColumn = Schema::hasColumn('missed_installments', 'loan_status_at_generation');

        DB::transaction(function () use ($monthStart, $monthEnd, $dueMonth, $generatedAt, $loanBalanceService, $hasMissedDateColumn, $hasLoanStatusAtGenerationColumn, &$created, &$updated, &$deleted) {
            $loans = Loan::query()
                ->with(['borrower', 'repaymentSchedules'])
                ->whereIn('loan_status', ['approved', 'partially_paid'])
                ->whereDate('loan_release_date', '<=', $monthEnd->toDateString())
                ->get();

            $existingForMonth = MissedInstallment::query()
                ->whereDate('due_month', $dueMonth->toDateString())
                ->get()
                ->keyBy('loan_id');

            $keptLoanIds = [];

            foreach ($loans as $loan) {
                $loanStatusAtGeneration = $this->resolveLoanStatusAsOf($loan, $generatedAt);

                if ($this->isClosedStatus($loanStatusAtGeneration)) {
                    continue;
                }

                $outstandingBalance = $loanBalanceService->resolveOutstandingBalance($loan);

                if ($outstandingBalance <= 0) {
                    continue;
                }

                if (! $this->isLoanDueForMonth($loan, $dueMonth)) {
                    continue;
                }

                $expected = round((float) ($loan->total_monthly_repayment ?? 0), 2);
                if ($expected <= 0) {
                    continue;
                }

                $paidAmount = round((float) Repayments::query()
                    ->where('loan_id', $loan->loan_id)
                    ->whereBetween('receipt_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->sum('receipt_amount'), 2);

                $repaymentCount = Repayments::query()
                    ->where('loan_id', $loan->loan_id)
                    ->whereBetween('receipt_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->count();

                $shortfall = round(max($expected - $paidAmount, 0), 2);

                if ($shortfall <= 0) {
                    continue;
                }

                $status = $paidAmount > 0 ? 'partial' : 'missed';
                $missedDate = app(LoanInterestAccrualService::class)->dueDateForMonth($loan, $dueMonth);
                $attributes = [
                    'employee_no' => $loan->employee_no,
                    'employer' => $loan->borrower->employer ?? $loan->employer ?? null,
                    'expected_amount' => $expected,
                    'paid_amount' => $paidAmount,
                    'shortfall_amount' => $shortfall,
                    'repayment_count' => $repaymentCount,
                    'status' => $status,
                    'generated_at' => $generatedAt,
                ];

                if ($hasMissedDateColumn) {
                    $attributes['missed_date'] = $missedDate->toDateString();
                }

                if ($hasLoanStatusAtGenerationColumn) {
                    $attributes['loan_status_at_generation'] = $loanStatusAtGeneration;
                }

                $record = $existingForMonth->get($loan->loan_id);
                if ($record) {
                    $record->fill($attributes)->save();
                    $updated++;
                } else {
                    MissedInstallment::create(array_merge($attributes, [
                        'loan_id' => $loan->loan_id,
                        'due_month' => $dueMonth->toDateString(),
                    ]));
                    $created++;
                }

                $keptLoanIds[] = $loan->loan_id;
            }

            $deleteQuery = MissedInstallment::query()->whereDate('due_month', $dueMonth->toDateString());
            if (! empty($keptLoanIds)) {
                $deleteQuery->whereNotIn('loan_id', $keptLoanIds);
            }

            $deleted = $deleteQuery->delete();
        });

        return [
            'month' => $dueMonth->toDateString(),
            'created' => $created,
            'updated' => $updated,
            'deleted' => $deleted,
        ];
    }

    private function isLoanDueForMonth(Loan $loan, Carbon $dueMonth): bool
    {
        $firstDueMonth = app(LoanInterestAccrualService::class)->firstDueMonthEnd($loan);

        return $dueMonth->greaterThanOrEqualTo($firstDueMonth);
    }

    private function resolveLoanStatusAsOf(Loan $loan, Carbon $asOfDate): string
    {
        $currentStatus = trim((string) ($loan->loan_status ?? ''));
        $normalizedCurrentStatus = Str::lower($currentStatus);

        if ($loan->top_up_settled_at && Carbon::parse($loan->top_up_settled_at)->lte($asOfDate)) {
            return 'closed';
        }

        $latestClosingBalanceDate = Repayments::query()
            ->where('loan_id', $loan->loan_id)
            ->whereNotNull('closing_balance')
            ->where('closing_balance', '<=', 0)
            ->whereDate('receipt_date', '<=', $asOfDate->toDateString())
            ->orderByDesc('receipt_date')
            ->orderByDesc('id')
            ->value('receipt_date');

        if ($latestClosingBalanceDate) {
            return 'closed';
        }

        return $normalizedCurrentStatus === '' ? 'unknown' : $currentStatus;
    }

    private function isClosedStatus(?string $status): bool
    {
        return in_array(Str::lower(trim((string) $status)), [
            'closed',
            'paid off',
            'paid_off',
            'paid-off',
            'paidoff',
            'settled',
        ], true);
    }
}
