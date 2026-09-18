<?php

namespace App\Filament\Resources;

use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Set;
use Filament\Forms\Get;
use App\Filament\Resources\LoanResource\Pages;
use Bavix\Wallet\Models\Wallet;
use App\Models\Loan;
use App\Models\LoanType;
use App\Models\Borrower;
use App\Models\ThirdParty;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\DatePicker;            // used both in form & filter
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Resource;
use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\StatusReason;

class LoanResource extends Resource
{
    protected static ?string $model = Loan::class;
    protected static ?string $navigationGroup = 'Loans';
    protected static ?string $navigationIcon  = 'fas-dollar-sign';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        $accountOptions = Wallet::all()
            ->mapWithKeys(fn($wallet) => [
                $wallet->id => "{$wallet->name} – Balance: " . number_format($wallet->balance),
            ])->toArray();

        return $form
            ->schema([
                Select::make('loan_type_id')
                    ->label('Loan Type')
                    ->relationship(
                        'loan_type',
                        'loan_name',
                        modifyQueryUsing: fn (Builder $query) => $query
                            ->where('active', true)
                            ->orderBy('loan_name')
                    )
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->afterStateUpdated(fn(Set $set, Get $get) => static::applyCalculations($get, $set))
                    ->required(),

                Select::make('loan_category')
                    ->label('Loan Category')
                    ->options([
                        'Refinancing Loan' => 'Refinancing Loan',
                        'Educational Loan' => 'Educational Loan',
                        'Consumer Loan' => 'Consumer Loan',
                    ])
                    ->reactive()
                    ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                        if ($state !== 'Refinancing Loan') {
                            return;
                        }

                        if (static::thirdPartyTotal($get('third_parties') ?? []) > 0) {
                            return;
                        }

                        $set('loan_category', null);

                        Notification::make()
                            ->warning()
                            ->title('Third-party balance required')
                            ->body('Select Refinancing Loan only after adding at least one third-party balance.')
                            ->send();
                    })
                    ->required(),

                Select::make('borrower_id')
                    ->label('Borrower')
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search): array {
                        return Borrower::query()
                            ->when($search !== '', function ($query) use ($search) {
                                $query->where('customer_id', 'like', "%{$search}%")
                                    ->orWhere('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%")
                                    ->orWhere('full_name', 'like', "%{$search}%")
                                    ->orWhere('mobile', 'like', "%{$search}%");
                            })
                            ->orderBy('full_name')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (Borrower $borrower) => [
                                $borrower->id => static::borrowerOptionLabel($borrower),
                            ])
                            ->all();
                    })
                    ->getOptionLabelUsing(function ($value): ?string {
                        if (! $value) {
                            return null;
                        }

                        $borrower = Borrower::query()->find($value);

                        return $borrower ? static::borrowerOptionLabel($borrower) : null;
                    })
                    ->required(),

                Select::make('loan_status')
                    ->label('Loan Status')
                    ->options([
                        'requested'  => 'Requested',
                        'processing' => 'Processing',
                        'approved'   => 'Approved',
                        'denied'     => 'Denied',
                        'defaulted'  => 'Defaulted',
                        'Refund'     => 'Refund',
                        'settled'    => 'Closed',
                        'Paid Off' => 'Paid Off', // Add this line
                    ])
                    ->required(),

                Toggle::make('exceptional_approval')
                    ->label('Exceptional Approval')
                    ->helperText('Turn this on only when this loan has approval outside the normal approval flow.')
                    ->live()
                    ->default(false),

                FileUpload::make('exceptional_approval_email_screenshot_path')
                    ->label('Exceptional Approval Email Screenshot')
                    ->disk('public')
                    ->directory('exceptional-approval-emails')
                    ->visibility('public')
                    ->acceptedFileTypes([
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                    ])
                    ->maxSize(10240)
                    ->helperText('Required when exceptional approval is enabled. Upload the email screenshot confirming approval.')
                    ->openable()
                    ->downloadable()
                    ->required(fn (Get $get): bool => (bool) $get('exceptional_approval'))
                    ->visible(fn (Get $get): bool => (bool) $get('exceptional_approval')),

                SpatieMediaLibraryFileUpload::make('supporting_documents')
                    ->label('Supporting Documents')
                    ->collection('supporting_documents')
                    ->disk('public')
                    ->visibility('public')
                    ->multiple()
                    ->maxFiles(10)
                    ->maxSize(102400)
                    ->acceptedFileTypes([
                        'application/pdf',
                        'image/jpeg',
                        'image/png',
                        'application/zip',
                        'application/x-zip-compressed',
                        '.zip',
                    ])
                    ->helperText('Upload the signed loan application and any other supporting documents here. PDF, JPG, PNG, and ZIP files up to 100 MB each are allowed.')
                    ->openable()
                    ->downloadable()
                    ->reorderable()
                    ->visible(fn (?Loan $record): bool => filled($record)),

                SpatieMediaLibraryFileUpload::make('settlement_documents')
                    ->label('Settlement Documents')
                    ->collection('settlement_documents')
                    ->disk('public')
                    ->visibility('public')
                    ->multiple()
                    ->maxFiles(10)
                    ->maxSize(102400)
                    ->acceptedFileTypes([
                        'application/pdf',
                        'image/jpeg',
                        'image/png',
                        'application/zip',
                        'application/x-zip-compressed',
                        '.zip',
                    ])
                    ->helperText('Required when changing a loan status to Closed or Paid Off. PDF, JPG, PNG, and ZIP files up to 100 MB each are allowed.')
                    ->openable()
                    ->downloadable()
                    ->reorderable()
                    ->required(fn (Get $get, ?Loan $record): bool => static::requiresSettlementDocuments($get('loan_status'), $record))
                    ->visible(fn (?Loan $record): bool => filled($record)),

                TextInput::make('principal_amount')
                    ->label('Principal Amount')
                    ->numeric()
                    ->reactive()
                    ->afterStateUpdated(fn(Set $set, Get $get) => static::applyCalculations($get, $set))
                    ->required(),

                TextInput::make('loan_duration')
                    ->label('Loan Duration (months)')
                    ->numeric()
                    ->reactive()
                    ->afterStateUpdated(fn(Set $set, Get $get) => static::applyCalculations($get, $set))
                    ->required(),

                TextInput::make('duration_period')
                    ->label('Duration Period')
                    ->numeric()
                    ->readOnly(),

                DatePicker::make('loan_release_date')
                    ->label('Loan Release Date')
                    ->native(false)
                    ->maxDate(now())
                    ->reactive()
                    ->afterStateUpdated(fn(Set $set, Get $get) => static::applyCalculations($get, $set))
                    ->afterStateHydrated(fn(Set $set, Get $get) => static::applyCalculations($get, $set))
                    ->required(),

                DatePicker::make('loan_due_date')
                    ->label('Loan Due Date')
                    ->native(false)
                    ->disabled()
                    ->dehydrated()
                    ->format('Y-m-d')
                    ->displayFormat('Y-m-d')
                    ->required(),

                Repeater::make('third_parties')
                    ->label('Third-Party Refinances')
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->datalist(fn (): array => static::thirdPartyNameOptions())
                            ->helperText('Choose an active registered third party, or enter a legacy name if needed.'),
                        TextInput::make('balance')->label('Balance')->numeric()->required(),
                    ])
                    ->minItems(0)
                    ->maxItems(3)
                    ->reactive()
                    ->afterStateUpdated(function (Set $set, Get $get): void {
                        static::applyCalculations($get, $set);

                        if (
                            $get('loan_category') === 'Refinancing Loan'
                            && static::thirdPartyTotal($get('third_parties') ?? []) <= 0
                        ) {
                            $set('loan_category', null);

                            Notification::make()
                                ->warning()
                                ->title('Refinancing Loan cleared')
                                ->body('Refinancing Loan requires at least one third-party balance.')
                                ->send();
                        }
                    }),

                TextInput::make('repayment_amount')->label('Total Repayment')->numeric()->readOnly(),
                TextInput::make('interest_amount')->label('Total Interest')->numeric()->readOnly(),
                TextInput::make('interest_rate')->label('Interest Rate')->suffix('%')->numeric()->readOnly(),
                TextInput::make('admin_fee')->label('Admin Fee')->numeric()->readOnly(),
                TextInput::make('insurance_fee')->label('Insurance Fee')->numeric()->readOnly(),
                TextInput::make('arrangement_fee')->label('Arrangement Fee')->numeric()->readOnly(),
                TextInput::make('crb_fee')->label('CRB Fee')->numeric()->readOnly(),
                TextInput::make('payment')->label('Periodic Payment')->numeric()->readOnly(),
                TextInput::make('monthly_insurance')->label('Monthly Insurance')->numeric()->readOnly(),
                TextInput::make('total_monthly_repayment')->label('Total Monthly Repayment')->numeric()->readOnly(),
                TextInput::make('disbursement_amount')->label('Disbursement Amount')->numeric()->readOnly(),

                TextInput::make('loan_number')
                    ->label('Loan Number')
                    ->default(fn() => static::generateNextLoanIdentifier())
                    ->readOnly()
                    ->required(),

                TextInput::make('loan_id')
                    ->default(fn() => static::generateNextLoanIdentifier())
                    ->dehydrated()
                    ->hidden(),

                Select::make('from_this_account')
                    ->label('From this Account')
                    ->options($accountOptions)
                    ->searchable()
                    ->required(),

                TextInput::make('transaction_reference')
                    ->label('Transaction Reference')
                    ->default(fn() => 'TRX-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)))
                    ->readOnly()
                    ->required(),

                Toggle::make('activate_loan_agreement_form')
                    ->label('Generate Loan Application Form')
                    ->helperText('Generate a printable application form for the customer to sign.')
                    ->onColor('success')
                    ->offColor('danger'),

                TextInput::make('loan_agreement_file_path')->hidden(),
                TextInput::make('loan_application_file_path')->hidden(),
                TextInput::make('balance')->hidden(),
                TextInput::make('withholding_amount')->hidden(),

                TextInput::make('first_month_withholding')
                    ->label('Upfront Installment / Withholding')
                    ->numeric()
                    ->visible(fn(Get $get) => static::isGrzRefinance(
                        optional(LoanType::find($get('loan_type_id')))?->loan_name,
                        $get('third_parties') ?? []
                    ))
                    ->readOnly()
                    ->dehydrated(false),

                TextInput::make('first_installment')
                    ->label('First Installment')
                    ->numeric()
                    ->readOnly()
                    ->dehydrated(false),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Loan Overview')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('loan_id')
                            ->label('Loan Number')
                            ->badge(),
                        TextEntry::make('loan_status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'approved', 'partially_paid' => 'success',
                                'processing' => 'info',
                                'requested' => 'gray',
                                'defaulted' => 'warning',
                                'denied' => 'danger',
                                'Refund' => 'danger',
                                'settled', 'closed', 'Paid Off' => 'secondary',
                                'Closed' => 'secondary',
                                default => 'gray',
                            }),
                        TextEntry::make('exceptional_approval')
                            ->label('Exceptional Approval')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                            ->color(fn (bool $state): string => $state ? 'warning' : 'gray'),
                        TextEntry::make('loan_category')
                            ->label('Category'),
                        TextEntry::make('loan_release_date')
                            ->label('Release Date')
                            ->date(),
                        TextEntry::make('loan_due_date')
                            ->label('Due Date')
                            ->state(fn (Loan $record): ?string => static::resolveLoanDueDate($record))
                            ->formatStateUsing(fn (?string $state): string => $state ? Carbon::parse($state)->format('M d, Y') : 'N/A'),
                        TextEntry::make('loan_duration')
                            ->label('Duration (Months)'),
                        TextEntry::make('interest_rate')
                            ->label('Interest Rate')
                            ->suffix('%'),
                        TextEntry::make('transaction_reference')
                            ->label('Transaction Reference'),
                    ]),
                InfolistSection::make('Borrower Details')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('borrower.full_name')
                            ->label('Borrower')
                            ->state(fn (Loan $record): string => $record->borrower ? static::borrowerOptionLabel($record->borrower) : 'N/A'),
                        TextEntry::make('borrower.customer_id')
                            ->label('Customer ID'),
                        TextEntry::make('employee_no')
                            ->label('Employee Number'),
                        TextEntry::make('borrower.identification')
                            ->label('NRC'),
                        TextEntry::make('borrower.mobile')
                            ->label('Phone'),
                        TextEntry::make('borrower.employer')
                            ->label('Employer'),
                    ]),
                InfolistSection::make('Financial Summary')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('principal_amount')->label('Principal')->money('ZMW'),
                        TextEntry::make('interest_amount')->label('Total Interest')->money('ZMW'),
                        TextEntry::make('repayment_amount')->label('Total Repayment')->money('ZMW'),
                        TextEntry::make('balance')->label('Balance')->money('ZMW'),
                        TextEntry::make('admin_fee')->label('Admin Fee')->money('ZMW'),
                        TextEntry::make('insurance_fee')->label('Insurance Fee')->money('ZMW'),
                        TextEntry::make('arrangement_fee')->label('Arrangement Fee')->money('ZMW'),
                        TextEntry::make('crb_fee')->label('CRB Fee')->money('ZMW'),
                        TextEntry::make('payment')->label('Periodic Payment')->money('ZMW'),
                        TextEntry::make('monthly_insurance')->label('Monthly Insurance')->money('ZMW'),
                        TextEntry::make('total_monthly_repayment')->label('Total Monthly Repayment')->money('ZMW'),
                        TextEntry::make('disbursement_amount')->label('Disbursement Amount')->money('ZMW'),
                    ]),
                InfolistSection::make('Status Reason')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('statusReason.code')
                            ->label('Active Code')
                            ->badge()
                            ->placeholder('None'),
                        TextEntry::make('statusReason.label')
                            ->label('Current Status Reason')
                            ->placeholder('None'),
                        TextEntry::make('statusReason.group')
                            ->label('Category')
                            ->placeholder('None'),
                        TextEntry::make('latestStatusReasonEvent.effective_date')
                            ->label('Effective Date')
                            ->date(),
                        TextEntry::make('latestStatusReasonEvent.performed_by_name')
                            ->label('Last Updated By')
                            ->placeholder('-'),
                        TextEntry::make('latestStatusReasonEvent.action')
                            ->label('Last Action')
                            ->badge()
                            ->placeholder('-'),
                        TextEntry::make('latestStatusReasonEvent.mode_of_exit')
                            ->label('Mode Of Exit')
                            ->placeholder('-'),
                        TextEntry::make('latestStatusReasonEvent.affordability_reason')
                            ->label('Affordability Reason')
                            ->placeholder('-'),
                        TextEntry::make('latestStatusReasonEvent.notes')
                            ->label('Latest Notes')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
                InfolistSection::make('Top-Up Tracking')
                    ->columns(3)
                    ->visible(fn (Loan $record): bool => filled($record->top_up_batch_reference)
                        || filled($record->top_up_parent_loan_id)
                        || filled($record->top_up_child_loan_id)
                        || (float) ($record->top_up_amount ?? 0) > 0)
                    ->schema([
                        TextEntry::make('top_up_amount')->label('Additional Top-Up')->money('ZMW'),
                        TextEntry::make('top_up_source_total')->label('Settled Balance Total')->money('ZMW'),
                        TextEntry::make('top_up_batch_reference')->label('Batch Reference')->placeholder('-'),
                        TextEntry::make('top_up_parent_loan_id')->label('Parent Loan')->placeholder('-'),
                        TextEntry::make('top_up_child_loan_id')->label('Child Loan')->placeholder('-'),
                        TextEntry::make('top_up_settled_at')->label('Settled At')->date(),
                    ]),
                InfolistSection::make('Refinance / Withholding')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('third_party_name')
                            ->label('Third Party 1')
                            ->placeholder('-'),
                        TextEntry::make('third_party_balance')
                            ->label('Third Party 1 Balance')
                            ->money('ZMW'),
                        TextEntry::make('third_party_name_2')
                            ->label('Third Party 2')
                            ->placeholder('-'),
                        TextEntry::make('third_party_balance_2')
                            ->label('Third Party 2 Balance')
                            ->money('ZMW'),
                        TextEntry::make('third_party_name_3')
                            ->label('Third Party 3')
                            ->placeholder('-'),
                        TextEntry::make('third_party_balance_3')
                            ->label('Third Party 3 Balance')
                            ->money('ZMW'),
                        TextEntry::make('total_third_party_balance')
                            ->label('Total Third Party Balance')
                            ->money('ZMW'),
                        TextEntry::make('withholding_amount')
                            ->label('Upfront Installment / Withholding')
                            ->money('ZMW'),
                    ]),
                InfolistSection::make('Files')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('loan_agreement_file_path')
                            ->label('Loan Agreement File')
                            ->formatStateUsing(fn (?string $state): string => $state ?: 'Not generated'),
                        TextEntry::make('loan_application_file_path')
                            ->label('Loan Application File')
                            ->formatStateUsing(fn (?string $state): string => $state ?: 'Not generated')
                            ->url(fn (Loan $record): ?string => filled($record->loan_application_file_path)
                                ? Storage::disk('public')->url($record->loan_application_file_path)
                                : null)
                            ->openUrlInNewTab(),
                        TextEntry::make('loan_settlement_file_path')
                            ->label('Settlement File')
                            ->formatStateUsing(fn (?string $state): string => $state ?: 'Not generated'),
                        TextEntry::make('supporting_documents_list')
                            ->label('Supporting Documents')
                            ->state(fn (Loan $record): string => $record->getMedia('supporting_documents')->pluck('file_name')->implode(', ') ?: 'Not uploaded'),
                        TextEntry::make('settlement_documents_list')
                            ->label('Settlement Documents')
                            ->state(fn (Loan $record): string => $record->getMedia('settlement_documents')->pluck('file_name')->implode(', ') ?: 'Not uploaded'),
                        TextEntry::make('exceptional_approval_email_screenshot_path')
                            ->label('Exceptional Approval Email Screenshot')
                            ->formatStateUsing(fn (?string $state): string => $state ? basename($state) : 'Not uploaded')
                            ->url(fn (Loan $record): ?string => filled($record->exceptional_approval_email_screenshot_path)
                                ? Storage::disk('public')->url($record->exceptional_approval_email_screenshot_path)
                                : null)
                            ->openUrlInNewTab(),
                    ]),
            ]);
    }

    protected static function requiresSettlementDocuments(?string $nextStatus, ?Loan $record): bool
    {
        if (! $record) {
            return false;
        }

        return $record->loan_status !== $nextStatus && in_array($nextStatus, ['settled', 'closed', 'Paid Off'], true);
    }

    protected static function applyCalculations(Get $get, Set $set): void
    {
        $loanTypeId   = $get('loan_type_id');
        $principal    = (float) ($get('principal_amount')   ?? 0);
        $duration     = (int)   ($get('loan_duration')      ?? 0);
        $thirdParties = $get('third_parties')              ?? [];
        $thirdTotal   = array_sum(array_map(fn($p) => floatval($p['balance'] ?? 0), $thirdParties));

        $set('duration_period', $duration);
        static::applyLoanDueDate($get, $set);

        if (! $loanTypeId || $principal <= 0 || $duration <= 0) {
            return;
        }

        $loanType    = LoanType::findOrFail($loanTypeId);
        $rate        = $loanType->interest_rate;
        $monthlyRate = $rate / 100 / 12;

        $pmt = $monthlyRate > 0
            ? ($monthlyRate * $principal) / (1 - pow(1 + $monthlyRate, -$duration))
            : ($principal / $duration);
        $pmt = round($pmt, 2);

        $totalRepayment = round($pmt * $duration, 2);
        $interestAmount = round($totalRepayment - $principal, 2);

        $loanName = strtolower($loanType->loan_name);
        $estimatedInsuranceFee = round($principal * 0.045, 2);
        $totalMonthly = round($pmt + round($estimatedInsuranceFee / $duration, 2), 2);
        [
            'admin_fee' => $adminFee,
            'insurance_fee' => $insuranceFee,
            'arrangement_fee' => $arrangementFee,
        ] = static::calculateFeeComponents($loanName, $principal, $totalRepayment, $duration, $totalMonthly);
        $crbFee         = 60;

        $monthlyInsure = round($insuranceFee / $duration, 2);

        $disbursement = round(
            $principal
          - ($adminFee + $arrangementFee + $crbFee + $thirdTotal),
            2
        );

        $set('repayment_amount',        $totalRepayment);
        $set('interest_amount',         $interestAmount);
        $set('interest_rate',           round($rate, 2));
        $set('admin_fee',               $adminFee);
        $set('insurance_fee',           $insuranceFee);
        $set('arrangement_fee',         $arrangementFee);
        $set('crb_fee',                 $crbFee);
        $set('payment',                 $pmt);
        $set('monthly_insurance',       $monthlyInsure);
        $set('total_monthly_repayment', $totalMonthly);
        $set('first_installment', $totalMonthly);
        $set('disbursement_amount',     $disbursement);

        $withholdingAmount = static::isGrzRefinance($loanType->loan_name, $thirdParties)
            ? $totalMonthly
            : 0;

        $set('first_month_withholding', $withholdingAmount);
        $set('withholding_amount', $withholdingAmount);
    }

    protected static function applyLoanDueDate(Get $get, Set $set): void
    {
        $loanTypeId = $get('loan_type_id');
        $duration = (int) ($get('loan_duration') ?? 0);
        $releaseDate = $get('loan_release_date');

        if (! $loanTypeId || ! $releaseDate || $duration <= 0) {
            $set('loan_due_date', null);
            return;
        }

        $loanType = LoanType::find($loanTypeId);

        if (! $loanType) {
            $set('loan_due_date', null);
            return;
        }

        $due = static::computeLoanDueDateValue($loanType->interest_cycle, (string) $releaseDate, $duration);

        $set('loan_due_date', $due);
    }

    public static function resolveLoanDueDate(Loan $loan): ?string
    {
        if ($loan->loan_due_date) {
            return (string) $loan->loan_due_date;
        }

        if ($loan->maturity_date) {
            return (string) $loan->maturity_date;
        }

        if ($loan->loan_release_date && $loan->loan_duration && $loan->loan_type?->interest_cycle) {
            return static::computeLoanDueDateValue(
                (string) $loan->loan_type->interest_cycle,
                (string) $loan->loan_release_date,
                (int) $loan->loan_duration,
            );
        }

        return null;
    }

    public static function computeLoanDueDateValue(string $interestCycle, string $releaseDate, int $duration): ?string
    {
        if ($releaseDate === '' || $duration <= 0) {
            return null;
        }

        $due = Carbon::parse($releaseDate);

        switch ($interestCycle) {
            case 'day(s)':
                $due = $due->addDays($duration);
                break;
            case 'week(s)':
                $due = $due->addWeeks($duration);
                break;
            case 'month(s)':
                $due = $due->addMonths($duration);
                break;
            case 'year(s)':
                $due = $due->addYears($duration);
                break;
            default:
                break;
        }

        return $due->toDateString();
    }

    public static function generateNextLoanIdentifier(): string
    {
        $lastLoanId = Loan::query()
            ->where('loan_id', 'like', 'L%')
            ->orderByRaw('LENGTH(loan_id) DESC')
            ->orderByDesc('loan_id')
            ->value('loan_id');

        $currentNumber = 0;
        $padding = 4;

        if (is_string($lastLoanId) && preg_match('/^L(\d+)$/', $lastLoanId, $matches)) {
            $currentNumber = (int) $matches[1];
            $padding = max(4, strlen($matches[1]));
        }

        return 'L' . str_pad((string) ($currentNumber + 1), $padding, '0', STR_PAD_LEFT);
    }

    public static function calculateFeeComponents(
        string $loanName,
        float $principal,
        float $totalRecoverable,
        int $duration = 0,
        ?float $totalMonthlyRepayment = null,
    ): array
    {
        $isCnmc = Str::contains($loanName, 'cnmc') || Str::contains($loanName, 'cmnc');
        $isGrz = Str::contains($loanName, 'grz');
        $arrangementBase = $isCnmc
            ? $principal
            : (($isGrz && $duration > 0 && $totalMonthlyRepayment !== null)
                ? round($totalMonthlyRepayment * $duration, 2)
                : $totalRecoverable);

        return [
            'admin_fee' => round($principal * ($isCnmc ? 0.005 : 0.015), 2),
            'insurance_fee' => round($principal * 0.045, 2),
            'arrangement_fee' => round($arrangementBase * ($isCnmc ? 0.020 : 0.025), 2),
        ];
    }

    public static function borrowerOptionLabel(?Borrower $borrower): string
    {
        if (! $borrower) {
            return 'N/A';
        }

        $name = $borrower->full_name ?: trim(($borrower->first_name ?? '') . ' ' . ($borrower->last_name ?? ''));
        $identifier = $borrower->mobile ?: $borrower->customer_id ?: $borrower->identification;

        return $identifier ? "{$name} - {$identifier}" : $name;
    }

    public static function isGrzRefinance(?string $loanName, array $thirdParties = []): bool
    {
        $normalizedLoanName = strtolower((string) $loanName);
        $thirdPartyTotal = static::thirdPartyTotal($thirdParties);

        return str_contains($normalizedLoanName, 'grz') && $thirdPartyTotal > 0;
    }

    public static function thirdPartyNameOptions(): array
    {
        return ThirdParty::query()
            ->active()
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->values()
            ->all();
    }

    public static function normalizeThirdParties(array $thirdParties = []): array
    {
        return collect($thirdParties)
            ->map(function ($party): array {
                $name = static::cleanString($party['name'] ?? null);
                $balance = isset($party['balance'])
                    ? (float) str_replace(',', '', (string) $party['balance'])
                    : 0.0;

                return [
                    'name' => $name,
                    'balance' => $balance,
                ];
            })
            ->filter(fn (array $party): bool => filled($party['name']) || $party['balance'] > 0)
            ->take(3)
            ->values()
            ->all();
    }

    public static function fillThirdPartyColumns(array $data, array $thirdParties = []): array
    {
        $thirdParties = static::normalizeThirdParties($thirdParties);

        $data['third_party_name'] = $thirdParties[0]['name'] ?? null;
        $data['third_party_balance'] = $thirdParties[0]['balance'] ?? 0;
        $data['third_party_name_2'] = $thirdParties[1]['name'] ?? null;
        $data['third_party_balance_2'] = $thirdParties[1]['balance'] ?? 0;
        $data['third_party_name_3'] = $thirdParties[2]['name'] ?? null;
        $data['third_party_balance_3'] = $thirdParties[2]['balance'] ?? 0;
        $data['total_third_party_balance'] = static::thirdPartyTotal($thirdParties);
        $data['third_parties'] = $thirdParties;

        return $data;
    }

    public static function thirdPartyRepeaterState(?Loan $loan): array
    {
        if (! $loan) {
            return [];
        }

        return static::normalizeThirdParties([
            [
                'name' => $loan->third_party_name,
                'balance' => $loan->third_party_balance,
            ],
            [
                'name' => $loan->third_party_name_2,
                'balance' => $loan->third_party_balance_2,
            ],
            [
                'name' => $loan->third_party_name_3,
                'balance' => $loan->third_party_balance_3,
            ],
        ]);
    }

    public static function thirdPartyTotal(array $thirdParties = []): float
    {
        return (float) collect($thirdParties)
            ->sum(fn ($party) => (float) ($party['balance'] ?? 0));
    }

    public static function runningLoanStatuses(): array
    {
        return [
            'requested',
            'processing',
            'approved',
            'partially_paid',
            'defaulted',
        ];
    }

    public static function hasRunningLoanForCategory(int $borrowerId, string $category, ?int $ignoreRecordId = null): bool
    {
        return Loan::query()
            ->where('borrower_id', $borrowerId)
            ->where('loan_category', $category)
            ->whereIn('loan_status', static::runningLoanStatuses())
            ->when($ignoreRecordId, fn ($query) => $query->whereKeyNot($ignoreRecordId))
            ->exists();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('borrower_name')
                    ->label('Borrower')
                    ->getStateUsing(fn (Loan $record) => trim(collect([
                        $record->borrower?->other_names ?: $record->borrower?->first_name ?: $record->other_names,
                        $record->borrower?->last_name ?: $record->last_name,
                    ])->filter()->implode(' ')))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('borrower', function (Builder $borrowerQuery) use ($search): void {
                            $borrowerQuery
                                ->where('full_name', 'like', "%{$search}%")
                                ->orWhere('first_name', 'like', "%{$search}%")
                                ->orWhere('other_names', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: function ($query, string $direction) {
                        return $query
                            ->orderBy('other_names', $direction)
                            ->orderBy('last_name', $direction);
                    }),
                TextColumn::make('loan_id')
                    ->label('Loan ID')
                    ->badge()
                    ->sortable()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('loan_id', 'like', "%{$search}%")
                            ->orWhere('loan_number', 'like', "%{$search}%");
                    }),
                TextColumn::make('loan_due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('employee_no')
                    ->label('Employee #')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('loan_category')
                    ->label('Loan Type')
                    ->searchable(),
    
                TextColumn::make('loan_release_date')
                    ->label('Release Date')
                    ->date()
                    ->sortable(),
                    
                TextColumn::make('loan_status')
                    ->label('Status')
                    ->badge()
                    ->searchable()
                    ->color(fn($state) => match ($state) {
                        'requested'  => 'gray',
                        'processing' => 'info',
                        'approved'   => 'success',
                        'denied'     => 'danger',
                        'defaulted'  => 'warning',
                        'Refund'     => 'warning',
                        'settled'    => 'secondary',
                        'Closed'     => 'secondary',
                        default      => 'secondary',
                    }),
                TextColumn::make('statusReason.code')
                    ->label('Status Reason')
                    ->badge()
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('statusReason.label')
                    ->label('Reason Label')
                    ->limit(28)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                TextColumn::make('principal_amount')
                    ->label('Principal')
                    ->money('ZMW')
                    ->sortable(),

                TextColumn::make('interest_amount')
                    ->label('Total Interest')
                    ->money('ZMW')
                    ->sortable(),
            
                TextColumn::make('repayment_amount')
                    ->label('Total Repayment')
                    ->money('ZMW'),
                    
                TextColumn::make('balance')
                    ->label('Balance')
                    ->money('ZMW'),
                    
                TextColumn::make('disbursement_amount')
                    ->label('Disbursement')
                    ->money('ZMW'),
                    
                TextColumn::make('loan_category')
                    ->label('Category')
                    ->state(fn (Loan $record): string => $record->loan_category ?: ($record->loan_type?->loan_name ?? 'N/A'))
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('loan_status')
                    ->label('Status')
                    ->options([
                        'requested'  => 'Requested',
                        'processing' => 'Processing',
                        'approved'   => 'Approved',
                        'denied'     => 'Denied',
                        'defaulted'  => 'Defaulted',
                        'Refund'     => 'Refund',
                        'Closed'     => 'Closed',
                        'settled'    => 'Settled',
                    ]),
                SelectFilter::make('status_reason_id')
                    ->relationship('statusReason', 'code')
                    ->label('Status Reason'),
                Filter::make('blocked_by_status_reason')
                    ->query(fn (Builder $query) => $query->whereHas('statusReason', fn (Builder $subQuery) => $subQuery->where('blocks_new_loan', true))),

                Filter::make('recent')
                    ->label('Recent (7 days)')
                    ->query(fn($query) => $query
                        ->whereDate(
                            'loan_release_date',
                            '>=',
                            now()->subDays(7)->toDateString()
                        )
                    ),

                Filter::make('release_date')
                    ->label('Release Date')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('to')->label('To'),
                    ])
                    ->query(fn($query, array $data) => $query
                        ->when($data['from'], fn($q, $from) => $q->whereDate('loan_release_date', '>=', $from))
                        ->when($data['to'], fn($q, $to) => $q->whereDate('loan_release_date', '<=', $to))
                    ),
            ])
            ->headerActions([
                Tables\Actions\Action::make('importLoansCsv')
                    ->label('Bulk Import CSV')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        FileUpload::make('csv_file')
                            ->label('Loan CSV File')
                            ->disk('local')
                            ->directory('imports/loans')
                            ->acceptedFileTypes(['.csv', 'text/csv', 'application/vnd.ms-excel', 'text/plain'])
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $fileKey = $data['csv_file'] ?? null;

                        if (is_array($fileKey)) {
                            $fileKey = reset($fileKey);
                        }

                        $path = $fileKey ? Storage::disk('local')->path($fileKey) : null;

                        if (! $path || ! file_exists($path)) {
                            Notification::make()->danger()->title('CSV file not found')->send();
                            return;
                        }

                        $file = new \SplFileObject($path);
                        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
                        $file->setCsvControl(',');

                        $file->rewind();
                        $rawHeader = $file->fgetcsv();

                        if (! is_array($rawHeader) || count(array_filter($rawHeader, fn ($value) => trim((string) $value) !== '')) === 0) {
                            Notification::make()->danger()->title('CSV header not found')->send();
                            return;
                        }

                        $header = array_map([static::class, 'normalizeCsvHeader'], $rawHeader);
                        $created = 0;
                        $updated = 0;
                        $skipped = 0;
                        $skipReasons = [];
                        $rowNumber = 1;

                        while (! $file->eof()) {
                            $rowNumber++;
                            $row = $file->fgetcsv();

                            if (! is_array($row) || count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                                continue;
                            }

                            $row = array_pad($row, count($header), null);
                            $csv = array_combine($header, array_slice($row, 0, count($header)));

                            if (! is_array($csv)) {
                                $skipped++;
                                continue;
                            }

                            try {
                                $result = static::upsertLoanFromCsvRow($csv);
                            } catch (\Throwable $exception) {
                                $skipped++;
                                $skipReasons[] = "Row {$rowNumber}: {$exception->getMessage()}";
                                continue;
                            }

                            if ($result === 'created') {
                                $created++;
                            } elseif ($result === 'updated') {
                                $updated++;
                            } else {
                                $skipped++;
                            }
                        }

                        $message = "Created: {$created} | Updated: {$updated} | Skipped: {$skipped}";

                        if ($skipReasons !== []) {
                            $message .= "\n" . implode("\n", array_slice($skipReasons, 0, 5));
                        }

                        Notification::make()
                            ->title('Loan import complete')
                            ->body($message)
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
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

    protected static function upsertLoanFromCsvRow(array $row): string
    {
        $loanId = static::csvValue($row, ['loan_id']);

        if (! $loanId) {
            throw new \InvalidArgumentException('missing loan_id');
        }

        $clientId = static::csvValue($row, ['client_id']);
        $borrowerId = static::resolveBorrowerId($clientId);
        $loanTypeName = static::csvValue($row, ['loan_type']);
        $loan = Loan::query()->firstOrNew(['loan_id' => $loanId]);
        $interestRate = static::parseCsvNumber(static::csvValue($row, ['interest_rate']));
        $loanTypeId = static::resolveLoanTypeId($loanTypeName, static::csvValue($row, ['employer']), $interestRate) ?? $loan->loan_type_id;
        $walletId = Wallet::query()->value('id');

        if (! $borrowerId) {
            throw new \InvalidArgumentException("borrower not found for client_id {$clientId}");
        }

        $principal = static::parseCsvNumber(static::csvValue($row, ['amount']));
        $openingBalance = static::parseCsvNumber(static::csvValue($row, ['outstanding_balance', 'total_outstanding_balance']));
        $issueDate = static::parseCsvDate(static::csvValue($row, ['loan_issue_date']));
        $duration = static::parseCsvInteger(static::csvValue($row, ['term_months']));

        if ($principal === null) {
            throw new \InvalidArgumentException("missing amount for loan {$loanId}");
        }

        if (! $issueDate) {
            throw new \InvalidArgumentException("missing loan issue date for loan {$loanId}");
        }

        if (! $duration) {
            throw new \InvalidArgumentException("missing term months for loan {$loanId}");
        }

        $payload = [
            'loan_id' => $loanId,
            'borrower_id' => $borrowerId,
            'loan_type_id' => $loanTypeId,
            'loan_category' => $loanTypeName,
            'employee_no' => static::csvValue($row, ['employee_no']),
            'employer' => static::csvValue($row, ['employer']),
            'other_names' => static::csvValue($row, ['other_names', 'other_name']),
            'last_name' => static::csvValue($row, ['last_name']),
            'third_party_name' => static::csvValue($row, ['third_party_name_1', 'third_party_name']),
            'third_party_balance' => static::parseCsvNumber(static::csvValue($row, ['third_party_balance_1', 'third_party_balance'])) ?? 0,
            'third_party_name_2' => static::csvValue($row, ['third_party_name_2']),
            'third_party_balance_2' => static::parseCsvNumber(static::csvValue($row, ['third_party_balance_2'])) ?? 0,
            'third_party_name_3' => static::csvValue($row, ['third_party_name_3']),
            'third_party_balance_3' => static::parseCsvNumber(static::csvValue($row, ['third_party_balance_3'])) ?? 0,
            'total_third_party_balance' => static::parseCsvNumber(static::csvValue($row, ['total_third_party_balance'])) ?? 0,
            'date_of_birth' => static::parseCsvDate(static::csvValue($row, ['dob'])),
            'nrc' => static::csvValue($row, ['nrc']),
            'gender' => static::normalizeGender(static::csvValue($row, ['gender'])),
            'zedfin_balance' => static::parseCsvNumber(static::csvValue($row, ['zedfin_balance'])) ?? 0,
            'loan_status' => static::mapLoanStatus(static::csvValue($row, ['status']), $openingBalance),
            'principal_amount' => $principal,
            'loan_release_date' => $issueDate,
            'first_repayment_date' => static::parseCsvDate(static::csvValue($row, ['first_repayment_date'])),
            'loan_duration' => $duration,
            'term_months' => $duration,
            'duration_period' => 'months',
            'transaction_reference' => static::csvValue($row, ['transaction_reference']) ?: 'TRX-IMPORT-' . $loanId,
            'repayment_amount' => static::parseCsvNumber(static::csvValue($row, ['total_recoverable'])) ?? 0,
            'installment_without_insurance' => static::parseCsvNumber(static::csvValue($row, ['installment_amount_without_insurance'])) ?? 0,
            'loan_due_date' => static::parseCsvDate(static::csvValue($row, ['maturity_date'])),
            'maturity_date' => static::parseCsvDate(static::csvValue($row, ['maturity_date'])),
            'interest_amount' => static::parseCsvNumber(static::csvValue($row, ['total_interest_charged'])) ?? 0,
            'total_interest_charged' => static::parseCsvNumber(static::csvValue($row, ['total_interest_charged'])) ?? 0,
            'total_recoverable' => static::parseCsvNumber(static::csvValue($row, ['total_recoverable'])) ?? 0,
            'activate_loan_agreement_form' => false,
            'loan_agreement_file_path' => null,
            'interest_rate' => static::parseCsvNumber(static::csvValue($row, ['annual_interest_rate'])) ?? 0,
            'loan_number' => $loanId,
            'from_this_account' => $walletId,
            'balance' => $openingBalance ?? $principal,
            'loan_settlement_file_path' => null,
            'admin_fee' => static::parseCsvNumber(static::csvValue($row, ['admin_fee'])) ?? 0,
            'insurance_fee' => static::parseCsvNumber(static::csvValue($row, ['insurance_premium'])) ?? 0,
            'monthly_insurance' => static::parseCsvNumber(static::csvValue($row, ['monthly_insurance_installment'])) ?? 0,
            'total_monthly_repayment' => static::parseCsvNumber(static::csvValue($row, ['total_monthly_repayment'])) ?? 0,
            'disbursement_amount' => static::parseCsvNumber(static::csvValue($row, ['disbursement_amount'])) ?? 0,
            'final_disbursement_amount' => static::parseCsvNumber(static::csvValue($row, ['final_disbursement_amount'])) ?? static::parseCsvNumber(static::csvValue($row, ['disbursement_amount'])) ?? 0,
            'withholding_amount' => static::parseCsvNumber(static::csvValue($row, ['withholding_amount'])) ?? 0,
            'arrangement_fee' => static::parseCsvNumber(static::csvValue($row, ['arrangement_fee'])) ?? 0,
            'crb_fee' => static::parseCsvNumber(static::csvValue($row, ['crb_fee'])) ?? 0,
            'payment' => static::parseCsvNumber(static::csvValue($row, ['installment_amount_without_insurance'])) ?? 0,
            'status_reason_id' => null,
        ];

        $wasExisting = $loan->exists;

        // Preserve the imported balance as the opening cycle balance.
        // Subsequent repayments should be the only process that reduces it.
        $loan->forceFill($payload);
        $loan->save();

        return $wasExisting ? 'updated' : 'created';
    }

    protected static function resolveBorrowerId(?string $clientId): ?int
    {
        if (! $clientId) {
            return null;
        }

        return Borrower::query()
            ->where('customer_id', $clientId)
            ->value('id');
    }

    protected static function resolveLoanTypeId(?string $loanType, ?string $employer = null, ?float $interestRate = null): ?int
    {
        $types = LoanType::query()->get(['id', 'loan_name', 'interest_rate']);

        $employerAndRateMatch = static::resolveLoanTypeIdFromEmployerAndRate($employer, $interestRate, $types);

        if ($employerAndRateMatch) {
            return $employerAndRateMatch;
        }

        if (! $loanType) {
            return static::resolveLoanTypeIdFromEmployer($employer, $types);
        }

        $normalized = static::normalizeLookupValue($loanType);

        $record = $types->first(function ($type) use ($normalized) {
            return static::normalizeLookupValue($type->loan_name) === $normalized;
        });

        if (! $record) {
            $record = $types->first(function ($type) use ($normalized) {
                $typeName = static::normalizeLookupValue($type->loan_name);

                return str_contains($typeName, $normalized) || str_contains($normalized, $typeName);
            });
        }

        if (! $record) {
            $aliases = static::loanTypeAliases($loanType, $employer);

            foreach ($aliases as $alias) {
                $alias = static::normalizeLookupValue($alias);

                $record = $types->first(function ($type) use ($alias) {
                    $typeName = static::normalizeLookupValue($type->loan_name);

                    return $typeName === $alias || str_contains($typeName, $alias) || str_contains($alias, $typeName);
                });

                if ($record) {
                    break;
                }
            }
        }

        return $record?->id ?? static::resolveLoanTypeIdFromEmployer($employer, $types);
    }

    protected static function resolveLoanTypeIdFromEmployerAndRate(?string $employer, ?float $interestRate, $types = null): ?int
    {
        $normalizedEmployer = static::normalizeLookupValue($employer);

        if ($normalizedEmployer === '' || $interestRate === null) {
            return null;
        }

        $types ??= LoanType::query()->get(['id', 'loan_name', 'interest_rate']);
        $roundedRate = round($interestRate, 2);

        $match = $types->first(function ($type) use ($normalizedEmployer, $roundedRate) {
            $typeName = static::normalizeLookupValue($type->loan_name);

            if (round((float) $type->interest_rate, 2) !== $roundedRate) {
                return false;
            }

            if (str_contains($normalizedEmployer, 'grz')) {
                return str_contains($typeName, 'grz');
            }

            if (str_contains($normalizedEmployer, 'cnmc')) {
                return str_contains($typeName, 'cnmc');
            }

            return false;
        });

        return $match?->id;
    }

    protected static function loanTypeAliases(?string $loanType, ?string $employer = null): array
    {
        $normalizedLoanType = static::normalizeLookupValue($loanType);
        $normalizedEmployer = static::normalizeLookupValue($employer);

        $aliases = [$loanType];

        if (str_contains($normalizedLoanType, 'consumerloan')) {
            $aliases[] = 'payroll cnmc';
            $aliases[] = 'payroll grz';
            $aliases[] = 'consumer';
        }

        if (str_contains($normalizedLoanType, 'refinancingloan')) {
            $aliases[] = 'refinancing';
            $aliases[] = 'payroll cnmc';
            $aliases[] = 'payroll grz';
        }

        if (str_contains($normalizedEmployer, 'grz')) {
            $aliases[] = 'payroll grz';
        }

        if (str_contains($normalizedEmployer, 'cnmc')) {
            $aliases[] = 'payroll cnmc';
        }

        return array_values(array_unique(array_filter($aliases)));
    }

    protected static function resolveLoanTypeIdFromEmployer(?string $employer, $types = null): ?int
    {
        $normalizedEmployer = static::normalizeLookupValue($employer);

        if ($normalizedEmployer === '') {
            return null;
        }

        $types ??= LoanType::query()->get(['id', 'loan_name']);

        $match = $types->first(function ($type) use ($normalizedEmployer) {
            $typeName = static::normalizeLookupValue($type->loan_name);

            if (str_contains($normalizedEmployer, 'grz')) {
                return str_contains($typeName, 'grz');
            }

            if (str_contains($normalizedEmployer, 'cnmc')) {
                return str_contains($typeName, 'cnmc');
            }

            return false;
        });

        return $match?->id;
    }

    protected static function normalizeLookupValue(?string $value): string
    {
        $value = strtolower(trim((string) $value));

        return preg_replace('/[^a-z0-9]+/', '', $value);
    }

    protected static function mapLoanStatus(?string $status, ?float $balance): string
    {
        $status = strtolower(trim((string) $status));

        return match ($status) {
            'active' => ($balance !== null && $balance <= 0) ? 'Paid Off' : 'approved',
            'closed', 'paid off', 'paid_off' => 'Paid Off',
            'processing' => 'processing',
            'defaulted' => 'defaulted',
            'denied', 'rejected' => 'denied',
            default => ($balance !== null && $balance <= 0) ? 'Paid Off' : 'approved',
        };
    }

    protected static function normalizeCsvHeader(mixed $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);
        $header = trim($header);
        $header = str_replace(["'", '.', '-'], '', $header);
        $header = preg_replace('/\s+/', ' ', $header);

        return Str::snake($header);
    }

    protected static function csvValue(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = static::cleanString($row[$key] ?? null);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    protected static function cleanString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        $value = preg_replace('/\s+/', ' ', $value);

        if ($value === '') {
            return null;
        }

        return $value;
    }

    protected static function normalizeGender(mixed $value): ?string
    {
        $value = strtolower(static::cleanString($value) ?? '');

        return match ($value) {
            'm', 'male' => 'male',
            'f', 'female' => 'female',
            default => null,
        };
    }

    protected static function parseCsvInteger(mixed $value): ?int
    {
        $number = static::parseCsvNumber($value);

        return $number === null ? null : (int) round($number);
    }

    protected static function parseCsvNumber(mixed $value): ?float
    {
        $value = static::cleanString($value);

        if (! $value) {
            return null;
        }

        $value = str_replace(',', '', $value);

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    protected static function parseCsvDate(mixed $value): ?string
    {
        $value = static::cleanString($value);

        if (! $value) {
            return null;
        }

        $value = preg_replace('/\s*([\/-])\s*/', '$1', $value);

        if (is_numeric($value)) {
            $numericValue = (float) $value;

            if ($numericValue > 1000) {
                return Carbon::create(1899, 12, 30)->addDays((int) round($numericValue))->format('Y-m-d');
            }
        }

        foreach (['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'm-d-Y', 'j-M-y', 'j-M-Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('Y-m-d');
            } catch (\Throwable $exception) {
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $exception) {
            return null;
        }
    }

    // In your LoanResource or Loan model after loan creation:
    protected static function afterCreate(Loan $loan): void
    {
        parent::afterCreate($loan);

        if ($loan->loanType->loan_name === 'payroll_grz') {
            \App\Models\WithheldLoan::create([
                'loan_id' => $loan->loan_id,
                'amount'  => $loan->first_month_withholding,
                // Add other fields as needed
            ]);
        }
    }
}
