<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Services\AmortizationService;
use App\Models\Loan;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use App\Services\LoanInterestAccrualService;

class AmortizationPage extends Page
{
    protected static string $view = 'filament.resources.wallet-resource.pages.amortization';
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Tools';
    protected static ?string $title = 'Loan Amortization';

    // Bound by the form
    public float $principal = 0;
    public float $rate      = 0;
    public int   $term      = 0;
    public string $startDate = '';

    // Computed schedule
    public array $schedule = [];

    public float $monthlyInsurance = 0;
    public float $totalMonthlyRepayment = 0;

    public ?int $loan_id = null;
    public array $loanOptions = [];

    public int $schedulePage = 1;
    public int $perPage = 12;

    public function mount(): void
    {
        $this->loanOptions = Loan::query()
            ->with('borrower')
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(fn (Loan $loan) => [
                $loan->id => sprintf(
                    '%s - %s',
                    $loan->loan_number ?: $loan->loan_id,
                    $this->borrowerDisplayName($loan)
                ),
            ])
            ->all();
    }

    public function calculateSchedule(): void
    {
        if (! $this->hydrateLoanFields()) {
            return;
        }

        $this->schedule = app(AmortizationService::class)
            ->generateSchedule(
                $this->principal,
                $this->rate,
                $this->term,
                $this->startDate,
                $this->monthlyInsurance,
                $this->totalMonthlyRepayment,
            );

        if ($loan = $this->selectedLoan) {
            $this->schedule = $this->mergeActualRepaymentData($this->schedule, $loan);
        }

        $this->schedulePage = 1;
    }

    public function exportPdf()
    {
        if (! $this->hydrateLoanFields()) {
            return null;
        }

        if ($this->schedule === []) {
            $this->calculateSchedule();
        }

        $loan = $this->selectedLoan;

        if (! $loan || $this->schedule === []) {
            Notification::make()
                ->title('No amortization schedule available')
                ->body('Select a valid loan and generate the schedule before exporting.')
                ->warning()
                ->send();

            return null;
        }

        $pdf = Pdf::loadView('exports.amortization', [
            'schedule' => $this->schedule,
            'loan' => $loan,
            'summary' => $this->scheduleSummary,
            'firstPaymentDate' => $this->startDate,
        ]);

        return response()->streamDownload(
            fn () => print($pdf->output()),
            "amortization_schedule_{$loan->loan_id}.pdf"
        );
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('exportPdf')
                ->label('Export PDF')
                ->action('exportPdf')
                ->color('primary')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (): bool => $this->schedule !== []),
        ];
    }

    public function updatedLoanId($value): void
    {
        $this->schedule = [];
        $this->schedulePage = 1;
        $this->applyLoanFields(Loan::with('borrower', 'loan_type')->find($value));

        if ($value && $this->principal > 0 && $this->term > 0 && $this->startDate !== '') {
            $this->calculateSchedule();
        }
    }

    public function getSelectedLoanProperty(): ?Loan
    {
        return $this->loan_id
            ? Loan::with([
                'borrower',
                'loan_type',
                'repayments' => fn ($query) => $query->orderBy('repayment_number')->orderBy('receipt_date')->orderBy('id'),
            ])->find($this->loan_id)
            : null;
    }

    public function getPaginatedScheduleProperty(): array
    {
        $offset = ($this->schedulePage - 1) * $this->perPage;

        return array_slice($this->schedule, $offset, $this->perPage);
    }

    public function getSchedulePageCountProperty(): int
    {
        return max(1, (int) ceil(count($this->schedule) / $this->perPage));
    }

    public function getScheduleSummaryProperty(): array
    {
        $rows = collect($this->schedule);

        return [
            'installments' => $rows->count(),
            'total_payment' => (float) $rows->sum('payment'),
            'total_interest' => (float) $rows->sum('interest'),
            'total_principal' => (float) $rows->sum('principal'),
            'total_insurance' => (float) $rows->sum('insurance'),
            'ending_balance' => (float) ($rows->last()['balance'] ?? 0),
            'expected_total_recoverable' => (float) $rows->sum('payment'),
            'actual_total_paid' => (float) $rows->sum('actual_payment'),
            'actual_principal_paid' => (float) $rows->sum('actual_principal_paid'),
            'actual_interest_paid' => (float) $rows->sum('actual_interest_paid'),
            'actual_insurance_paid' => (float) $rows->sum('actual_insurance_paid'),
            'actual_ending_balance' => (float) ($rows->last()['actual_balance'] ?? 0),
            'actual_interest_arrears' => (float) ($rows->last()['interest_arrears'] ?? 0),
            'actual_insurance_arrears' => (float) ($rows->last()['insurance_arrears'] ?? 0),
            'actual_total_recoverable' => (float) ($rows->last()['actual_total_recoverable'] ?? 0),
        ];
    }

    public function gotoSchedulePage(int $page): void
    {
        $this->schedulePage = max(1, min($page, $this->schedulePageCount));
    }

    protected function hydrateLoanFields(): bool
    {
        $loan = $this->selectedLoan;

        if (! $loan) {
            Notification::make()
                ->title('Loan required')
                ->body('Select a loan before generating an amortization schedule.')
                ->warning()
                ->send();

            return false;
        }

        $this->applyLoanFields($loan);

        return $this->principal > 0 && $this->term > 0 && $this->startDate !== '';
    }

    protected function applyLoanFields(?Loan $loan): void
    {
        if (! $loan) {
            $this->principal = 0;
            $this->rate = 0;
            $this->term = 0;
            $this->monthlyInsurance = 0;
            $this->totalMonthlyRepayment = 0;
            $this->startDate = '';

            return;
        }

        $this->principal = (float) ($loan->principal_amount ?? 0);
        $this->rate = (float) ($loan->interest_rate ?? 0);
        $this->term = (int) ($loan->loan_duration ?? 0);
        $this->monthlyInsurance = round((float) ($loan->monthly_insurance ?? 0), 2);
        $this->totalMonthlyRepayment = round((float) ($loan->total_monthly_repayment ?? 0), 2);
        $this->startDate = $this->resolveFirstPaymentDate($loan);
    }

    protected function resolveFirstPaymentDate(Loan $loan): string
    {
        return app(LoanInterestAccrualService::class)->firstPaymentDate($loan)?->toDateString() ?? '';
    }

    protected function borrowerDisplayName(Loan $loan): string
    {
        return trim(collect([
            $loan->borrower?->first_name ?: $loan->borrower?->other_names ?: $loan->other_names,
            $loan->borrower?->last_name ?: $loan->last_name,
        ])->filter()->implode(' ')) ?: 'Unknown borrower';
    }

    protected function mergeActualRepaymentData(array $schedule, Loan $loan): array
    {
        $repaymentsByPeriod = $this->repaymentsByPeriod($loan);
        $actualBalance = (float) ($loan->principal_amount ?? $this->principal);
        $interestArrears = 0.0;
        $insuranceArrears = 0.0;

        return collect($schedule)->map(function (array $row) use ($repaymentsByPeriod, &$actualBalance, &$interestArrears, &$insuranceArrears) {
            $actual = $repaymentsByPeriod->get((int) $row['period'], [
                'actual_payment' => 0.0,
                'actual_principal_paid' => 0.0,
                'actual_interest_paid' => 0.0,
                'actual_insurance_paid' => 0.0,
            ]);

            $actualBalance = round(max(0, $actualBalance - (float) $actual['actual_principal_paid']), 2);
            $interestArrears = round(max(0, $interestArrears + (float) ($row['interest'] ?? 0) - (float) $actual['actual_interest_paid']), 2);
            $insuranceArrears = round(max(0, $insuranceArrears + (float) ($row['insurance'] ?? 0) - (float) $actual['actual_insurance_paid']), 2);
            $actualTotalRecoverable = round($actualBalance + $interestArrears + $insuranceArrears, 2);

            return array_merge($row, $actual, [
                'expected_total_recoverable' => round((float) ($row['payment'] ?? 0) + (float) ($row['balance'] ?? 0), 2),
                'actual_balance' => $actualBalance,
                'interest_arrears' => $interestArrears,
                'insurance_arrears' => $insuranceArrears,
                'actual_total_recoverable' => $actualTotalRecoverable,
                'balance_variance' => round($actualBalance - (float) ($row['balance'] ?? 0), 2),
            ]);
        })->all();
    }

    protected function repaymentsByPeriod(Loan $loan): Collection
    {
        $fallbackPeriod = 1;

        return $loan->repayments
            ->map(function ($repayment) use (&$fallbackPeriod) {
                $period = (int) ($repayment->repayment_number ?: $fallbackPeriod++);

                return [
                    'period' => $period,
                    'actual_payment' => (float) ($repayment->receipt_amount ?? 0),
                    'actual_principal_paid' => (float) ($repayment->paid_principal ?? 0),
                    'actual_interest_paid' => (float) ($repayment->paid_interest ?? 0),
                    'actual_insurance_paid' => (float) ($repayment->insurance_paid ?? 0),
                ];
            })
            ->groupBy('period')
            ->map(function (Collection $rows) {
                return [
                    'actual_payment' => round((float) $rows->sum('actual_payment'), 2),
                    'actual_principal_paid' => round((float) $rows->sum('actual_principal_paid'), 2),
                    'actual_interest_paid' => round((float) $rows->sum('actual_interest_paid'), 2),
                    'actual_insurance_paid' => round((float) $rows->sum('actual_insurance_paid'), 2),
                ];
            });
    }
}
