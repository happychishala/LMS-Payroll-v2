<?php
namespace App\Filament\Resources;

use App\Exports\RepaymentsExport;
use App\Filament\Resources\RepaymentsResource\Pages\ListRepayments;
use App\Filament\Resources\RepaymentsResource\Pages\CreateRepayments;
use App\Filament\Resources\RepaymentsResource\Pages\ViewRepayments;
use App\Filament\Resources\RepaymentsResource\Pages\EditRepayments;
use App\Models\Repayments;
use App\Models\Loan;
use App\Models\InvalidRepayment;
use App\Models\SkippedRepayment;
use App\Services\RepaymentAllocationService;
use App\Services\RepaymentNumberResolver;
use App\Services\RepaymentScheduleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Filament\Resources\Resource;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\ExportBulkAction;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class RepaymentsResource extends Resource
{
    protected static ?string $model = Repayments::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Repayments';

    protected static function paymentTypeOptions(): array
    {
        return Repayments::query()
            ->whereNotNull('payments_method')
            ->where('payments_method', '!=', '')
            ->distinct()
            ->orderBy('payments_method')
            ->pluck('payments_method')
            ->mapWithKeys(fn (string $value): array => [$value => static::formatPaymentTypeLabel($value)])
            ->all();
    }

    protected static function formatPaymentTypeLabel(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return 'Unknown';
        }

        return match (strtolower($value)) {
            'bank_transfer' => 'Bank Transfer',
            'mobile_money' => 'Mobile Money',
            'payroll' => 'Payroll',
            'cash' => 'Cash',
            'ddacc' => 'DDACC',
            default => Str::of($value)->replace(['_', '-'], ' ')->title()->value(),
        };
    }

    protected static function resolveCurrentCycle(Loan $loan, ?string $receiptDate = null): int
    {
        return app(RepaymentScheduleService::class)->computeRepaymentNumber(
            $loan,
            Carbon::parse($receiptDate ?: now()->toDateString()),
        );
    }

    protected static function clearRepaymentPreview(callable $set): void
    {
        $set('balance', null);
        $set('current_balance', null);
        $set('interest_due', null);
        $set('monthly_insurance', null);
        $set('paid_interest', null);
        $set('insurance_paid', null);
        $set('paid_principal', null);
        $set('receipt_amount', null);
        $set('employee_no', null);
    }

    protected static function refreshRepaymentPreview(callable $set, callable $get): void
    {
        $loanId = $get('loan_id');

        if (! $loanId || ! is_string($loanId)) {
            static::clearRepaymentPreview($set);

            return;
        }

        $loan = Loan::query()->where('loan_id', $loanId)->first();

        if (! $loan) {
            static::clearRepaymentPreview($set);

            return;
        }

        $receiptDate = Carbon::parse($get('receipt_date') ?: now()->toDateString());
        $paymentAmount = round(max(0, (float) ($get('payments') ?? 0)), 2);
        $repaymentNumber = static::resolveCurrentCycle($loan, $receiptDate->toDateString());

        $allocation = app(RepaymentAllocationService::class)->allocateForMonth(
            $loan,
            $paymentAmount,
            $repaymentNumber,
            $receiptDate,
            true,
        );

        $set('balance', round((float) ($allocation['opening_balance'] ?? 0), 2));
        $set('current_balance', round((float) ($allocation['closing_balance'] ?? 0), 2));
        $set('interest_due', round((float) ($allocation['interest_due'] ?? 0), 2));
        $set('monthly_insurance', round((float) ($allocation['insurance_due'] ?? 0), 2));
        $set('paid_interest', round((float) ($allocation['paid_interest'] ?? 0), 2));
        $set('insurance_paid', round((float) ($allocation['insurance_paid'] ?? 0), 2));
        $set('paid_principal', round((float) ($allocation['paid_principal'] ?? 0), 2));
        $set('receipt_amount', $loan->total_monthly_repayment);
        $set('employee_no', $loan->employee_no);
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Select::make('loan_id')
                    ->label('Loan Number')
                    ->options(fn(Get $get) => Loan::pluck('loan_number', 'loan_id')->toArray())
                    ->searchable()
                    ->reactive()
                    ->required()
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        static::refreshRepaymentPreview($set, $get);
                    }),

                TextInput::make('payments')
                    ->label('Repayment Amount')
                    ->numeric()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        static::refreshRepaymentPreview($set, $get);
                    }),

                TextInput::make('balance')
                    ->label('Opening Balance')
                    ->numeric()
                    ->disabled(),
                TextInput::make('current_balance')
                    ->label('Current Balance')
                    ->numeric()
                    ->disabled(),
                TextInput::make('interest_due')
                    ->label('Interest Due')
                    ->numeric()
                    ->disabled(),
                TextInput::make('monthly_insurance')
                    ->label('Insurance Due')
                    ->numeric()
                    ->disabled(),
                TextInput::make('paid_interest')
                    ->label('Paid Interest')
                    ->numeric()
                    ->disabled(),
                TextInput::make('insurance_paid')
                    ->label('Insurance Paid')
                    ->numeric()
                    ->disabled(),
                TextInput::make('paid_principal')
                    ->label('Paid Principal')
                    ->numeric()
                    ->disabled(),

                DatePicker::make('receipt_date')
                    ->label('Receipt Date')
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        static::refreshRepaymentPreview($set, $get);
                    }),
                TextInput::make('receipt_amount')
                    ->label('Receipt Amount')
                    ->numeric(),
                Select::make('payments_method')
                    ->label('Payment Method')
                    ->options([
                        'bank_transfer' => 'Bank Transfer',
                        'mobile_money'  => 'Mobile Money',
                        'payroll'       => 'Payroll',
                        'DDACC'         => 'DDACC',
                        'cash'          => 'Cash',
                    ])
                    ->required(),
                TextInput::make('reference_number')
                    ->label('Transaction Reference'),
                TextInput::make('employee_no')
                    ->label('Employee No')
                    ->disabled()
                    ->dehydrated(false)
                    ->afterStateHydrated(fn($state, $set, $record) =>
                        $set('employee_no', $record?->loan?->borrower?->employee_no)
                    ),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            TextColumn::make('loan_number')->label('Loan ID')->searchable(),
            TextColumn::make('employee_no')->label('Employee No')->searchable(),
            TextColumn::make('batch_no')->label('Batch No')->searchable(),
            TextColumn::make('loan_issue_date')->label('Loan Release Date')->date(),
            TextColumn::make('employer')->searchable(),
            TextColumn::make('receipt_date')->label('Receipt Date')->date()->searchable()->sortable(),
            TextColumn::make('receipt_amount')->label('Receipt Amount')->money('ZMW'),
            TextColumn::make('payments_method')
                ->label('Payment Type')
                ->formatStateUsing(fn (?string $state): string => static::formatPaymentTypeLabel($state))
                ->badge(),
            TextColumn::make('paid_principal')
                ->label('Principal')
                ->money('ZMW'),
            TextColumn::make('paid_interest')
                ->label('Interest')
                ->money('ZMW'),
            TextColumn::make('insurance_paid')
                ->label('Insurance')
                ->money('ZMW'),
            TextColumn::make('closing_balance')
                ->label('Balance')
                ->money('ZMW')
                ->sortable(),
            TextColumn::make('payment_status')
                ->label('Status')
                ->badge()
                ->color(fn(string $state): string => match($state) {
                    'Paid' => 'success',
                    'Pending' => 'warning',
                    'Failed' => 'danger',
                    default => 'gray',
                }),
               
            ])
            ->filters([
                Tables\Filters\Filter::make('receipt_period')
                    ->label('Receipt period')
                    ->form([
                        TextInput::make('receipt_month')
                            ->label('Receipt Month')
                            ->type('month'),
                        DatePicker::make('receipt_date_from')
                            ->label('Receipt Date From'),
                        DatePicker::make('receipt_date_to')
                            ->label('Receipt Date To'),
                    ])
                    ->query(function ($query, array $data) {
                        return static::applyReceiptPeriodFilters($query, $data);
                    }),
            ])
            ->headerActions([
                CreateAction::make(),
                TableAction::make('exportExcel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-document-chart-bar')
                    ->form(static::exportFilterSchema())
                    ->action(function (array $data) {
                        if (! static::exportPeriodSelectionIsComplete($data)) {
                            Notification::make()
                                ->warning()
                                ->title('Select month and year')
                                ->body('Choose both Month and Year before exporting repayments.')
                                ->send();

                            return null;
                        }

                        return Excel::download(
                            new RepaymentsExport(static::repaymentExportQuery($data)),
                            'repayments_' . now()->format('Ymd_His') . '.xlsx'
                        );
                    }),
                TableAction::make('exportPdf')
                    ->label('Export PDF')
                    ->icon('heroicon-o-document-text')
                    ->form(static::exportFilterSchema())
                    ->action(function (array $data) {
                        if (! static::exportPeriodSelectionIsComplete($data)) {
                            Notification::make()
                                ->warning()
                                ->title('Select month and year')
                                ->body('Choose both Month and Year before exporting repayments.')
                                ->send();

                            return null;
                        }

                        $query = static::repaymentExportQuery($data);
                        $rowCount = (clone $query)->count();
                        if ($rowCount > 500) {
                            Notification::make()
                                ->warning()
                                ->title('PDF export too large')
                                ->body("Your filter matches {$rowCount} rows. Narrow it to 500 rows or fewer for PDF export.")
                                ->send();
                            return null;
                        }

                        $rows = $query
                            ->get()
                            ->map(fn (Repayments $row) => static::mapRepaymentExportRow($row))
                            ->all();

                        if (empty($rows)) {
                            Notification::make()->warning()->title('No repayments found for the selected filters')->send();
                            return null;
                        }

                        $pdf = Pdf::loadView('exports.repayments-pdf', ['rows' => $rows]);

                        return response()->streamDownload(
                            fn () => print($pdf->output()),
                            'repayments_' . now()->format('Ymd_His') . '.pdf'
                        );
                    }),

                TableAction::make('importCsv')
                    ->label('Import CSV')
                    ->icon('heroicon-o-arrow-down-on-square')
                    ->form([
                        \Filament\Forms\Components\FileUpload::make('csv_file')
                            ->label('CSV File')
                            ->disk('local')
                            ->directory('imports')
                            ->acceptedFileTypes(['.csv', 'text/csv', 'application/vnd.ms-excel', 'text/plain'])
                            ->required(),
                    ])
                    ->action(function (ListRepayments $page, array $data) {
                        // find stored file
                        $fileKey = $data['csv_file'] ?? null;
                        if (is_array($fileKey)) {
                            $fileKey = reset($fileKey);
                        }
                        $path = $fileKey ? Storage::disk('local')->path($fileKey) : null;
                        if (empty($path) || ! file_exists($path)) {
                            Notification::make()->danger()->title('File not found')->send();
                            return;
                        }

                        // use SplFileObject + fgetcsv so quoted fields (with commas) parse correctly
                        $file = new \SplFileObject($path);
                        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
                        $file->setCsvControl(',');

                        // read header
                        $file->rewind();
                        $rawHeader = $file->fgetcsv();
                        if (!is_array($rawHeader) || count(array_filter($rawHeader, fn($h) => trim((string)$h) !== '')) === 0) {
                            Notification::make()->warning()->title('CSV header not found')->send();
                            InvalidRepayment::create([
                                'csv_file' => $fileKey,
                                'errors' => 'Header not detected',
                                'status' => 'pending',
                            ]);
                            return;
                        }

                        // normalize header: remove BOM, trim, snake_case
                        $header = array_map(function ($h) {
                            $h = preg_replace('/^\xEF\xBB\xBF/', '', (string) $h);
                            $h = trim($h);
                            $h = str_replace('-', '_', $h);
                            return Str::snake($h);
                        }, $rawHeader);

                        Log::info('CSV headers parsed', ['file' => $fileKey, 'headers' => $header]);

                        $validRows = [];
                        $created = 0;
                        $skipped = 0;
                        $invalid = 0;
                        $rowNumber = 1; // header row

                        // helper for parsing various date formats
                        $parseDate = function (?string $d) {
                            if (empty($d)) return null;
                            $d = trim($d);
                            $formats = ['j/n/Y', 'j/n/y', 'd/m/Y', 'd/m/y', 'Y-m-d', 'n/j/Y', 'n/j/y', 'm/d/Y', 'm/d/y'];
                            foreach ($formats as $fmt) {
                                $parsed = static::parseDateWithFormat($fmt, $d);
                                if ($parsed) return $parsed;
                            }
                            try {
                                return Carbon::parse($d)->toDateString();
                            } catch (\Exception $e) {
                                return null;
                            }
                        };

                        // iterate rows
                        while (! $file->eof()) {
                            $row = $file->fgetcsv();
                            $rowNumber++;
                            if ($row === false || $row === null) continue;
                            // skip completely empty rows
                            if (count(array_filter($row, fn($c) => trim((string)$c) !== '')) === 0) continue;

                            // ensure header/row column counts align
                            $cols = $row;
                            if (count($cols) < count($header)) {
                                // pad with nulls
                                $cols = array_pad($cols, count($header), null);
                            }

                            $assoc = array_combine($header, $cols);

                            // tolerantly find keys
                            $employeeNo = $assoc['employee_no'] ?? $assoc['emp_no'] ?? $assoc['employee'] ?? null;
                            $amountRaw  = $assoc['amount'] ?? $assoc['amount_'] ?? $assoc[' amount '] ?? null;
                            $receiptRaw = $assoc['receipt_date'] ?? $assoc['receiptdate'] ?? $assoc['receipt_date_raw'] ?? $assoc['receipt'] ?? null;

                            // normalize amount: strip non numeric except dot and minus
                            $amount = null;
                            if ($amountRaw !== null) {
                                $clean = preg_replace('/[^\d\.\-]/', '', $amountRaw);
                                if (is_numeric($clean)) $amount = (float) $clean;
                            }

                            $receiptDate = $parseDate($receiptRaw);

                            $errors = [];
                            if (empty($employeeNo)) $errors[] = 'Missing employee_no';
                            if ($amount === null) $errors[] = 'Invalid amount';
                            if ($receiptDate === null) $errors[] = 'Invalid receipt_date';

                            if (! empty($errors)) {
                                InvalidRepayment::create([
                                    // ensure CSV filename is persisted (fallback to basename of path)
                                    'csv_file' => $fileKey ?? basename($path),
                                    'row_number' => $rowNumber,
                                    'employee_no' => $employeeNo,
                                    'name' => $assoc['name'] ?? null,
                                    'nrc' => $assoc['nrc'] ?? null,
                                    'amount_raw' => $amountRaw,
                                    'amount' => $amount,
                                    'receipt_date_raw' => $receiptRaw,
                                    'receipt_date' => $receiptDate,
                                    'errors' => implode('; ', $errors),
                                    'status' => 'pending',
                                ]);
                                 $invalid++;
                                 Log::info('Invalid CSV row saved', ['file' => $fileKey, 'row' => $rowNumber, 'errors' => $errors]);
                                 continue;
                             }

                            // collect normalized row for downstream processing
                            $validRows[] = [
                                'row_number' => $rowNumber,
                                'employee_no' => $employeeNo,
                                'name' => $assoc['name'] ?? null,
                                'nrc' => $assoc['nrc'] ?? null,
                                'amount' => $amount,
                                'receipt_date' => $receiptDate,
                            ];
                        }

                        Log::info('CSV parsing finished', [
                            'file' => $fileKey ?? basename($path),
                            'valid_rows' => count($validRows),
                            'invalid_rows' => $invalid,
                            'sample_valid' => array_slice($validRows, 0, 5),
                        ]);

                        Notification::make()
                            ->title("CSV parsed: valid " . count($validRows) . ", invalid " . $invalid)
                            ->info()
                            ->send();

                        if ($invalid > 0) {
                            $examples = \App\Models\InvalidRepayment::where('csv_file', $fileKey ?? basename($path))
                                ->orderBy('id','desc')->limit(3)->get()->map(fn($r) => "row {$r->row_number}: {$r->errors}")->join("\n");
                            Notification::make()->warning()->title('Invalid examples')->body($examples)->send();
                        }

                        if (empty($validRows)) {
                            // create a clear file-level invalid record so it's obvious in UI
                            InvalidRepayment::create([
                                'csv_file' => $fileKey ?? basename($path),
                                'row_number' => null,
                                'errors' => 'No valid rows found in CSV (all rows missing employee_no/amount/receipt_date)',
                                'status' => 'pending',
                            ]);
                            Notification::make()->warning()->title('CSV has no valid rows')->send();
                            return;
                        }

                        // process valid rows
                        foreach ($validRows as $r) {
                            try {
                                DB::transaction(function () use ($r, &$created, &$skipped, $fileKey, $path) {
                                    $remaining = (float) $r['amount'];
                                    $receiptDate = \Carbon\Carbon::parse($r['receipt_date']);

                                    // Candidate loans: loan_release_date <= receipt_date and positive balance
                                    $loans = Loan::where('employee_no', $r['employee_no'])
                                        ->whereIn('loan_status', ['approved', 'partially_paid'])
                                        ->where('balance', '>', 0)
                                        ->whereDate('loan_release_date', '<=', $receiptDate->toDateString())
                                        ->orderBy('loan_release_date', 'asc') // oldest first
                                        ->get();

                                    if ($loans->isEmpty()) {
                                        static::recordSkippedRepayment([
                                            'csv_file' => $fileKey ?? basename($path),
                                            'row_number' => $r['row_number'],
                                            'import_type' => 'payroll_csv',
                                            'reason' => 'No due loans found on or before receipt_date',
                                            'employee_no' => $r['employee_no'],
                                            'receipt_date' => $r['receipt_date'],
                                            'receipt_amount' => $r['amount'],
                                            'source_payload' => $r,
                                        ]);
                                        InvalidRepayment::create([
                                            'csv_file' => $fileKey ?? basename($path),
                                            'row_number' => $r['row_number'],
                                            'employee_no' => $r['employee_no'],
                                            'name' => $r['name'] ?? null,
                                            'amount_raw' => $r['amount'],
                                            'errors' => 'No due loans found on or before receipt_date',
                                            'status' => 'pending',
                                        ]);
                                        $skipped++;
                                        return;
                                    }

                                    // 1) First pass: cover scheduled installment for each due loan (no extra principal applied here)
                                    foreach ($loans as $loan) {
                                        if ($remaining <= 0) break;

                                        $repaymentNumber = app(\App\Services\RepaymentScheduleService::class)
                                            ->computeRepaymentNumber($loan, $receiptDate);
                                        $repaymentNumber = app(\App\Services\RepaymentAllocationService::class)
                                            ->resolveRepaymentNumberForOutstandingInterest($loan, $repaymentNumber);

                                        $alloc = app(\App\Services\RepaymentAllocationService::class)
                                            ->allocateForMonth($loan, $remaining, $repaymentNumber, $receiptDate, false); // NO extra
                                        $applied = (float) ($alloc['receipt_amount'] ?? 0);

                                        if ($applied <= 0) continue;

                                        $alloc['import_reference'] = static::buildPayrollImportReference(
                                            $r,
                                            $loan,
                                            $repaymentNumber,
                                            $alloc,
                                            'installment'
                                        );

                                        $rep = app(\App\Services\RepaymentImportService::class)
                                            ->upsertAndRecompute($loan, $repaymentNumber, $alloc);

                                        if (($rep->import_skipped_duplicate ?? false) === true) {
                                            $skipped++;
                                            static::recordSkippedRepayment([
                                                'csv_file' => $fileKey ?? basename($path),
                                                'row_number' => $r['row_number'],
                                                'import_type' => 'payroll_csv',
                                                'reason' => 'Duplicate payroll import allocation; matching import reference already exists.',
                                                'loan_id' => $loan->loan_id,
                                                'loan_number' => $loan->loan_number,
                                                'employee_no' => $r['employee_no'],
                                                'repayment_number' => $repaymentNumber,
                                                'receipt_date' => $r['receipt_date'],
                                                'receipt_amount' => $applied,
                                                'reference_number' => $alloc['import_reference'],
                                                'matched_repayment_id' => $rep->id,
                                                'source_payload' => $r,
                                            ]);
                                            \Illuminate\Support\Facades\Log::info('Skipped duplicate payroll import allocation', [
                                                'file' => $fileKey ?? basename($path),
                                                'row' => $r['row_number'],
                                                'loan_id' => $loan->loan_id,
                                                'repayment_number' => $repaymentNumber,
                                                'reference' => $alloc['import_reference'],
                                            ]);
                                            continue;
                                        }

                                        $remaining = max(0.0, $remaining - $applied);
                                        $created++;

                                        \Illuminate\Support\Facades\Log::info('Allocated installment', [
                                            'file' => $fileKey ?? basename($path),
                                            'row' => $r['row_number'],
                                            'loan_id' => $loan->loan_id,
                                            'repayment_number' => $repaymentNumber,
                                            'applied' => $applied,
                                            'remaining' => $remaining,
                                        ]);
                                    }

                                    // 2) If leftover, apply extra to FIRST (oldest) loan as extra principal
                                    if ($remaining > 0) {
                                        $firstLoan = $loans->first();
                                        $repaymentNumberFirst = app(\App\Services\RepaymentScheduleService::class)
                                            ->computeRepaymentNumber($firstLoan, $receiptDate);
                                        $repaymentNumberFirst = app(\App\Services\RepaymentAllocationService::class)
                                            ->resolveRepaymentNumberForOutstandingInterest($firstLoan, $repaymentNumberFirst);

                                        $allocExtra = app(\App\Services\RepaymentAllocationService::class)
                                            ->allocateForMonth($firstLoan, $remaining, $repaymentNumberFirst, $receiptDate, true); // allow extra
                                        $appliedExtra = (float) ($allocExtra['receipt_amount'] ?? 0);

                                        if ($appliedExtra > 0) {
                                            $allocExtra['import_reference'] = static::buildPayrollImportReference(
                                                $r,
                                                $firstLoan,
                                                $repaymentNumberFirst,
                                                $allocExtra,
                                                'extra'
                                            );

                                            $rep = app(\App\Services\RepaymentImportService::class)
                                                ->upsertAndRecompute($firstLoan, $repaymentNumberFirst, $allocExtra);

                                            if (($rep->import_skipped_duplicate ?? false) === true) {
                                                $skipped++;
                                                static::recordSkippedRepayment([
                                                    'csv_file' => $fileKey ?? basename($path),
                                                    'row_number' => $r['row_number'],
                                                    'import_type' => 'payroll_csv',
                                                    'reason' => 'Duplicate payroll extra allocation; matching import reference already exists.',
                                                    'loan_id' => $firstLoan->loan_id,
                                                    'loan_number' => $firstLoan->loan_number,
                                                    'employee_no' => $r['employee_no'],
                                                    'repayment_number' => $repaymentNumberFirst,
                                                    'receipt_date' => $r['receipt_date'],
                                                    'receipt_amount' => $appliedExtra,
                                                    'reference_number' => $allocExtra['import_reference'],
                                                    'matched_repayment_id' => $rep->id,
                                                    'source_payload' => $r,
                                                ]);
                                                \Illuminate\Support\Facades\Log::info('Skipped duplicate payroll extra allocation', [
                                                    'file' => $fileKey ?? basename($path),
                                                    'row' => $r['row_number'],
                                                    'loan_id' => $firstLoan->loan_id,
                                                    'repayment_number' => $repaymentNumberFirst,
                                                    'reference' => $allocExtra['import_reference'],
                                                ]);
                                            } else {
                                                $remaining = max(0.0, $remaining - $appliedExtra);
                                                $created++;

                                                \Illuminate\Support\Facades\Log::info('Applied extra to first loan', [
                                                    'file' => $fileKey ?? basename($path),
                                                    'row' => $r['row_number'],
                                                    'first_loan_id' => $firstLoan->loan_id,
                                                    'applied_extra' => $appliedExtra,
                                                    'remaining' => $remaining,
                                                ]);
                                            }
                                        }
                                    }

                                    // 3) Any remaining after that => persist for manual review
                                    if ($remaining > 0) {
                                        static::recordSkippedRepayment([
                                            'csv_file' => $fileKey ?? basename($path),
                                            'row_number' => $r['row_number'],
                                            'import_type' => 'payroll_csv',
                                            'reason' => 'Unallocated remainder after distributing across due loans and applying extra to first loan: ' . number_format($remaining, 2),
                                            'employee_no' => $r['employee_no'],
                                            'receipt_date' => $r['receipt_date'],
                                            'receipt_amount' => $remaining,
                                            'source_payload' => $r,
                                        ]);
                                        InvalidRepayment::create([
                                            'csv_file' => $fileKey ?? basename($path),
                                            'row_number' => $r['row_number'],
                                            'employee_no' => $r['employee_no'],
                                            'name' => $r['name'] ?? null,
                                            'amount_raw' => $r['amount'],
                                            'errors' => 'Unallocated remainder after distributing across due loans and applying extra to first loan: ' . number_format($remaining, 2),
                                            'status' => 'pending',
                                        ]);
                                        $skipped++;
                                    }
                                });
                            } catch (\Exception $e) {
                                InvalidRepayment::create([
                                    'csv_file' => $fileKey ?? basename($path),
                                    'row_number' => $r['row_number'],
                                    'employee_no' => $r['employee_no'],
                                    'name' => $r['name'] ?? null,
                                    'amount_raw' => $r['amount'],
                                    'errors' => 'Processing exception: ' . $e->getMessage(),
                                    'status' => 'pending',
                                ]);
                                $skipped++;
                                \Illuminate\Support\Facades\Log::error('CSV row processing failed', ['error' => $e->getMessage(), 'row' => $r['row_number']]);
                            }
                        }

                        Notification::make()
                            ->title("CSV import finished: created {$created}, skipped {$skipped}, invalid {$invalid}")
                            ->success()
                            ->send();
                    }),

                TableAction::make('importDatabaseCsv')
                    ->label('Import Repayment DB CSV')
                    ->icon('heroicon-o-document-arrow-down')
                    ->form([
                        \Filament\Forms\Components\FileUpload::make('csv_file')
                            ->label('Database-format CSV File')
                            ->disk('local')
                            ->directory('imports')
                            ->acceptedFileTypes(['.csv', 'text/csv', 'application/vnd.ms-excel', 'text/plain'])
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $fileKey = $data['csv_file'] ?? null;
                        if (is_array($fileKey)) {
                            $fileKey = reset($fileKey);
                        }

                        $path = $fileKey ? Storage::disk('local')->path($fileKey) : null;
                        if (empty($path) || ! file_exists($path)) {
                            Notification::make()->danger()->title('File not found')->send();
                            return;
                        }

                        [$created, $updated, $skipped, $invalid] = static::importDatabaseFormatCsv($path, $fileKey);

                        Notification::make()
                            ->title("DB CSV import finished: created {$created}, updated {$updated}, skipped {$skipped}, invalid {$invalid}")
                            ->success()
                            ->send();
                    }),
                                ])
                                ->searchable()
                                ->filters([
                                    Tables\Filters\SelectFilter::make('payments_method')
                                        ->label('Payment Type')
                                        ->options(static::paymentTypeOptions()),
                                ])
                                ->actions([
                                    //
                                ])
                                ->bulkActions([
                                    DeleteBulkAction::make(),
                                ]);
                        }
                    
                        public static function getRelations(): array
                        {
                            return [
                                //
                            ];
                        }
                    
                        public static function getPages(): array
                        {
                            return [
                                'index' => ListRepayments::route('/'),
                                'create' => CreateRepayments::route('/create'),
                                'view' => ViewRepayments::route('/{record}'),
                                'edit' => EditRepayments::route('/{record}/edit'),
                            ];
                        }

                        public function upsertRepayment(array $data, Loan $loan, int $repaymentNumber)
                        {
                            // $data must contain: paid_principal, paid_interest, insurance_paid, receipt_date, allocatedAmount, etc.
                            DB::transaction(function() use ($data, $loan, $repaymentNumber) {
                                $receiptDate = $data['receipt_date'] ?? now()->toDateString();
                                $receiptDate = Carbon::parse($receiptDate)->toDateString();

                                // Only merge if receipt_date matches; otherwise create a new record
                                $rep = Repayments::where('loan_id', $loan->loan_id)
                                    ->where('repayment_number', $repaymentNumber)
                                    ->whereDate('receipt_date', $receiptDate)
                                    ->lockForUpdate()
                                    ->first();

                                if ($rep) {
                                    // Merge: sum amounts (or replace depending on policy)
                                    $rep->paid_principal   = $rep->paid_principal + ($data['paid_principal'] ?? 0);
                                    $rep->paid_interest    = $rep->paid_interest + ($data['paid_interest'] ?? 0);
                                    $rep->insurance_paid   = $rep->insurance_paid + ($data['insurance_paid'] ?? 0);
                                    $rep->receipt_amount   = $rep->receipt_amount + ($data['receipt_amount'] ?? 0);
                                    $rep->receipt_date     = $receiptDate;
                                    $rep->save();
                                } else {
                                    // Even if same repayment_number exists on a different date, create a new row
                                    $rep = Repayments::create(array_merge($data, [
                                        'loan_id' => $loan->loan_id,
                                        'repayment_number' => $repaymentNumber,
                                        'receipt_date' => $receiptDate,
                                    ]));
                                }

                                // Recompute ledger from this repayment number onward
                                $this->recomputeLoanLedger($loan);
                            });
                        }

                        private static function computeOpeningBalanceForRepayment(Loan $loan, int $repaymentNumber): float
                        {
                            // starting from principal or loan->balance at creation, apply previous repayments to compute opening
                            $opening = $loan->principal_amount ?? $loan->balance ?? 0;
                            $prevReps = Repayments::where('loan_id', $loan->loan_id)
                                ->where('repayment_number', '<', $repaymentNumber)
                                ->orderBy('repayment_number', 'asc')
                                ->get();

                            foreach ($prevReps as $r) {
                                $opening = max(0, $opening - ($r->paid_principal ?? 0));
                            }
                            return $opening;
                        }

                        private static function recomputeLoanLedger(Loan $loan): void
                        {
                            // load schedule info
                            $rate = ($loan->interest_rate ?? 0) / 100 / 12;
                            $monthlyPayment = $loan->total_monthly_repayment ?? 0;
                            $monthlyInsurance = $loan->monthly_insurance ?? 0;

                            // starting balance: take principal_amount OR the balance at time 0
                            $startingBalance = $loan->principal_amount ?? $loan->balance ?? 0;

                            // get ordered repayments by repayment_number
                            $reps = Repayments::where('loan_id', $loan->loan_id)
                                ->orderBy('repayment_number', 'asc')
                                ->orderBy('receipt_date', 'asc')
                                ->orderBy('id', 'asc')
                                ->get();

                            foreach ($reps as $rep) {
                                // opening_balance is current starting balance
                                $interestDue = round($startingBalance * $rate, 2);
                                // scheduled principal (from amortization)
                                $scheduledPrincipal = max(0, round($monthlyPayment - $interestDue, 2));

                                // use actual paid values to compute closing
                                $paidPrincipal = $rep->paid_principal ?? 0;
                                $paidInterest  = $rep->paid_interest ?? 0;
                                $paidInsurance = $rep->insurance_paid ?? 0;

                                $opening = $startingBalance;
                                $closing = max(0, $opening - $paidPrincipal);

                                // update repayment record if balances differ
                                $rep->opening_balance = $opening;
                                $rep->closing_balance = $closing;
                                $rep->monthly_repayment_balance = $monthlyPayment;
                                $rep->sanlam = $monthlyInsurance;
                                $rep->save();

                                // next opening
                                $startingBalance = $closing;
                            }

                            // finally update loans.balance to last closing
                            $loan->balance = $startingBalance;
                            $loan->save();
                        }

                        // Move the importCsv method here (inside the class)
                        public function importCsv(\Illuminate\Http\UploadedFile $file)
                        {
                            // initialize counters
                            $created = 0;
                            $skipped = 0;
                            $invalid = 0;

                            $path = $file->getRealPath();
                            $contents = file_get_contents($path);
                            $lines = array_filter(array_map('trim', explode(PHP_EOL, $contents)), fn($l) => $l !== '');

                            if (empty($lines)) {
                                \Illuminate\Support\Facades\Log::warning('CSV import: empty file', ['path' => $path]);
                                return;
                            }

                            // normalize headers
                            $rawHeader = str_getcsv(array_shift($lines));
                            $header = array_map(fn($h) => strtolower(trim($h, " \t\n\r\0\x0B\"")), $rawHeader);

                            // map each line to associative row
                            foreach ($lines as $index => $line) {
                                $cols = str_getcsv($line);
                                // skip if completely empty
                                if (empty(array_filter($cols, fn($c) => trim($c) !== ''))) {
                                    continue;
                                }

                                // combine header + row (safe guard if column count mismatch)
                                $row = [];
                                foreach ($header as $i => $key) {
                                    $row[$key] = $cols[$i] ?? null;
                                }

                                // normalize keys known to vary
                                // amount may be " amount " in CSV -> header normalized to "amount"
                                $amountRaw = $row['amount'] ?? $row[' amount'] ?? null;
                                $amount = null;
                                if ($amountRaw !== null) {
                                    $clean = preg_replace('/[^\d\.\-]/', '', $amountRaw); // remove commas, spaces, currency
                                    if (is_numeric($clean)) {
                                        $amount = (float) $clean;
                                    }
                                }

                                // receipt date normalization
                                $dateRaw = $row['receipt_date'] ?? $row['receipt date'] ?? $row['receiptdate'] ?? null;
                                $receiptDate = null;
                                if ($dateRaw) {
                                    // Try strict day/month formats before month/day formats.
                                    $formats = ['j/n/Y', 'j/n/y', 'd/m/Y', 'd/m/y', 'Y-m-d', 'n/j/Y', 'n/j/y', 'm/d/Y', 'm/d/y'];
                                    foreach ($formats as $fmt) {
                                        $parsed = static::parseDateWithFormat($fmt, trim($dateRaw));
                                        if ($parsed) {
                                            $receiptDate = $parsed;
                                            break;
                                        }
                                    }
                                    if ($receiptDate === null) {
                                        try {
                                            $receiptDate = Carbon::parse(trim($dateRaw))->toDateString();
                                        } catch (\Exception $e) {
                                            // invalid date
                                        }
                                    }
                                }

                                // basic validation
                                $errors = [];
                                if (empty($row['employee_no'])) $errors[] = 'Missing employee_no';
                                if ($amount === null) $errors[] = 'Invalid amount';
                                if ($receiptDate === null) $errors[] = 'Invalid receipt_date';

                                if (! empty($errors)) {
                                    InvalidRepayment::create([
                                        'csv_file' => $file->getClientOriginalName(),
                                        'row_number' => $index + 2, // header + 1-based
                                        'employee_no' => $row['employee_no'] ?? null,
                                        'name' => $row['name'] ?? null,
                                        'nrc' => $row['nrc'] ?? null,
                                        'amount_raw' => $amountRaw,
                                        'amount' => $amount,
                                        'receipt_date_raw' => $dateRaw,
                                        'receipt_date' => $receiptDate,
                                        'errors' => implode('; ', $errors),
                                        'status' => 'pending',
                                    ]);
                                    $invalid++;
                                    Log::info('Invalid repayment saved', ['row' => $index + 2, 'errors' => $errors]);
                                    continue;
                                }

                                // continue with your existing processing for valid rows:
                                // - find loan(s)
                                // - compute repayment_number using employer rules (GRZ/CNMC)
                                // - allocate to month due (insurance -> interest -> principal)
                                // - upsert/merge repayments by loan_id + repayment_number
                                // - recompute ledger from affected repayment_number onward
                                // - update loans.balance
                                //
                                // On failures while processing, persist to InvalidRepayment with error message
                                //
                                // for now increment created as placeholder
                                $created++;
                            }

                            Log::info('CSV import summary', ['file' => $file->getClientOriginalName(), 'created'=> $created, 'invalid'=> $invalid, 'skipped'=> $skipped]);
    }

    private static function importDatabaseFormatCsv(string $path, ?string $fileKey = null): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $invalid = 0;
        $affectedLoanIds = [];

        $file = new \SplFileObject($path);
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
        $file->setCsvControl(',');
        $file->rewind();

        $rawHeader = $file->fgetcsv();
        if (! is_array($rawHeader) || count(array_filter($rawHeader, fn ($h) => trim((string) $h) !== '')) === 0) {
            Notification::make()->warning()->title('CSV header not found')->send();
            return [$created, $updated, $skipped, 1];
        }

        $header = array_map(fn ($h) => static::normalizeCsvHeader($h), $rawHeader);
        $rowNumber = 1;

        while (! $file->eof()) {
            $row = $file->fgetcsv();
            $rowNumber++;

            if ($row === false || $row === null) {
                continue;
            }

            if (count(array_filter($row, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }

            $cols = count($row) < count($header) ? array_pad($row, count($header), null) : array_slice($row, 0, count($header));
            $assoc = array_combine($header, $cols);

            $normalized = static::normalizeDatabaseRepaymentRow($assoc);
            $errors = static::validateDatabaseRepaymentRow($normalized);

            if (! empty($errors)) {
                InvalidRepayment::create([
                    'csv_file' => $fileKey ?? basename($path),
                    'row_number' => $rowNumber,
                    'employee_no' => $normalized['employee_no'] ?? null,
                    'amount_raw' => $assoc['receipt_amount'] ?? null,
                    'amount' => $normalized['receipt_amount'] ?? null,
                    'receipt_date_raw' => $assoc['receipt_date'] ?? null,
                    'receipt_date' => $normalized['receipt_date'] ?? null,
                    'errors' => implode('; ', $errors),
                    'status' => 'pending',
                ]);
                $invalid++;
                continue;
            }

            $existing = Repayments::query()
                ->where('loan_id', $normalized['loan_id'])
                ->where('employee_no', $normalized['employee_no'])
                ->where('repayment_number', $normalized['repayment_number'])
                ->whereDate('receipt_date', $normalized['receipt_date'])
                ->first();

            unset($normalized['_loan_exists']);

            if ($existing) {
                $existing->fill($normalized);
                $existing->save();
                $affectedLoanIds[$existing->loan_id] = true;
                $updated++;
                continue;
            }

            $repayment = Repayments::create($normalized);
            $affectedLoanIds[$repayment->loan_id] = true;
            $created++;
        }

        foreach (array_keys($affectedLoanIds) as $loanId) {
            $loan = Loan::query()->where('loan_id', $loanId)->first();

            if ($loan) {
                app(\App\Services\RepaymentImportService::class)->recomputeLoanLedger($loan);
            }
        }

        Log::info('DB-format repayment CSV import summary', [
            'file' => $fileKey ?? basename($path),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'invalid' => $invalid,
        ]);

        return [$created, $updated, $skipped, $invalid];
    }

    private static function recordSkippedRepayment(array $data): void
    {
        SkippedRepayment::create([
            'csv_file' => $data['csv_file'] ?? null,
            'row_number' => $data['row_number'] ?? null,
            'import_type' => $data['import_type'] ?? null,
            'reason' => $data['reason'] ?? 'Skipped without reason',
            'loan_id' => $data['loan_id'] ?? null,
            'loan_number' => $data['loan_number'] ?? null,
            'employee_no' => $data['employee_no'] ?? null,
            'batch_no' => $data['batch_no'] ?? null,
            'repayment_number' => $data['repayment_number'] ?? null,
            'receipt_date' => $data['receipt_date'] ?? null,
            'receipt_amount' => $data['receipt_amount'] ?? null,
            'reference_number' => $data['reference_number'] ?? null,
            'matched_repayment_id' => $data['matched_repayment_id'] ?? null,
            'source_payload' => $data['source_payload'] ?? null,
        ]);
    }

    private static function normalizeDatabaseRepaymentRow(array $row): array
    {
        $loanId = static::cleanString($row['loan_id'] ?? null);
        $loan = null;

        if ($loanId !== null) {
            $loan = Loan::where('loan_id', $loanId)
                ->orWhere('loan_number', $loanId)
                ->first();
        }

        $resolvedLoanId = $loan?->loan_id ?? $loanId;

        $receiptDate = static::parseFlexibleDate($row['receipt_date'] ?? null);
        $loanIssueDate = static::parseFlexibleDate($row['loan_issue_date'] ?? null);

        $receiptAmount = static::parseFlexibleNumber($row['receipt_amount'] ?? null);
        $paidPrincipal = static::parseFlexibleNumber($row['paid_principal'] ?? null);
        $paidInterest = static::parseFlexibleNumber($row['paid_interest'] ?? null);
        $insuranceCharge = static::parseFlexibleNumber($row['insurance_charge'] ?? null);
        $openingBalance = static::parseFlexibleNumber($row['opening_balance'] ?? null);
        $closingBalance = static::parseFlexibleNumber($row['closing_balance'] ?? null);
        $monthlyRepaymentBalance = static::parseFlexibleNumber($row['monthly_repayment_balance'] ?? null);
        $monthlyInstallmentBalance = static::parseFlexibleNumber($row['monthly_installment_balance'] ?? null);

        if ($openingBalance === null) {
            $openingBalance = $loan ? (float) ($loan->balance ?? $loan->principal_amount ?? 0) : 0.0;
        }

        if ($closingBalance === null) {
            $closingBalance = max(0, round((float) $openingBalance - (float) ($paidPrincipal ?? 0), 2));
        }

        if ($monthlyRepaymentBalance === null) {
            $monthlyRepaymentBalance = $monthlyInstallmentBalance
                ?? ($receiptAmount !== null ? (float) $receiptAmount : null)
                ?? ($loan ? (float) ($loan->total_monthly_repayment ?? 0) : 0.0);
        }

        if ($monthlyInstallmentBalance === null) {
            $monthlyInstallmentBalance = $monthlyRepaymentBalance;
        }

        $uploadedRepaymentNumber = static::parseFlexibleInteger($row['repayment_number'] ?? null);
        $repaymentNumber = $loan
            ? app(RepaymentNumberResolver::class)->resolve($loan, $receiptDate, $uploadedRepaymentNumber)
            : $uploadedRepaymentNumber;

        if ($loan && $uploadedRepaymentNumber !== null && $repaymentNumber !== $uploadedRepaymentNumber) {
            Log::info('Corrected uploaded repayment_number', [
                'loan_id' => $loan->loan_id,
                'receipt_date' => $receiptDate,
                'uploaded_repayment_number' => $uploadedRepaymentNumber,
                'resolved_repayment_number' => $repaymentNumber,
            ]);
        }

        return [
            'loan_id' => $resolvedLoanId,
            'employee_no' => static::cleanString($row['employee_no'] ?? null),
            'nrc' => static::cleanString($row['nrc'] ?? null),
            'client_id' => static::cleanString($row['client_id'] ?? null),
            'batch_no' => static::cleanString($row['batch_no'] ?? null),
            'receipt_date' => $receiptDate,
            'receipt_amount' => $receiptAmount,
            'paid_principal' => $paidPrincipal,
            'paid_interest' => $paidInterest,
            'insurance_paid' => static::parseFlexibleNumber($row['insurance_paid'] ?? $row['insurance_charge'] ?? null) ?? 0,
            'monthly_installment_balance' => $monthlyInstallmentBalance,
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'repayment_number' => $repaymentNumber,
            'monthly_repayment_balance' => $monthlyRepaymentBalance,
            'payment_status' => static::cleanString($row['payment_status'] ?? null),
            'extra_payments' => static::parseFlexibleNumber($row['extra_payments'] ?? null) ?? 0,
            'refund' => static::parseFlexibleNumber($row['refund'] ?? null) ?? 0,
            'insurance_charge' => $insuranceCharge ?? 0,
            'zed_fin' => static::parseFlexibleNumber($row['zed_fin'] ?? null) ?? 0,
            'sanlam' => static::parseFlexibleNumber($row['sanlam'] ?? null) ?? 0,
            'interest_rate' => static::parseFlexibleNumber($row['interest_rate'] ?? null),
            'loan_amount' => static::parseFlexibleNumber($row['loan_amount'] ?? null),
            'term' => static::parseFlexibleInteger($row['term'] ?? null),
            'loan_issue_date' => $loanIssueDate,
            'employer' => static::cleanString($row['employer'] ?? null) ?? ($loan?->borrower?->employer ?? null),
            'status' => static::cleanString($row['status'] ?? null),
            'balance' => $closingBalance,
            'payments' => $receiptAmount ?? 0,
            'principal' => $paidPrincipal ?? 0,
            'payments_method' => static::cleanString($row['payment_type'] ?? $row['payments_method'] ?? null) ?? 'Payroll',
            'reference_number' => static::cleanString($row['reference_number'] ?? null) ?? uniqid('csv_', false),
            'loan_number' => static::cleanString($row['loan_number'] ?? null) ?? ($loan?->loan_number ?? $loanId),
            'payment_date' => $receiptDate,
            '_loan_exists' => $loan !== null,
        ];
    }

    private static function validateDatabaseRepaymentRow(array $row): array
    {
        $errors = [];

        if (empty($row['loan_id'])) {
            $errors[] = 'Missing loan_id';
        } elseif (($row['_loan_exists'] ?? false) !== true) {
            $errors[] = 'Referenced loan_id does not exist in loans table';
        }
        if (empty($row['employee_no'])) {
            $errors[] = 'Missing employee_no';
        }
        if ($row['receipt_date'] === null) {
            $errors[] = 'Invalid receipt_date';
        }
        if ($row['receipt_amount'] === null) {
            $errors[] = 'Invalid receipt_amount';
        }
        if ($row['repayment_number'] === null) {
            $errors[] = 'Invalid repayment_number';
        }
        return $errors;
    }

    private static function normalizeCsvHeader(?string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', (string) $value);
        $value = trim($value);
        $value = str_replace(['-', ' '], '_', $value);

        return Str::snake($value);
    }

    private static function parseFlexibleDate(?string $value): ?string
    {
        $value = static::cleanString($value);
        if ($value === null) {
            return null;
        }

        // Handle Excel serial dates such as 45688 or 45688.00.
        if (is_numeric($value)) {
            try {
                $serial = (float) $value;
                if ($serial > 0) {
                    return Carbon::create(1899, 12, 30)->addDays((int) floor($serial))->toDateString();
                }
            } catch (\Exception $e) {
            }
        }

        foreach (['j/n/Y', 'j/n/y', 'd/m/Y', 'd/m/y', 'd-m-Y', 'd-m-y', 'Y-m-d', 'n/j/Y', 'n/j/y', 'm/d/Y', 'm/d/y'] as $format) {
            $parsed = static::parseDateWithFormat($format, $value);
            if ($parsed) {
                return $parsed;
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Exception $e) {
            return null;
        }
    }

    private static function parseDateWithFormat(string $format, string $value): ?string
    {
        try {
            $date = Carbon::createFromFormat($format, $value);
            $errors = Carbon::getLastErrors();

            if ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) {
                return null;
            }

            return $date->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function parseFlexibleNumber($value): ?float
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $clean = preg_replace('/[^\d.\-]/', '', $value);
        if ($clean === '' || ! is_numeric($clean)) {
            return null;
        }

        return (float) $clean;
    }

    private static function parseFlexibleInteger($value): ?int
    {
        $number = static::parseFlexibleNumber($value);

        return $number === null ? null : (int) $number;
    }

    private static function cleanString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private static function buildPayrollImportReference(array $row, Loan $loan, int $repaymentNumber, array $allocation, string $segment): string
    {
        $fingerprint = [
            'segment' => $segment,
            'employee_no' => (string) ($row['employee_no'] ?? ''),
            'receipt_date' => (string) ($row['receipt_date'] ?? ''),
            'source_amount' => round((float) ($row['amount'] ?? 0), 2),
            'loan_id' => (string) $loan->loan_id,
            'repayment_number' => $repaymentNumber,
            'receipt_amount' => round((float) ($allocation['receipt_amount'] ?? 0), 2),
            'paid_principal' => round((float) ($allocation['paid_principal'] ?? 0), 2),
            'paid_interest' => round((float) ($allocation['paid_interest'] ?? 0), 2),
            'insurance_paid' => round((float) ($allocation['insurance_paid'] ?? 0), 2),
        ];

        return 'payroll_' . sha1(json_encode($fingerprint));
    }

    private static function exportFilterSchema(): array
    {
        return [
            Forms\Components\Select::make('loan_id')
                ->label('Loan ID')
                ->options(static::loanExportOptions())
                ->default('all')
                ->searchable(),
            Forms\Components\Select::make('employee_no')
                ->label('Employee No')
                ->options(static::employeeExportOptions())
                ->default('all')
                ->searchable(),
            Forms\Components\Select::make('receipt_month')
                ->label('Month')
                ->options(static::monthOptions())
                ->searchable()
                ->required(),
            Forms\Components\Select::make('receipt_year')
                ->label('Year')
                ->options(static::receiptYearOptions())
                ->searchable()
                ->required(),
        ];
    }

    private static function exportPeriodSelectionIsComplete(array $data): bool
    {
        return ! empty($data['receipt_month']) && ! empty($data['receipt_year']);
    }

    private static function applyReceiptPeriodFilters($query, array $filters)
    {
        if (! empty($filters['receipt_year']) && ! empty($filters['receipt_month'])) {
            try {
                $month = Carbon::create((int) $filters['receipt_year'], (int) $filters['receipt_month'], 1);

                $query->whereDate('receipt_date', '>=', $month->copy()->startOfMonth()->toDateString())
                    ->whereDate('receipt_date', '<=', $month->copy()->endOfMonth()->toDateString());
            } catch (\Throwable $e) {
            }
        } elseif (! empty($filters['receipt_month']) && preg_match('/^\d{4}-\d{2}$/', (string) $filters['receipt_month'])) {
            try {
                $month = Carbon::parse($filters['receipt_month'] . '-01');

                $query->whereDate('receipt_date', '>=', $month->copy()->startOfMonth()->toDateString())
                    ->whereDate('receipt_date', '<=', $month->copy()->endOfMonth()->toDateString());
            } catch (\Throwable $e) {
            }
        } elseif (! empty($filters['receipt_year'])) {
            $query->whereYear('receipt_date', (int) $filters['receipt_year']);
        }

        return $query
            ->when(! empty($filters['receipt_date_from']), fn ($query) => $query->whereDate('receipt_date', '>=', $filters['receipt_date_from']))
            ->when(! empty($filters['receipt_date_to']), fn ($query) => $query->whereDate('receipt_date', '<=', $filters['receipt_date_to']));
    }

    private static function monthOptions(): array
    {
        return collect(range(1, 12))
            ->mapWithKeys(fn (int $month): array => [
                (string) $month => Carbon::create(null, $month, 1)->format('F'),
            ])
            ->all();
    }

    private static function receiptYearOptions(): array
    {
        $years = Repayments::query()
            ->whereNotNull('receipt_date')
            ->selectRaw('YEAR(receipt_date) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->filter()
            ->mapWithKeys(fn ($year): array => [(string) $year => (string) $year])
            ->all();

        if ($years !== []) {
            return $years;
        }

        $currentYear = (int) now()->year;

        return collect(range($currentYear, $currentYear - 5))
            ->mapWithKeys(fn (int $year): array => [(string) $year => (string) $year])
            ->all();
    }

    private static function loanExportOptions(): array
    {
        return ['all' => 'All Loans'] + Repayments::query()
            ->whereNotNull('loan_id')
            ->where('loan_id', '!=', '')
            ->distinct()
            ->orderBy('loan_id')
            ->pluck('loan_id', 'loan_id')
            ->all();
    }

    private static function employeeExportOptions(): array
    {
        return ['all' => 'All Employees'] + Repayments::query()
            ->whereNotNull('employee_no')
            ->where('employee_no', '!=', '')
            ->distinct()
            ->orderBy('employee_no')
            ->pluck('employee_no', 'employee_no')
            ->all();
    }

    private static function repaymentExportQuery(array $filters)
    {
        $query = Repayments::query()
            ->when(! empty($filters['loan_id']) && $filters['loan_id'] !== 'all', function ($query) use ($filters) {
                $query->where(function ($subQuery) use ($filters) {
                    $subQuery->where('loan_number', $filters['loan_id'])
                        ->orWhere('loan_id', $filters['loan_id']);
                });
            })
            ->when(! empty($filters['employee_no']) && $filters['employee_no'] !== 'all', fn ($query) => $query->where('employee_no', $filters['employee_no']));

        return static::applyReceiptPeriodFilters($query, $filters)
            ->orderByDesc('receipt_date')
            ->orderBy('loan_number');
    }

    private static function mapRepaymentExportRow(Repayments $row): array
    {
        return [
            'Loan ID' => $row->loan_number,
            'Employee No' => $row->employee_no,
            'Loan Release Date' => optional($row->loan_issue_date)->toDateString(),
            'Employer' => $row->employer,
            'Receipt Date' => optional($row->receipt_date)->toDateString(),
            'Receipt Amount' => (float) ($row->receipt_amount ?? 0),
            'Payment Type' => $row->payments_method,
            'Principal' => (float) ($row->paid_principal ?? 0),
            'Interest' => (float) ($row->paid_interest ?? 0),
            'Insurance' => (float) ($row->insurance_paid ?? 0),
            'Balance' => (float) ($row->closing_balance ?? 0),
            'Status' => $row->payment_status,
            'Reference Number' => $row->reference_number,
        ];
    }

    private static function selectLoanForRepayment(string $employeeNo, float $amount, string $receiptDate)
    {
        $candidates = \App\Models\Loan::where('employee_no', $employeeNo)
            ->whereIn('loan_status', ['approved', 'partially_paid'])
            ->where('balance', '>', 0)
            ->orderBy('loan_release_date', 'asc')
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }
        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        // Try to match by monthly expected amount (monthly repayment + insurance)
        $best = null;
        $bestScore = PHP_FLOAT_MAX;
        foreach ($candidates as $loan) {
            $expected = ($loan->total_monthly_repayment ?? 0) + ($loan->monthly_insurance ?? 0);
            // Avoid division by zero; use absolute diff
            $score = abs($amount - $expected);

            // prefer lower diff
            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $loan;
            }
        }

        // If closest match is reasonably close, choose it (within 25% or fixed tolerance)
        if ($best && (($bestScore <= 0.25 * (($best->total_monthly_repayment ?? 0) + ($best->monthly_insurance ?? 0))) || $bestScore <= 50.0)) {
            return $best;
        }

        // Fall back: attempt allocation simulation and pick loan with smallest unallocated
        $allocSvc = app(\App\Services\RepaymentAllocationService::class);
        $bestLoan = null;
        $bestUnallocated = PHP_FLOAT_MAX;
        foreach ($candidates as $loan) {
            $repaymentNumber = app(\App\Services\RepaymentScheduleService::class)
                ->computeRepaymentNumber($loan, \Carbon\Carbon::parse($receiptDate));
            $repaymentNumber = app(\App\Services\RepaymentAllocationService::class)
                ->resolveRepaymentNumberForOutstandingInterest($loan, $repaymentNumber);
            $alloc = $allocSvc->allocateForMonth($loan, $amount, $repaymentNumber, \Carbon\Carbon::parse($receiptDate));
            if (($alloc['unallocated'] ?? 0) < $bestUnallocated) {
                $bestUnallocated = $alloc['unallocated'] ?? 0;
                $bestLoan = $loan;
            }
        }

        if ($bestLoan && $bestUnallocated <= 0.01) {
            return $bestLoan;
        }

        // ambiguous -> return null so caller creates invalid_repayment for manual correction
        return null;
    }
}
