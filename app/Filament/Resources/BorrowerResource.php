<?php

namespace App\Filament\Resources;

use App\Filament\Pages\CustomerHistory;
use App\Filament\Resources\BorrowerResource\Pages;
use App\Models\Borrower;
use App\Models\BorrowerFiles;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class BorrowerResource extends Resource
{
    protected static ?string $model = Borrower::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Borrowers';

    protected static ?string $navigationGroup = 'Customers';



    //     public static function infolist(Infolist $infolist): Infolist
    //     {

    // // Fetch existing files associated with the borrower (assuming you have a $borrower variable available)
    // $borrowerFiles = $borrower->getMedia('attachments'); // Adjust the collection name as per your setup

    // $existingFilesInfo = $borrowerFiles->map(function (Media $file) {
    //     return [
    //         'name' => $file->file_name,
    //         'url' => $file->getFullUrl(),
    //     ];
    // });



    //         return $infolist
    //             ->schema([
    //                 Section::make('Personal Details')
    //                     ->description('Borrower Personal Details')
    //                     ->schema([
    //                         TextEntry::make('first_name'),
    //                         TextEntry::make('last_name'),
    //                         TextEntry::make('gender'),
    //                         TextEntry::make('dob'),
    //                         TextEntry::make('occupation'),
    //                         TextEntry::make('identification'),
    //                         TextEntry::make('mobile'),
    //                         TextEntry::make('email'),
    //                         TextEntry::make('address'),
    //                         TextEntry::make('city'),
    //                         TextEntry::make('province'),
    //                         TextEntry::make('zipcode'),
    //                     ])
    //                     ->columns(2),
    //                 Section::make('Next of Kin Details')
    //                     ->description('Borrower Next Of Kin Details')
    //                     ->schema([
    //                         TextEntry::make('next_of_kin_first_name'),
    //                         TextEntry::make('next_of_kin_last_name'),
    //                         TextEntry::make('phone_next_of_kin'),
    //                         TextEntry::make('address_next_of_kin'),
    //                         TextEntry::make('relationship_next_of_kin'),
    //                     ])
    //                     ->columns(2),
    //                     Section::make('Bank Details')
    //                     ->description('Borrower Bank Details')
    //                     ->schema([
    //                         TextEntry::make('bank_name'),
    //                         TextEntry::make('bank_branch'),
    //                         TextEntry::make('bank_sort_code'),
    //                         TextEntry::make('bank_account_number'),
    //                         TextEntry::make('bank_account_name'),
    //                         TextEntry::make('mobile_money_name'),
    //                         TextEntry::make('mobile_money_number'),
    //                     ])
    //                     ->columns(2),

    //                     Section::make('Borrower Files')
    //                     ->description('Borrower Attached Files')
    //                     ->schema([
    //                         TextEntry::make('existing_files')
    //                             ->label('Existing Files')
    //                             ->value($existingFilesInfo->implode('<br>'))
    //                             ->multiline() // Adjust if needed for multiline display
    //                     ])
    //                     ->columns(2),

    //             ]);
    //     }
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }





    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Personal Details')
                    ->description('Borrower personal details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('first_name')
                            ->label('First Name')
                            ->prefixIcon('heroicon-o-user')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('last_name')
                            ->label('Last Name')
                            ->prefixIcon('heroicon-o-user')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('other_names')
                            ->label('Other Names')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('customer_id')
                            ->label('Customer ID')
                            ->maxLength(255),
                        Forms\Components\Select::make('gender')
                            ->label('Gender')
                            ->prefixIcon('heroicon-o-users')
                            ->options([
                                'male' => 'Male',
                                'female' => 'Female',
                            ])
                            ->required(),
                        Forms\Components\DatePicker::make('dob')
                            ->label('Date of Birth')
                            ->prefixIcon('heroicon-o-calendar')
                            ->required()
                            ->native(false)
                            ->maxDate(now())
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set): void {
                                if (! $state) {
                                    $set('age', null);
                                    return;
                                }

                                $set('age', Carbon::parse($state)->age);
                            }),
                        Forms\Components\TextInput::make('age')
                            ->label('Age')
                            ->numeric(),
                        Forms\Components\TextInput::make('title')
                            ->label('Title')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('occupation')
                            ->label('Occupation')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('identification')
                            ->label('National ID')
                            ->prefixIcon('heroicon-o-identification')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('mobile')
                            ->label('Phone Number')
                            ->prefixIcon('heroicon-o-phone')
                            ->tel()
                            ->required(),
                        Forms\Components\TextInput::make('email')
                            ->label('Email Address')
                            ->prefixIcon('heroicon-o-envelope')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('address')
                            ->label('Address')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('city')
                            ->label('City')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('province')
                            ->label('Province')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('zipcode')
                            ->label('Zipcode')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('nationality')
                            ->label('Nationality')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('marital_status')
                            ->label('Marital Status')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('mine_number')
                            ->label('Mine Number')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('employer')
                            ->label('Employer')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('employer_number')
                            ->label('Employer Number')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('employer_address')
                            ->label('Employer Address')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('employer_position')
                            ->label('Employer Position')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('cycle')
                            ->label('Cycle')
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('retirement_date')
                            ->label('Retirement Date'),
                        Forms\Components\DatePicker::make('term_date')
                            ->label('Term Date'),
                        Forms\Components\DatePicker::make('start_contract')
                            ->label('Start Contract'),
                        Forms\Components\DatePicker::make('end_contract')
                            ->label('End Contract'),
                        Forms\Components\TextInput::make('contract_type')
                            ->label('Contract Type')
                            ->maxLength(255),
                    ]),
                Section::make('Next of Kin Details')
                    ->description('Borrower next of kin details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('next_of_kin_first_name')
                            ->label('Next of Kin First Name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('next_of_kin_last_name')
                            ->label('Next of Kin Last Name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone_next_of_kin')
                            ->label('Phone Next of Kin')
                            ->tel(),
                        Forms\Components\TextInput::make('next_of_kin_nrc')
                            ->label('Next of Kin NRC')
                            ->maxLength(255),
                        Forms\Components\Select::make('relationship_next_of_kin')
                            ->label('Relationship to Next of Kin')
                            ->options([
                                'mother' => 'Mother',
                                'father' => 'Father',
                                'aunty' => 'Aunty',
                                'uncle' => 'Uncle',
                                'cousin' => 'Cousin',
                                'wife' => 'Wife',
                                'husband' => 'Husband',
                                'brother' => 'Brother',
                                'sister' => 'Sister',
                            ]),
                        Forms\Components\Textarea::make('address_next_of_kin')
                            ->label('Address Next of Kin')
                            ->maxLength(255),
                    ]),
                Section::make('Bank Details')
                    ->description('Borrower bank details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('bank_name')
                            ->label('Bank Name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('bank_branch')
                            ->label('Bank Branch')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('bank_sort_code')
                            ->label('Bank Sort Code')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('bank_account_number')
                            ->label('Bank Account Number')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('bank_account_type')
                            ->label('Bank Account Type')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('bank_account_name')
                            ->label('Bank Account Name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('mobile_money_name')
                            ->label('Mobile Money Name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('mobile_money_number')
                            ->label('Mobile Money Number')
                            ->tel(),
                    ]),
                Section::make('Borrower Files')
                    ->description('Borrower attached files')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('attachments')
                            ->label('Attachments')
                            ->collection('attachments')
                            ->disk('public')
                            ->visibility('public')
                            ->multiple()
                            ->minFiles(1)
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
                            ->helperText('PDF, JPG, PNG, and ZIP files up to 100 MB each are allowed.')
                            ->openable()
                            ->downloadable()
                            ->reorderable()
                            ->required(),
                    ]),
            ]);
    }

    public static function mutateBorrowerData(array $data): array
    {
        $data['full_name'] = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));

        if (! empty($data['mobile'])) {
            $data['full_name'] .= ' - ' . $data['mobile'];
        }

        $data['added_by'] = $data['added_by'] ?? Auth::id();

        return $data;
    }

    public static function syncAttachmentReferences(Borrower $borrower): void
    {
        $mediaItems = $borrower->getMedia('attachments');

        BorrowerFiles::query()->where('borrower_id', $borrower->id)->delete();

        foreach ($mediaItems as $media) {
            $path = method_exists($media, 'getPathRelativeToRoot')
                ? $media->getPathRelativeToRoot()
                : ltrim((string) parse_url($media->getUrl(), PHP_URL_PATH), '/');

            BorrowerFiles::query()->create([
                'borrower_id' => $borrower->id,
                'file_path' => $path ?: $media->file_name,
            ]);
        }

        $borrower->forceFill([
            'attachment' => $mediaItems->pluck('file_name')->implode(', '),
        ])->saveQuietly();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer_id')->label('Customer ID')->searchable(),
                Tables\Columns\TextColumn::make('first_name')->label('First Name')->searchable(),
                Tables\Columns\TextColumn::make('last_name')->label('Last Name')->searchable(),
                Tables\Columns\TextColumn::make('gender')->label('Gender')->searchable(),
                Tables\Columns\TextColumn::make('employer')->label('Employer')->searchable(),
                Tables\Columns\TextColumn::make('occupation')->label('Occupation')->searchable(),
                Tables\Columns\TextColumn::make('identification')->label('Identification')->searchable(),
                Tables\Columns\TextColumn::make('mobile')->label('Mobile Number')->searchable(),
                Tables\Columns\TextColumn::make('mine_number')->label('Mine Number')->searchable(),
                Tables\Columns\TextColumn::make('created_by.name')->label('Created By')->searchable(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('importBorrowersCsv')
                    ->label('Bulk Import CSV')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        FileUpload::make('csv_file')
                            ->label('Borrower CSV File')
                            ->disk('local')
                            ->directory('imports/borrowers')
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
                        $unchanged = 0;
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

                            if (! static::csvValue($csv, ['customer_id', 'client_id', 'customer_number'])) {
                                $skipped++;
                                continue;
                            }

                            try {
                                $result = static::upsertBorrowerFromCsvRow($csv);
                            } catch (\InvalidArgumentException $exception) {
                                $skipped++;
                                $skipReasons[] = "Row {$rowNumber}: {$exception->getMessage()}";
                                continue;
                            } catch (\Throwable $exception) {
                                $skipped++;
                                $skipReasons[] = "Row {$rowNumber}: {$exception->getMessage()}";
                                continue;
                            }

                            if ($result === 'created') {
                                $created++;
                            } elseif ($result === 'updated') {
                                $updated++;
                            } elseif ($result === 'unchanged') {
                                $unchanged++;
                            } else {
                                $skipped++;
                            }
                        }

                        $message = "Created: {$created} | Updated: {$updated} | Unchanged: {$unchanged} | Skipped: {$skipped}";

                        if ($skipReasons !== []) {
                            $message .= "\n" . implode("\n", array_slice($skipReasons, 0, 5));
                        }

                        Notification::make()
                            ->title('Borrower import complete')
                            ->body($message)
                            ->success()
                            ->send();
                    }),
            ])

            ->filters([
                Tables\Filters\SelectFilter::make('gender')
                    ->options([
                        'male' => 'Male',
                        'female' => 'Female',

                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('history')
                    ->label('History')
                    ->icon('heroicon-o-clock')
                    ->url(fn (Borrower $record): string => CustomerHistory::getUrl(['borrower' => $record->id]))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    ExportBulkAction::make()
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
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
            'index' => Pages\ListBorrowers::route('/'),
            'create' => Pages\CreateBorrower::route('/create'),
            'view' => Pages\ViewBorrower::route('/{record}'),
            'edit' => Pages\EditBorrower::route('/{record}/edit'),
        ];
    }

    protected static function upsertBorrowerFromCsvRow(array $row): string
    {
        $customerId = static::csvValue($row, ['customer_id', 'client_id', 'customer_number']);

        if (! $customerId) {
            return 'skipped';
        }

        $lastName = static::csvValue($row, ['last_name']);
        $otherNames = static::csvValue($row, ['other_names', 'other_name']);
        $phone = static::csvValue($row, ['phone_number', 'mobile']);
        $address = static::csvValue($row, ['address']);
        $bankBranchCode = static::csvValue($row, ['branch_code', 'bank_branch']);
        $nextOfKin = static::csvValue($row, ['next_of_kin']);
        $nextOfKinNames = static::splitName($nextOfKin);
        $displayName = trim(collect([$otherNames, $lastName])->filter()->implode(' '));

        $payload = [
            'customer_id' => $customerId,
            'first_name' => static::csvValue($row, ['first_name']) ?: ($otherNames ?: $lastName ?: $customerId),
            'last_name' => $lastName ?: ($otherNames ?: $customerId),
            'other_names' => $otherNames,
            'gender' => static::normalizeGender(static::csvValue($row, ['gender'])),
            'dob' => static::parseCsvDate(static::csvValue($row, ['dob'])) ?: static::inferDobFromAge(static::parseCsvAge(static::csvValue($row, ['age']))),
            'age' => static::parseCsvAge(static::csvValue($row, ['age'])),
            'occupation' => static::csvValue($row, ['occupation']),
            'identification' => static::csvValue($row, ['nrc_number', 'nrc', 'identification']) ?: $customerId,
            'mobile' => $phone,
            'email' => static::csvValue($row, ['email']),
            'address' => $address ?: 'N/A',
            'city' => static::csvValue($row, ['city']) ?: ($address ?: 'N/A'),
            'province' => static::csvValue($row, ['province']) ?: ($address ?: 'N/A'),
            'zipcode' => static::csvValue($row, ['zipcode']),
            'full_name' => $displayName . ($phone ? " - {$phone}" : ''),
            'added_by' => Auth::id() ?? 1,
            'mine_number' => static::csvValue($row, ['mine_number']),
            'title' => static::csvValue($row, ['title']),
            'retirement_date' => static::parseCsvDate(static::csvValue($row, ['retirement_date'])),
            'term_date' => static::parseCsvDate(static::csvValue($row, ['termdate', 'term_date'])),
            'start_contract' => static::parseCsvDate(static::csvValue($row, ['start_cont', 'startcont', 'start_contract'])),
            'end_contract' => static::parseCsvDate(static::csvValue($row, ['end_cont', 'endcont', 'end_contract'])),
            'contract_type' => static::csvValue($row, ['cont_type', 'conttype', 'contract_type']),
            'marital_status' => static::csvValue($row, ['martial_status', 'marital_status']),
            'nationality' => static::csvValue($row, ['nationality']),
            'employer' => static::csvValue($row, ['employer']),
            'employer_number' => static::csvValue($row, ['employer_number']),
            'employer_address' => static::csvValue($row, ['employer_address']),
            'employer_position' => static::csvValue($row, ['employer_position']),
            'cycle' => static::csvValue($row, ['cycle']),
            'next_of_kin_first_name' => static::csvValue($row, ['next_of_kin_first_name']) ?: $nextOfKinNames['first_name'],
            'next_of_kin_last_name' => static::csvValue($row, ['next_of_kin_last_name']) ?: $nextOfKinNames['last_name'],
            'phone_next_of_kin' => static::csvValue($row, ['nok_phone_number', 'phone_next_of_kin']),
            'address_next_of_kin' => static::csvValue($row, ['next_of_kins_address', 'nok_address', 'address_next_of_kin']),
            'relationship_next_of_kin' => static::csvValue($row, ['relationship_next_of_kin']),
            'next_of_kin_nrc' => static::csvValue($row, ['nok_nrc', 'next_of_kin_nrc']),
            'bank_name' => static::csvValue($row, ['bank', 'bank_name']),
            'bank_branch' => $bankBranchCode,
            'bank_sort_code' => static::csvValue($row, ['bank_sort_code']) ?: $bankBranchCode,
            'bank_account_number' => static::csvValue($row, ['account_number', 'bank_account_number']),
            'bank_account_type' => static::csvValue($row, ['account_type', 'bank_account_type']),
            'bank_account_name' => static::csvValue($row, ['bank_account_name']) ?: ($displayName ?: null),
            'mobile_money_name' => static::csvValue($row, ['mobile_money_name']),
            'mobile_money_number' => static::csvValue($row, ['mobile_money_number']),
        ];

        if ($payload['full_name'] === '') {
            $payload['full_name'] = $customerId;
        }

        if (! $payload['first_name']) {
            $payload['first_name'] = $otherNames ?: $lastName ?: $customerId;
        }

        $borrower = static::resolveExistingBorrower($payload);

        if (! $borrower->exists) {
            $borrower->customer_id = $customerId;
        }

        $wasExisting = $borrower->exists;
        $payload = static::mergeBorrowerPayload($borrower, $payload, ! $wasExisting);

        if ($wasExisting && static::borrowerPayloadMatches($borrower, $payload)) {
            return 'unchanged';
        }

        $borrower->forceFill($payload);
        $borrower->save();

        return $wasExisting ? 'updated' : 'created';
    }

    protected static function resolveExistingBorrower(array $payload): Borrower
    {
        $query = Borrower::query();

        if (! empty($payload['customer_id'])) {
            $existing = (clone $query)->where('customer_id', $payload['customer_id'])->first();
            if ($existing) {
                return $existing;
            }
        }

        if (! empty($payload['identification'])) {
            $existing = (clone $query)->where('identification', $payload['identification'])->first();
            if ($existing) {
                return $existing;
            }
        }

        if (! empty($payload['mobile'])) {
            $existing = (clone $query)->where('mobile', $payload['mobile'])->first();
            if ($existing) {
                return $existing;
            }
        }

        if (! empty($payload['email'])) {
            $existing = (clone $query)->where('email', $payload['email'])->first();
            if ($existing) {
                return $existing;
            }
        }

        return new Borrower();
    }

    protected static function mergeBorrowerPayload(Borrower $borrower, array $payload, bool $isNew): array
    {
        $result = [];

        foreach ($payload as $column => $value) {
            if ($isNew) {
                $result[$column] = static::normalizeImportedValue($column, $value, true);
                continue;
            }

            $normalizedIncoming = static::normalizeImportedValue($column, $value, false);
            $currentValue = $borrower->getAttribute($column);

            $result[$column] = $normalizedIncoming ?? $currentValue;
        }

        if ($isNew) {
            $result['full_name'] = static::cleanString($result['full_name'] ?? null) ?: ($result['customer_id'] ?? null);
            $result['address'] = static::cleanString($result['address'] ?? null) ?: 'N/A';
            $result['city'] = static::cleanString($result['city'] ?? null) ?: ($result['address'] ?? 'N/A');
            $result['province'] = static::cleanString($result['province'] ?? null) ?: ($result['address'] ?? 'N/A');
            $result['identification'] = static::cleanString($result['identification'] ?? null) ?: ($result['customer_id'] ?? null);
            $result['first_name'] = static::cleanString($result['first_name'] ?? null) ?: ($result['customer_id'] ?? null);
            $result['last_name'] = static::cleanString($result['last_name'] ?? null) ?: ($result['customer_id'] ?? null);
        }

        return $result;
    }

    protected static function normalizeImportedValue(string $column, mixed $value, bool $isNew): mixed
    {
        if ($value === null) {
            return $isNew && in_array($column, ['address', 'city', 'province'], true) ? 'N/A' : null;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '') {
            return null;
        }

        if ($value === 'N/A' && ! $isNew && ! in_array($column, ['address', 'city', 'province'], true)) {
            return null;
        }

        return $value;
    }

    protected static function borrowerPayloadMatches(Borrower $borrower, array $payload): bool
    {
        foreach ($payload as $column => $value) {
            $current = $borrower->getAttribute($column);

            if ((string) ($current ?? '') !== (string) ($value ?? '')) {
                return false;
            }
        }

        return true;
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

    protected static function splitName(?string $value): array
    {
        $value = static::cleanString($value);

        if (! $value) {
            return ['first_name' => null, 'last_name' => null];
        }

        $parts = preg_split('/\s+/', $value) ?: [];

        if (count($parts) === 1) {
            return ['first_name' => $parts[0], 'last_name' => null];
        }

        return [
            'first_name' => array_shift($parts),
            'last_name' => trim(implode(' ', $parts)) ?: null,
        ];
    }

    protected static function normalizeGender(mixed $value): string
    {
        $value = strtolower(static::cleanString($value) ?? '');

        return match ($value) {
            'm', 'male' => 'male',
            'f', 'female' => 'female',
            default => '',
        };
    }

    protected static function parseCsvAge(mixed $value): ?int
    {
        $value = static::cleanString($value);

        if (! $value || ! is_numeric($value)) {
            return null;
        }

        return (int) round((float) $value);
    }

    protected static function parseCsvDate(mixed $value): ?string
    {
        $value = static::cleanString($value);

        if (! $value) {
            return null;
        }

        // Normalize separators from CSV/Excel exports like "1959 -12 -31".
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

    protected static function inferDobFromAge(?int $age): ?string
    {
        if ($age === null || $age < 0) {
            return null;
        }

        return now()->subYears($age)->format('Y-m-d');
    }
}
