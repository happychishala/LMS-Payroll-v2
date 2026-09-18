<?php

namespace App\Filament\Pages;

use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanType;
use App\Services\TopUpLoanService;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class TopUpLoans extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationGroup = 'Loans';
    protected static ?string $navigationIcon = 'fas-money-bill-trend-up';
    protected static ?string $navigationLabel = 'Top-Up Loans';
    protected static string $view = 'filament.pages.top-up-loans';

    public $borrower_id;
    public $selected_loans = [];
    public $loan_type_id;
    public $topup_amount = 0;
    public $loan_release_date;

    public function mount(): void
    {
        $this->form->fill([
            'borrower_id' => null,
            'selected_loans' => [],
            'loan_type_id' => null,
            'topup_amount' => 0,
            'loan_release_date' => now()->toDateString(),
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Select::make('borrower_id')
                ->label('Borrower')
                ->searchable()
                ->preload()
                ->getSearchResultsUsing(function (string $search) {
                    return Borrower::query()
                        ->where('mine_number', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(function ($borrower) {
                            return [
                                $borrower->id => "{$borrower->full_name} ({$borrower->mine_number})",
                            ];
                        })
                        ->toArray();
                })
                ->getOptionLabelUsing(function ($value) {
                    $borrower = Borrower::find($value);

                    return $borrower
                        ? "{$borrower->full_name} ({$borrower->mine_number})"
                        : $value;
                })
                ->live()
                ->afterStateUpdated(function (Set $set): void {
                    $set('selected_loans', []);
                })
                ->required(),
            Select::make('selected_loans')
                ->label('Loans to Refinance')
                ->multiple()
                ->searchable()
                ->preload()
                ->live()
                ->options(function (Get $get) {
                    if (! $get('borrower_id')) {
                        return [];
                    }

                    return Loan::query()
                        ->where('borrower_id', $get('borrower_id'))
                        ->whereIn('loan_status', ['approved', 'partially_paid'])
                        ->where('loan_category', '!=', 'Educational Loan')
                        ->orderBy('loan_release_date')
                        ->get()
                        ->mapWithKeys(function (Loan $loan) {
                            return [
                                $loan->loan_id => sprintf(
                                    '%s - %s (Balance: %s)',
                                    $loan->loan_id,
                                    $loan->loan_category,
                                    number_format((float) $loan->balance, 2)
                                ),
                            ];
                        })
                        ->toArray();
                })
                ->helperText('Select one or more active loans for the chosen borrower.')
                ->placeholder(function (Get $get): string {
                    return $get('borrower_id')
                        ? 'Select loan(s)'
                        : 'Select a borrower first';
                })
                ->noSearchResultsMessage('No eligible loans found for this borrower.')
                ->required(),
            Select::make('loan_type_id')
                ->label('New Top-Up Loan Type')
                ->options(fn (): array => LoanType::query()
                    ->where('active', true)
                    ->orderBy('loan_name')
                    ->pluck('loan_name', 'id')
                    ->all())
                ->getSearchResultsUsing(fn (string $search): array => LoanType::query()
                    ->where('active', true)
                    ->where('loan_name', 'like', "%{$search}%")
                    ->orderBy('loan_name')
                    ->limit(50)
                    ->pluck('loan_name', 'id')
                    ->all())
                ->getOptionLabelUsing(fn ($value): ?string => LoanType::query()
                    ->whereKey($value)
                    ->value('loan_name'))
                ->searchable()
                ->preload()
                ->live()
                ->helperText('This loan type will be applied to the new top-up loan and used for the preview calculations.')
                ->required(),
            TextInput::make('topup_amount')
                ->label('Additional Top-Up Amount')
                ->numeric()
                ->default(0)
                ->minValue(0.01)
                ->live(debounce: 500)
                ->helperText('Enter only the new cash amount to add. The system will calculate the resulting loan below.')
                ->required(),
            DatePicker::make('loan_release_date')
                ->label('New Loan Release Date')
                ->default(now())
                ->live()
                ->required(),
        ];
    }

    public function submit(): void
    {
        $this->validate([
            'borrower_id' => 'required|exists:borrowers,id',
            'selected_loans' => 'required|array|min:1',
            'loan_type_id' => 'required|exists:loan_types,id',
            'topup_amount' => 'required|numeric|min:0.01',
            'loan_release_date' => 'required|date',
        ]);

        try {
            $preview = app(TopUpLoanService::class)->previewTopUp(
                $this->borrower_id,
                $this->selected_loans,
                (float) $this->topup_amount,
                $this->loan_release_date,
                $this->loan_type_id,
            );

            if (($preview['can_create'] ?? false) !== true) {
                throw new \RuntimeException(
                    ($preview['additional_amount_shortfall'] ?? 0) > 0
                        ? sprintf(
                            'Additional Top-Up Amount must cover the estimated fees and accrued loan deductions. Increase it by at least %s.',
                            number_format((float) $preview['additional_amount_shortfall'], 2)
                        )
                        : 'Additional Top-Up Amount must be greater than zero.'
                );
            }

            $result = app(TopUpLoanService::class)->createTopUp(
                $this->borrower_id,
                $this->selected_loans,
                (float) $this->topup_amount,
                $this->loan_release_date,
                $this->loan_type_id,
            );

            Notification::make()
                ->success()
                ->title('Top-Up Loan Created')
                ->body(sprintf(
                    'New loan %s created. %d loan(s) settled into batch %s.',
                    $result['new_loan']->loan_id,
                    $result['settled_loans']->count(),
                    $result['batch']->batch_reference,
                ))
                ->send();

            $this->form->fill([
                'loan_type_id' => null,
                'topup_amount' => 0,
                'loan_release_date' => now()->toDateString(),
                'selected_loans' => [],
            ]);
            $this->selected_loans = [];
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Top-Up failed')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function getTopUpPreviewProperty(): ?array
    {
        if (! $this->borrower_id || empty($this->selected_loans) || ! $this->loan_type_id || (float) $this->topup_amount <= 0) {
            return null;
        }

        try {
            return app(TopUpLoanService::class)->previewTopUp(
                $this->borrower_id,
                $this->selected_loans,
                (float) $this->topup_amount,
                $this->loan_release_date,
                $this->loan_type_id,
            );
        } catch (\Throwable $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getSelectedLoanBalancesProperty(): array
    {
        if (empty($this->selected_loans)) {
            return [];
        }

        return Loan::query()
            ->whereIn('loan_id', $this->selected_loans)
            ->orderBy('loan_id')
            ->get(['loan_id', 'loan_number', 'balance'])
            ->map(function (Loan $loan) {
                return sprintf(
                    '%s (%s): %s',
                    $loan->loan_id,
                    $loan->loan_number,
                    number_format((float) $loan->balance, 2)
                );
            })
            ->toArray();
    }
}
