<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\Repayments;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncLoanBalancesFromRepayments extends Command
{
    protected $signature = 'loans:sync-balances-from-repayments {--dry-run : Preview changes without updating loans}';

    protected $description = 'Sync loan balances from the latest repayment receipt for loans that have repayments.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $loanIds = Repayments::query()->distinct()->pluck('loan_id');

        $checked = 0;
        $updated = 0;
        $skipped = 0;

        Loan::query()
            ->whereIn('loan_id', $loanIds)
            ->chunkById(200, function ($loans) use ($dryRun, &$checked, &$updated, &$skipped) {
                foreach ($loans->sortBy('loan_id')->values() as $loan) {
                    $checked++;

                    $latestRepayment = Repayments::query()
                        ->where('loan_id', $loan->loan_id)
                        ->where(function ($query) {
                            $query->whereNotNull('receipt_date')
                                ->orWhereNotNull('payment_date')
                                ->orWhereNotNull('created_at');
                        })
                        ->orderByRaw('COALESCE(receipt_date, payment_date, created_at) DESC')
                        ->orderByDesc('id')
                        ->first();

                    if (! $latestRepayment) {
                        $skipped++;

                        continue;
                    }

                    $resolvedBalance = $latestRepayment->closing_balance;
                    if ($resolvedBalance === null) {
                        $resolvedBalance = $latestRepayment->balance;
                    }

                    if ($resolvedBalance === null) {
                        $skipped++;

                        continue;
                    }

                    $resolvedBalance = round((float) $resolvedBalance, 2);
                    $currentBalance = round((float) ($loan->balance ?? 0), 2);

                    if (abs($currentBalance - $resolvedBalance) < 0.01) {
                        $skipped++;

                        continue;
                    }

                    $this->line(sprintf(
                        '%s: %0.2f -> %0.2f (%s)',
                        $loan->loan_id,
                        $currentBalance,
                        $resolvedBalance,
                        (string) (optional($latestRepayment->receipt_date ?? $latestRepayment->payment_date ?? $latestRepayment->created_at)->toDateString() ?? 'no-date')
                    ));

                    if (! $dryRun) {
                        DB::table('loans')
                            ->where('id', $loan->id)
                            ->update([
                                'balance' => $resolvedBalance,
                                'updated_at' => now(),
                            ]);
                    }

                    $updated++;
                }
            }, 'id');

        $this->info(sprintf(
            'Checked %d loans with repayments. Updated %d. Skipped %d.%s',
            $checked,
            $updated,
            $skipped,
            $dryRun ? ' Dry run only.' : ''
        ));

        return self::SUCCESS;
    }
}
