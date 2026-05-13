<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\Repayments;
use App\Services\RepaymentImportService;
use App\Services\RepaymentNumberResolver;
use Illuminate\Console\Command;

class FixRepaymentNumbers extends Command
{
    protected $signature = 'repayments:fix-numbers
        {--loan= : Limit correction to one loan_id or loan_number}
        {--dry-run : Show mismatches without saving changes}';

    protected $description = 'Correct repayment_number values from each repayment loan and receipt date.';

    public function handle(RepaymentNumberResolver $resolver, RepaymentImportService $importService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $loanFilter = $this->option('loan');

        $loans = Loan::query()
            ->when($loanFilter, function ($query) use ($loanFilter) {
                $query->where(function ($subQuery) use ($loanFilter) {
                    $subQuery->where('loan_id', $loanFilter)
                        ->orWhere('loan_number', $loanFilter);
                });
            })
            ->get()
            ->keyBy('loan_id');

        if ($loanFilter && $loans->isEmpty()) {
            $this->error("Loan not found: {$loanFilter}");

            return self::FAILURE;
        }

        $checked = 0;
        $corrected = 0;
        $skipped = 0;
        $changedLoanIds = [];

        Repayments::query()
            ->when($loanFilter, function ($query) use ($loans) {
                $query->whereIn('loan_id', $loans->keys());
            })
            ->orderBy('id')
            ->chunkById(500, function ($repayments) use ($resolver, $loans, $dryRun, &$checked, &$corrected, &$skipped, &$changedLoanIds) {
                foreach ($repayments as $repayment) {
                    $checked++;

                    $loan = $loans->get($repayment->loan_id);
                    if (! $loan || ! $repayment->receipt_date) {
                        $skipped++;
                        continue;
                    }

                    $expected = $resolver->expectedFor($loan, $repayment->receipt_date);
                    if ($expected === null || (int) $repayment->repayment_number === $expected) {
                        continue;
                    }

                    $this->line(sprintf(
                        '%s repayment #%d: %s -> %d',
                        $repayment->loan_id,
                        $repayment->id,
                        $repayment->repayment_number ?? 'NULL',
                        $expected
                    ));

                    if (! $dryRun) {
                        $repayment->repayment_number = $expected;
                        $repayment->save();
                        $changedLoanIds[$repayment->loan_id] = true;
                    }

                    $corrected++;
                }
            });

        if (! $dryRun && $changedLoanIds !== []) {
            foreach (array_keys($changedLoanIds) as $loanId) {
                $loan = $loans->get($loanId) ?? Loan::query()->where('loan_id', $loanId)->first();

                if ($loan) {
                    $importService->recomputeLoanLedger($loan);
                }
            }
        }

        $action = $dryRun ? 'would be corrected' : 'corrected';
        $ledgerMessage = (! $dryRun && $changedLoanIds !== [])
            ? ' Recomputed ledgers for ' . count($changedLoanIds) . ' loan(s).'
            : '';
        $this->info("Checked {$checked} repayment(s); {$corrected} {$action}; {$skipped} skipped.{$ledgerMessage}");

        return self::SUCCESS;
    }
}
