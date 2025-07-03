<?php

namespace App\Filament\Resources;

use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Filament\Forms\Components\Toggle;
use App\helpers\CreateLinks;
use Filament\Forms\Set;
use Filament\Forms\Get;
use App\Filament\Resources\LoanResource\Pages;
use Bavix\Wallet\Models\Wallet;
use App\Models\Loan;
use App\Models\LoanType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class LoanResource extends Resource
{
    protected static ?string $model = Loan::class;
    protected static ?string $navigationGroup = 'Loans';
    protected static ?string $navigationIcon = 'fas-dollar-sign';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        $accountOptions = Wallet::all()
            ->mapWithKeys(fn($wallet) => [
                $wallet->id => $wallet->name . ' - Balance: ' . number_format($wallet->balance),
            ])
            ->toArray();

        return $form->schema([
            // Loan Type
            Components\Select::make('loan_type_id')
                ->label('Loan Type')
                ->relationship('loan_type', 'loan_name')
                ->searchable()
                ->preload()
                ->reactive()
                ->afterStateUpdated(fn($state, Set $set, Get $get) => $set('principal_amount', $get('principal_amount') ?? 0))
                ->required(),

            // Borrower
            Components\Select::make('borrower_id')
                ->label('Borrower')
                ->relationship('borrower', 'full_name')
                ->searchable()
                ->preload()
                ->required(),

            // Loan Status
            Components\Select::make('loan_status')
                ->label('Loan Status')
                ->options([
                    'requested'  => 'Requested',
                    'processing' => 'Processing',
                    'approved'   => 'Approved',
                    'denied'     => 'Denied',
                    'defaulted'  => 'Defaulted',
                ])
                ->required(),

            // Principal Amount + Calculations
            Components\TextInput::make('principal_amount')
                ->label('Principal Amount')
                ->numeric()
                ->reactive()
                ->afterStateUpdated(function ($state, Set $set, Get $get) {
                    $loanTypeId = $get('loan_type_id');
                    $duration = (int) ($get('loan_duration') ?? 0);
                    if (! $loanTypeId || $duration === 0) {
                        return;
                    }

                    // Fetch loan type
                    $loanType = LoanType::find($loanTypeId);
                    if (! $loanType) {
                        return;
                    }

                    // Base values
                    $principal = floatval($state);
                    $rate = $loanType->interest_rate;
                    $monthlyRate = $rate / 100 / 12;

                    // PMT formula
                    $pmt = $monthlyRate > 0
                        ? ($monthlyRate * $principal) / (1 - pow(1 + $monthlyRate, -$duration))
                        : ($principal / $duration);
                    $pmt = round($pmt, 2);

                    // Total & interest
                    $totalRepayment = round($pmt * $duration, 2);
                    $interestAmount = round($totalRepayment - $principal, 2);

                    // Fee schedule
                    $loanName = strtolower($loanType->loan_name);
                    $adminFee = str_contains($loanName, 'payroll-cnmc')
                        ? round($principal * 0.05, 2)
                        : round($principal * 0.015, 2);
                    $insuranceFee = round($principal * 0.045, 2);
                    $arrangementFee = str_contains($loanName, 'payroll-grz')
                        ? round($principal * 0.02, 2)
                        : round($totalRepayment * 0.025, 2);
                    $crbFee = 60;

                    // Monthly insurance + total monthly
                    $monthlyInsurance = round($insuranceFee / $duration, 2);
                    $totalMonthly = round($pmt + $monthlyInsurance, 2);

                    // Disbursement = principal - all fees
                    $disbursementAmount = round(
                        $principal
                      - ($adminFee + $monthlyInsurance + $arrangementFee + $crbFee),
                        2
                    );

                    // Set all states
                    $set('interest_rate', round($rate, 2));
                    $set('payment', $pmt);
                    $set('repayment_amount', $totalRepayment);
                    $set('interest_amount', $interestAmount);
                    $set('admin_fee', $adminFee);
                    $set('insurance_fee', $insuranceFee);
                    $set('arrangement_fee', $arrangementFee);
                    $set('crb_fee', $crbFee);
                    $set('monthly_insurance', $monthlyInsurance);
                    $set('total_monthly_repayment', $totalMonthly);
                    $set('disbursement_amount', $disbursementAmount);
                })
                ->required(),

            // Loan Duration
            Components\TextInput::make('loan_duration')
                ->label('Loan Duration (months)')
                ->numeric()
                ->reactive()
                ->afterStateUpdated(fn($state, Set $set) => $set('duration_period', $state))
                ->required(),

            // Duration Period (read-only)
            Components\TextInput::make('duration_period')
                ->label('Duration Period')
                ->numeric()
                ->readOnly(),

            // Release Date
            Components\DatePicker::make('loan_release_date')
                ->label('Loan Release Date')
                ->native(false)
                ->maxDate(now())
                ->required(),

            // Computed Outputs
            Components\TextInput::make('repayment_amount')
                ->label('Total Repayment')
                ->numeric()
                ->readOnly(),

            Components\TextInput::make('interest_amount')
                ->label('Interest Amount')
                ->numeric()
                ->readOnly(),

            Components\TextInput::make('interest_rate')
                ->label('Interest Rate')
                ->suffix('%')
                ->numeric()
                ->readOnly(),

            Components\TextInput::make('admin_fee')
                ->label('Admin Fee')
                ->numeric()
                ->readOnly(),

            Components\TextInput::make('insurance_fee')
                ->label('Insurance Fee')
                ->numeric()
                ->readOnly(),

            Components\TextInput::make('arrangement_fee')
                ->label('Arrangement Fee')
                ->numeric()
                ->readOnly(),

            Components\TextInput::make('crb_fee')
                ->label('CRB Fee')
                ->numeric()
                ->readOnly(),

            Components\TextInput::make('payment')
                ->label('Periodic Payment')
                ->numeric()
                ->readOnly(),

            Components\TextInput::make('monthly_insurance')
                ->label('Monthly Insurance')
                ->numeric()
                ->readOnly(),

            Components\TextInput::make('total_monthly_repayment')
                ->label('Total Monthly Repayment')
                ->numeric()
                ->readOnly(),

            Components\TextInput::make('disbursement_amount')
                ->label('Disbursement Amount')
                ->numeric()
                ->readOnly(),

            // Auto-generated Loan Number
            Components\TextInput::make('loan_number')
                ->label('Loan Number')
                ->default(fn() => 'LN-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(4)))
                ->readOnly()
                ->required(),

            // From Account
            Components\Select::make('from_this_account')
                ->label('From this Account')
                ->options($accountOptions)
                ->searchable()
                ->required(),

            // Transaction Reference
            Components\TextInput::make('transaction_reference')
                ->label('Transaction Reference')
                ->default(fn() => 'TRX-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(6)))
                ->readOnly()
                ->required(),

            // Compile Agreement Toggle
            Toggle::make('activate_loan_agreement_form')
                ->label('Compile Loan Agreement Form')
                ->helperText('Ensure template exists for this loan type.')
                ->onColor('success')
                ->offColor('danger'),

            // Hidden fields
            Components\TextInput::make('loan_agreement_file_path')->hidden(),
            Components\TextInput::make('balance')->hidden(),
        ]);
    }

    public static function table(Table $table): Table
    {
        $createLink = new CreateLinks();
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('borrower.full_name')->label('Borrower')->searchable(),
                Tables\Columns\TextColumn::make('loan_type.loan_name')->label('Loan Type')->searchable(),
                Tables\Columns\TextColumn::make('loan_status')
    ->badge()
    ->color(fn ($state) => match ($state) {
        'requested'  => 'gray',
        'processing' => 'info',
        'approved'   => 'success',
        'denied'     => 'danger',
        'defaulted'  => 'warning',
        default      => 'secondary',
    }),
                Tables\Columns\TextColumn::make('principal_amount')->label('Principal')->money('ZMW')->sortable(),
                Tables\Columns\TextColumn::make('loan_duration')->label('Duration (months)'),
                Tables\Columns\TextColumn::make('repayment_amount')->label('Total Repayment')->money('ZMW'),
                Tables\Columns\TextColumn::make('payment')->label('Periodic Payment')->money('ZMW'),
                Tables\Columns\TextColumn::make('monthly_insurance')->label('Monthly Insurance')->money('ZMW'),
                Tables\Columns\TextColumn::make('total_monthly_repayment')->label('Total Monthly Repayment')->money('ZMW'),
                Tables\Columns\TextColumn::make('disbursement_amount')->label('Disbursement')->money('ZMW'),
                Tables\Columns\TextColumn::make('loan_number')->label('Loan #')->badge(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                ExportBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListLoans::route('/'),
            'create' => Pages\CreateLoan::route('/create'),
            'view'   => Pages\ViewLoan::route('/{record}'),
            'edit'   => Pages\EditLoan::route('/{record}/edit'),
            
        ];
    }
}
