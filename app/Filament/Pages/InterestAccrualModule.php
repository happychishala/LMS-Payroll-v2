<?php

namespace App\Filament\Pages;

use App\Exports\InterestAccrualExport;
use App\Models\Loan;
use App\Models\LoanType;
use App\Services\LoanInterestAccrualService;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class InterestAccrualModule extends Page
{
    protected static ?string $navigationGroup = 'Loans';
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?int $navigationSort = 55;
    protected static ?string $title = 'Interest Accrued';
    protected static string $view = 'filament.pages.interest-accrual-module';

    public ?string $asOfDate = '';
    public ?string $employer = '';
    public ?string $loanName = '';
    public ?string $status = '';
    public ?string $loanId = '';
    public ?string $filterMonth = '';
    public ?string $filterYear = '';

    public function mount(): void
    {
        $this->asOfDate = now()->toDateString();
    }

    public function getAvailableYearsProperty()
    {
        return \App\Models\RepaymentSchedule::query()
            ->selectRaw('YEAR(due_date) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year');
    }

    public function getEmployersProperty()
    {
        return Loan::query()
            ->whereNotNull('employer')
            ->distinct()
            ->orderBy('employer')
            ->pluck('employer');
    }

    public function getLoanNamesProperty()
    {
        return LoanType::query()->orderBy('loan_name')->pluck('loan_name');
    }

    public function getStatusesProperty()
    {
        return Loan::query()
            ->whereNotNull('loan_status')
            ->distinct()
            ->orderBy('loan_status')
            ->pluck('loan_status');
    }

    public function getRowsProperty(): Collection
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
                    'Borrower Name' => $this->borrowerName($loan),
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

    public function getPreviewRowsProperty(): Collection
    {
        return $this->rows->take(50)->values();
    }

    public function getSummaryProperty(): array
    {
        return [
            'loans' => $this->rows->count(),
            'accrued_interest' => (float) $this->rows->sum('Accrued Interest'),
            'interest_paid' => (float) $this->rows->sum('Interest Paid'),
            'unpaid_accrued_interest' => (float) $this->rows->sum('Unpaid Accrued Interest'),
            'contracted_interest' => (float) $this->rows->sum('Total Contracted Interest'),
        ];
    }

    public function export()
    {
        return Excel::download(
            new InterestAccrualExport(
                $this->asOfDate ?: null,
                $this->employer ?: null,
                $this->loanName ?: null,
                $this->status ?: null,
                $this->loanId ?: null,
                $this->filterMonth ?: null,
                $this->filterYear ?: null,
            ),
            'interest_accrual_' . now()->format('Ymd_His') . '.csv'
        );
    }

    protected function borrowerName(Loan $loan): string
    {
        return trim(collect([
            $loan->borrower?->first_name ?: $loan->borrower?->other_names ?: $loan->other_names,
            $loan->borrower?->last_name ?: $loan->last_name,
        ])->filter()->implode(' ')) ?: 'N/A';
    }
}
