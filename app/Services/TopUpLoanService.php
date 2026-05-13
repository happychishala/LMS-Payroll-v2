<?php

namespace App\Services;

use App\Filament\Resources\LoanResource;
use App\Models\Loan;
use App\Models\LoanType;
use App\Models\RepaymentSchedule;
use App\Models\Repayments;
use App\Models\TopUpLoanBatch;
use App\Models\TopUpLoanItem;
use App\Services\WithholdingService;
use Carbon\Carbon;
use Haruncpi\LaravelIdGenerator\IdGenerator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TopUpLoanService
{
    public function previewTopUp(
        string|int|null $borrowerId,
        array $selectedLoanIds,
        float $topUpAmount = 0,
        Carbon|string|null $releaseDate = null,
        int|string|null $loanTypeId = null,
    ): ?array
    {
        $selectedLoanIds = $this->normalizeLoanIds($selectedLoanIds);

        if (! $borrowerId || $selectedLoanIds === [] || ! $loanTypeId) {
            return null;
        }

        $releaseDate = $releaseDate instanceof Carbon
            ? $releaseDate->copy()->startOfDay()
            : Carbon::parse($releaseDate ?? now())->startOfDay();
        $topUpAmount = round(max(0, $topUpAmount), 2);

        $loans = $this->fetchEligibleLoans($borrowerId, $selectedLoanIds)->get();
        $this->guardEligibleLoans($loans, $selectedLoanIds);

        return $this->buildPreview($loans, max(0, $topUpAmount), $releaseDate, (int) $loanTypeId);
    }

    public function createTopUp(
        string|int $borrowerId,
        array $selectedLoanIds,
        float $topUpAmount,
        Carbon|string|null $releaseDate = null,
        int|string|null $loanTypeId = null,
    ): array
    {
        $selectedLoanIds = $this->normalizeLoanIds($selectedLoanIds);
        $releaseDate = $releaseDate instanceof Carbon
            ? $releaseDate->copy()->startOfDay()
            : Carbon::parse($releaseDate ?? now())->startOfDay();
        $topUpAmount = round(max(0, $topUpAmount), 2);
        $loanTypeId = (int) $loanTypeId;

        if ($loanTypeId <= 0) {
            throw new \RuntimeException('Select the loan type for the new top-up loan.');
        }

        return DB::transaction(function () use ($borrowerId, $selectedLoanIds, $topUpAmount, $releaseDate, $loanTypeId) {
            $loans = $this->fetchEligibleLoans($borrowerId, $selectedLoanIds)
                ->lockForUpdate()
                ->get();

            $this->guardEligibleLoans($loans, $selectedLoanIds);
            $preview = $this->buildPreview($loans, $topUpAmount, $releaseDate, $loanTypeId);
            $templateLoan = $preview['template_loan'];
            $newLoan = $this->createNewLoan(
                $templateLoan,
                $releaseDate,
                $preview['new_principal'],
                $preview['financials'],
                $preview['loan_type'],
                $preview['topup_amount'],
                $preview['total_outstanding'],
                $preview['withholding_amount'],
            );
            $this->syncRepaymentSchedule($newLoan);
            app(WithholdingService::class)->syncForLoan($newLoan->load('borrower', 'loan_type'));

            $batch = TopUpLoanBatch::create([
                'batch_reference' => 'TOPUP-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4)),
                'borrower_id' => $templateLoan->borrower_id,
                'new_loan_id' => $newLoan->loan_id,
                'topup_amount' => $preview['topup_amount'],
                'total_settled_amount' => $preview['total_outstanding'],
                'new_principal_amount' => $preview['new_principal'],
                'source_loan_count' => $loans->count(),
                'loan_release_date' => $releaseDate->toDateString(),
                'created_by' => auth()->id(),
                'metadata' => [
                    'source_loan_ids' => $loans->pluck('loan_id')->values()->all(),
                    'template_loan_id' => $templateLoan->loan_id,
                    'estimated_net_disbursement' => $preview['net_disbursement'],
                    'estimated_fees' => $preview['fees_total'],
                ],
            ]);

            $newLoan->forceFill([
                'top_up_batch_reference' => $batch->batch_reference,
            ])->save();

            $settlementRepaymentIds = [];
            foreach ($loans as $loan) {
                $balance = round((float) ($loan->balance ?? 0), 2);
                $statusBefore = (string) ($loan->loan_status ?? '');
                $settlementRepayment = $this->settleLoan($loan, $releaseDate, $newLoan->loan_id, $batch->batch_reference);
                $settlementRepaymentIds[] = $settlementRepayment->id;

                TopUpLoanItem::create([
                    'top_up_loan_batch_id' => $batch->id,
                    'old_loan_id' => $loan->loan_id,
                    'old_loan_number' => $loan->loan_number,
                    'old_balance' => $balance,
                    'settlement_repayment_id' => $settlementRepayment->id,
                    'old_status_before' => $statusBefore,
                    'old_status_after' => 'closed',
                    'metadata' => [
                        'new_loan_id' => $newLoan->loan_id,
                        'settlement_reference' => $settlementRepayment->reference_number,
                    ],
                ]);
            }

            $batch->metadata = array_merge($batch->metadata ?? [], [
                'settlement_repayment_ids' => $settlementRepaymentIds,
            ]);
            $batch->save();

            return [
                'batch' => $batch,
                'new_loan' => $newLoan,
                'settled_loans' => $loans,
            ];
        });
    }

    private function createNewLoan(
        Loan $templateLoan,
        Carbon $releaseDate,
        float $newPrincipal,
        array $financials,
        LoanType $loanType,
        float $topUpAmount,
        float $sourceTotal,
        float $withholdingAmount,
    ): Loan
    {
        $newLoanId = $this->generateLoanId();
        $loanNumber = IdGenerator::generate([
            'table' => 'loans',
            'field' => 'loan_number',
            'length' => 12,
            'prefix' => 'LN-',
        ]);

        $payload = Arr::except($templateLoan->getAttributes(), [
            'id',
            'loan_id',
            'loan_number',
            'balance',
            'loan_status',
            'loan_release_date',
            'loan_due_date',
            'maturity_date',
            'transaction_reference',
            'loan_settlement_file_path',
            'created_at',
            'updated_at',
        ]);

        $duration = (int) ($templateLoan->loan_duration ?? $templateLoan->term_months ?? 0);

        $loan = new Loan();
        $loan->forceFill(array_merge($payload, [
            'loan_id' => $newLoanId,
            'loan_number' => $loanNumber,
            'loan_type_id' => $loanType->id,
            'loan_status' => 'approved',
            'loan_category' => $templateLoan->loan_category ?: 'Top-Up',
            'loan_release_date' => $releaseDate->toDateString(),
            'loan_due_date' => $this->resolveDueDate($releaseDate, $duration)->toDateString(),
            'maturity_date' => $this->resolveDueDate($releaseDate, $duration)->toDateString(),
            'transaction_reference' => 'TOPUP-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6)),
            'principal_amount' => $newPrincipal,
            'balance' => $newPrincipal,
            'interest_rate' => $financials['interest_rate'],
            'payment' => $financials['payment'],
            'repayment_amount' => $financials['repayment_amount'],
            'interest_amount' => $financials['interest_amount'],
            'admin_fee' => $financials['admin_fee'],
            'insurance_fee' => $financials['insurance_fee'],
            'monthly_insurance' => $financials['monthly_insurance'],
            'total_monthly_repayment' => $financials['total_monthly_repayment'],
            'arrangement_fee' => $financials['arrangement_fee'],
            'crb_fee' => $financials['crb_fee'],
            'disbursement_amount' => $financials['disbursement_amount'],
            'final_disbursement_amount' => $financials['disbursement_amount'],
            'total_recoverable' => $financials['repayment_amount'],
            'total_third_party_balance' => $sourceTotal,
            'loan_settlement_file_path' => null,
            'status_reason_id' => null,
            'first_repayment_date' => $this->firstRepaymentDate($templateLoan, $releaseDate)->toDateString(),
            'withholding_amount' => $withholdingAmount,
            'top_up_amount' => $topUpAmount,
            'top_up_source_total' => $sourceTotal,
            'top_up_parent_loan_id' => $templateLoan->loan_id,
            'top_up_batch_reference' => null,
        ]));
        $loan->save();

        return $loan->fresh(['borrower', 'loan_type']);
    }

    private function settleLoan(Loan $loan, Carbon $settlementDate, string $newLoanId, string $batchReference): Repayments
    {
        $openingBalance = round((float) ($loan->balance ?? 0), 2);
        $repaymentNumber = ((int) Repayments::query()->where('loan_id', $loan->loan_id)->max('repayment_number')) + 1;

        $repayment = Repayments::create([
            'loan_id' => $loan->loan_id,
            'employee_no' => $loan->employee_no,
            'nrc' => $loan->nrc,
            'receipt_date' => $settlementDate->toDateString(),
            'receipt_amount' => $openingBalance,
            'paid_principal' => $openingBalance,
            'paid_interest' => 0,
            'insurance_paid' => 0,
            'opening_balance' => $openingBalance,
            'closing_balance' => 0,
            'repayment_number' => max(1, $repaymentNumber),
            'monthly_repayment_balance' => $loan->total_monthly_repayment ?? 0,
            'payment_status' => 'top_up',
            'sanlam' => $loan->monthly_insurance ?? 0,
            'zed_fin' => 0,
            'interest_rate' => $loan->interest_rate,
            'loan_amount' => $loan->principal_amount,
            'term' => $loan->loan_duration,
            'loan_issue_date' => $loan->loan_release_date,
            'employer' => $loan->borrower->employer ?? $loan->employer ?? null,
            'status' => 'top_up',
            'balance' => 0,
            'payments' => $openingBalance,
            'principal' => $openingBalance,
            'payments_method' => 'top_up',
            'reference_number' => $batchReference . '-' . $loan->loan_id,
            'loan_number' => $loan->loan_number,
            'payment_date' => $settlementDate->toDateString(),
        ]);

        RepaymentSchedule::query()
            ->where('loan_id', $loan->loan_id)
            ->whereIn('payment_status', ['Unpaid', 'Partial'])
            ->update([
                'payment_status' => 'Paid',
                'closing_balance' => 0,
                'updated_at' => now(),
            ]);

        $loan->forceFill([
            'balance' => 0,
            'loan_status' => 'closed',
            'top_up_child_loan_id' => $newLoanId,
            'top_up_settled_at' => $settlementDate->toDateString(),
            'top_up_batch_reference' => $batchReference,
        ])->save();

        return $repayment;
    }

    private function syncRepaymentSchedule(Loan $loan): void
    {
        RepaymentSchedule::query()->where('loan_id', $loan->loan_id)->delete();

        $duration = max(1, (int) ($loan->loan_duration ?? $loan->term_months ?? 1));
        $monthlyPayment = round((float) ($loan->total_monthly_repayment ?? 0), 2);
        $monthlyInsurance = round((float) ($loan->monthly_insurance ?? 0), 2);
        $monthlyRate = ((float) ($loan->interest_rate ?? 0)) / 100 / 12;
        $opening = round((float) ($loan->principal_amount ?? 0), 2);
        $firstDueDate = $this->firstRepaymentDate($loan, Carbon::parse($loan->loan_release_date));

        for ($n = 1; $n <= $duration; $n++) {
            $interest = round($opening * $monthlyRate, 2);
            $principal = round(max(0, $monthlyPayment - $monthlyInsurance - $interest), 2);
            $closing = round(max(0, $opening - $principal), 2);

            RepaymentSchedule::create([
                'loan_id' => $loan->loan_id,
                'repayment_number' => $n,
                'due_date' => $firstDueDate->copy()->addMonths($n - 1)->toDateString(),
                'monthly_payment' => $monthlyPayment,
                'principal_payment' => $principal,
                'interest_payment' => $interest,
                'insurance_payment' => $monthlyInsurance,
                'opening_balance' => $opening,
                'closing_balance' => $closing,
                'payment_status' => 'Unpaid',
            ]);

            $opening = $closing;
        }
    }

    private function calculateFinancials(Loan $templateLoan, float $principal, int $loanTypeId): array
    {
        $loanType = LoanType::query()
            ->whereKey($loanTypeId)
            ->where('active', true)
            ->first();

        if (! $loanType) {
            throw new \RuntimeException('Selected loan type is invalid or inactive.');
        }

        $rate = (float) ($loanType->interest_rate ?? $templateLoan->interest_rate ?? 0);
        $duration = max(1, (int) ($templateLoan->loan_duration ?? $templateLoan->term_months ?? 1));
        $monthlyRate = $rate / 100 / 12;
        $payment = $monthlyRate > 0
            ? ($monthlyRate * $principal) / (1 - pow(1 + $monthlyRate, -$duration))
            : ($principal / $duration);
        $payment = round($payment, 2);

        $repaymentAmount = round($payment * $duration, 2);
        $interestAmount = round($repaymentAmount - $principal, 2);
        $monthlyInsurance = round(($principal * 0.045) / $duration, 2);
        $totalMonthlyRepayment = round($payment + $monthlyInsurance, 2);
        $loanName = strtolower((string) ($loanType->loan_name ?? $templateLoan->loan_category ?? ''));
        [
            'admin_fee' => $adminFee,
            'insurance_fee' => $insuranceFee,
            'arrangement_fee' => $arrangementFee,
        ] = LoanResource::calculateFeeComponents(
            $loanName,
            $principal,
            $repaymentAmount,
            $duration,
            $totalMonthlyRepayment,
        );
        $crbFee = 60.0;
        return [[
            'interest_rate' => round($rate, 2),
            'payment' => $payment,
            'repayment_amount' => $repaymentAmount,
            'interest_amount' => $interestAmount,
            'admin_fee' => $adminFee,
            'insurance_fee' => $insuranceFee,
            'arrangement_fee' => $arrangementFee,
            'crb_fee' => $crbFee,
            'monthly_insurance' => $monthlyInsurance,
            'total_monthly_repayment' => $totalMonthlyRepayment,
        ], $loanType];
    }

    private function fetchEligibleLoans(string|int $borrowerId, array $selectedLoanIds)
    {
        return Loan::query()
            ->with(['borrower', 'loan_type', 'repaymentSchedules'])
            ->where('borrower_id', $borrowerId)
            ->whereIn('loan_id', $selectedLoanIds)
            ->whereIn('loan_status', ['approved', 'partially_paid'])
            ->orderByDesc('loan_release_date');
    }

    private function guardEligibleLoans(Collection $loans, array $selectedLoanIds): void
    {
        if ($loans->isEmpty()) {
            throw new \RuntimeException('No eligible active loans were selected for top-up.');
        }

        if ($loans->count() !== count($selectedLoanIds)) {
            $loadedLoanIds = $loans->pluck('loan_id')->all();
            $missing = array_values(array_diff($selectedLoanIds, $loadedLoanIds));

            throw new \RuntimeException(
                'Some selected loans are not eligible for top-up: ' . implode(', ', $missing)
            );
        }

        $zeroBalanceLoans = $loans
            ->filter(fn (Loan $loan) => round((float) ($loan->balance ?? 0), 2) <= 0)
            ->pluck('loan_id')
            ->values()
            ->all();

        if ($zeroBalanceLoans !== []) {
            throw new \RuntimeException(
                'Top-up cannot settle loans with zero balance: ' . implode(', ', $zeroBalanceLoans)
            );
        }

        if ($loans->pluck('loan_type_id')->filter()->unique()->count() > 1) {
            throw new \RuntimeException('Selected loans must share the same loan type for a top-up batch.');
        }

        if ($loans->pluck('loan_duration')->filter()->unique()->count() > 1) {
            throw new \RuntimeException('Selected loans must share the same loan duration for a top-up batch.');
        }
    }

    private function buildPreview(Collection $loans, float $topUpAmount, Carbon $releaseDate, int $loanTypeId): array
    {
        $templateLoan = $loans->first();
        $totalOutstanding = round((float) $loans->sum(fn (Loan $loan) => (float) ($loan->balance ?? 0)), 2);
        $topUpAmount = round(max(0, $topUpAmount), 2);
        $newPrincipal = round($totalOutstanding + $topUpAmount, 2);

        if ($newPrincipal <= 0) {
            throw new \RuntimeException('Top-up principal must be greater than zero.');
        }

        [$financials, $loanType] = $this->calculateFinancials($templateLoan, $newPrincipal, $loanTypeId);
        $feesTotal = round(
            (float) $financials['admin_fee']
            + (float) $financials['arrangement_fee']
            + (float) $financials['crb_fee'],
            2
        );
        $netDisbursement = round(max(0, $topUpAmount - $feesTotal), 2);
        $withholdingAmount = $this->qualifiesForUpfrontWithholding($templateLoan, $totalOutstanding)
            ? round((float) $financials['total_monthly_repayment'], 2)
            : 0.0;
        $additionalAmountShortfall = round(max(0, $feesTotal - $topUpAmount), 2);

        $financials['disbursement_amount'] = $netDisbursement;

        return [
            'template_loan' => $templateLoan,
            'loan_type' => $loanType,
            'release_date' => $releaseDate->toDateString(),
            'topup_amount' => $topUpAmount,
            'total_outstanding' => $totalOutstanding,
            'new_principal' => $newPrincipal,
            'fees_total' => $feesTotal,
            'net_disbursement' => $netDisbursement,
            'additional_amount_shortfall' => $additionalAmountShortfall,
            'can_create' => $topUpAmount > 0 && $topUpAmount >= $feesTotal,
            'withholding_amount' => $withholdingAmount,
            'financials' => $financials,
        ];
    }

    private function qualifiesForUpfrontWithholding(Loan $loan, float $refinancedAmount): bool
    {
        $loanName = strtolower((string) ($loan->loan_type?->loan_name ?? $loan->loan_category ?? ''));

        return str_contains($loanName, 'grz') && $refinancedAmount > 0;
    }

    private function firstRepaymentDate(Loan $loan, Carbon $releaseDate): Carbon
    {
        $loan->loan_release_date = $releaseDate->toDateString();

        return app(LoanInterestAccrualService::class)->firstPaymentDate($loan) ?? $releaseDate->copy()->endOfMonth();
    }

    private function resolveDueDate(Carbon $releaseDate, int $duration): Carbon
    {
        return $releaseDate->copy()->addMonths(max(1, $duration));
    }

    private function generateLoanId(): string
    {
        $lastLoanId = Loan::query()
            ->where('loan_id', 'like', 'L%')
            ->orderByRaw('LENGTH(loan_id) DESC')
            ->orderByDesc('loan_id')
            ->value('loan_id');

        $currentNumber = 0;
        if (is_string($lastLoanId) && preg_match('/^(?:L)(\d+)$/', $lastLoanId, $matches)) {
            $currentNumber = (int) $matches[1];
            $padding = max(3, strlen($matches[1]));
        } else {
            $padding = 3;
        }

        return 'L' . str_pad((string) ($currentNumber + 1), $padding, '0', STR_PAD_LEFT);
    }

    private function normalizeLoanIds(array $selectedLoanIds): array
    {
        return collect($selectedLoanIds)
            ->map(fn ($loanId) => trim((string) $loanId))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
