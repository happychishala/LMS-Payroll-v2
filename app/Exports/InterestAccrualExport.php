<?php

namespace App\Exports;

use App\Models\Loan;
use App\Services\LoanInterestAccrualService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class InterestAccrualExport implements FromCollection, WithHeadings
{
    public function __construct(
        protected ?string $asOfDate,
        protected ?string $employer,
        protected ?string $loanName,
        protected ?string $status,
        protected ?string $loanId = null,
        protected ?string $filterMonth = null,
        protected ?string $filterYear = null,
    ) {}

    public function collection(): Collection
    {
        $asOf = $this->asOfDate ? Carbon::parse($this->asOfDate)->endOfDay() : now()->endOfDay();
        $interestAccruals = app(LoanInterestAccrualService::class);

        return Loan::query()
            ->with(['borrower', 'loan_type', 'repayments', 'repaymentSchedules'])
            ->when($this->employer, fn ($query, $employer) => $query->where('employer', $employer))
            ->when($this->loanName, fn ($query, $loanName) => $query->whereHas('loan_type', fn ($loanTypeQuery) => $loanTypeQuery->where('loan_name', $loanName)))
            ->when($this->status, fn ($query, $status) => $query->where('loan_status', $status))
            ->when($this->loanId, fn ($query, $loanId) => $query->where('loan_id', 'like', '%' . $loanId . '%'))
            ->when($this->filterYear && $this->filterMonth, fn ($query) => $query->whereHas('repaymentSchedules', fn ($q) => $q->whereYear('due_date', $this->filterYear)->whereMonth('due_date', $this->filterMonth)))
            ->when($this->filterYear && ! $this->filterMonth, fn ($query) => $query->whereHas('repaymentSchedules', fn ($q) => $q->whereYear('due_date', $this->filterYear)))
            ->when($this->filterMonth && ! $this->filterYear, fn ($query) => $query->whereHas('repaymentSchedules', fn ($q) => $q->whereMonth('due_date', $this->filterMonth)))
            ->whereNotNull('loan_release_date')
            ->orderByDesc('loan_release_date')
            ->get()
            ->map(function (Loan $loan) use ($asOf, $interestAccruals) {
                $accruedInterest = $interestAccruals->accruedInterest($loan, $asOf);
                $interestPaid = $interestAccruals->paidInterest($loan, $asOf);
                $firstPaymentDate = $interestAccruals->firstPaymentDate($loan);

                return [
                    'Loan ID' => $loan->loan_id ?? 'N/A',
                    'Borrower ID' => $loan->borrower?->customer_id ?: $loan->borrower_id ?: 'N/A',
                    'Borrower Name' => trim(collect([
                        $loan->borrower?->first_name ?: $loan->borrower?->other_names ?: $loan->other_names,
                        $loan->borrower?->last_name ?: $loan->last_name,
                    ])->filter()->implode(' ')) ?: 'N/A',
                    'Employer' => $loan->borrower?->employer ?? $loan->employer ?? 'N/A',
                    'Loan Name' => $loan->loan_type?->loan_name ?? 'Unknown',
                    'Issue Date' => optional($loan->loan_release_date)?->format('Y-m-d'),
                    'First Payment Date' => $firstPaymentDate?->toDateString(),
                    'As Of Date' => $asOf->toDateString(),
                    'Due Installments' => $interestAccruals->dueInstallmentsCount($loan, $asOf),
                    'Monthly Expected Interest' => $interestAccruals->monthlyExpectedInterest($loan, $asOf),
                    'Accrued Interest' => $accruedInterest,
                    'Interest Paid' => $interestPaid,
                    'Unpaid Accrued Interest' => $interestAccruals->unpaidAccruedInterest($loan, $asOf),
                    'Total Contracted Interest' => (float) ($loan->interest_amount ?? 0),
                    'Balance' => (float) ($loan->balance ?? 0),
                    'Status' => $loan->loan_status ?? 'N/A',
                ];
            })
            ->values();
    }

    public function headings(): array
    {
        return [
            'Loan ID',
            'Borrower ID',
            'Borrower Name',
            'Employer',
            'Loan Name',
            'Issue Date',
            'First Payment Date',
            'As Of Date',
            'Due Installments',
            'Monthly Expected Interest',
            'Accrued Interest',
            'Interest Paid',
            'Unpaid Accrued Interest',
            'Total Contracted Interest',
            'Balance',
            'Status',
        ];
    }
}
