<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SkippedRepaymentResource\Pages;
use App\Models\SkippedRepayment;
use App\Services\SkippedRepaymentPostingService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;

class SkippedRepaymentResource extends Resource
{
    protected static ?string $model = SkippedRepayment::class;
    protected static ?string $navigationIcon = 'heroicon-o-forward';
    protected static ?string $navigationGroup = 'Repayments';
    protected static ?string $navigationLabel = 'Skipped Repayments';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('csv_file')->disabled(),
            Forms\Components\TextInput::make('row_number')->disabled(),
            Forms\Components\TextInput::make('import_type')->disabled(),
            Forms\Components\Select::make('status')
                ->options([
                    'pending' => 'Pending',
                    'posted' => 'Posted',
                    'ignored' => 'Ignored',
                    'failed' => 'Failed',
                ])
                ->required(),
            Forms\Components\Textarea::make('reason')->columnSpanFull(),
            Forms\Components\TextInput::make('loan_id')
                ->label('Loan ID')
                ->helperText('Use the LMS loan_id or loan_number.'),
            Forms\Components\TextInput::make('employee_no')->label('Employee No'),
            Forms\Components\TextInput::make('batch_no')->label('Batch No'),
            Forms\Components\TextInput::make('repayment_number')->numeric(),
            Forms\Components\DatePicker::make('receipt_date')->required(),
            Forms\Components\TextInput::make('receipt_amount')->numeric()->required(),
            Forms\Components\TextInput::make('reference_number'),
            Forms\Components\TextInput::make('matched_repayment_id')->disabled(),
            Forms\Components\TextInput::make('posted_repayment_id')->disabled(),
            Forms\Components\DateTimePicker::make('posted_at')->disabled(),
            Forms\Components\Textarea::make('post_error')->columnSpanFull()->disabled(),
            Forms\Components\Textarea::make('source_payload')
                ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT))
                ->columnSpanFull()
                ->disabled(),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Skipped At')->dateTime()->sortable(),
                TextColumn::make('csv_file')->label('File')->searchable()->limit(32),
                TextColumn::make('row_number')->label('Row')->sortable(),
                TextColumn::make('import_type')->label('Import')->badge()->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'posted' => 'success',
                        'failed' => 'danger',
                        'ignored' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('reason')->searchable()->limit(80)->wrap(),
                TextColumn::make('loan_id')->label('Loan ID')->searchable(),
                TextColumn::make('employee_no')->label('Employee No')->searchable(),
                TextColumn::make('batch_no')->label('Batch No')->searchable(),
                TextColumn::make('repayment_number')->label('Repayment #')->sortable(),
                TextColumn::make('receipt_date')->date()->sortable(),
                TextColumn::make('receipt_amount')->money('ZMW'),
                TextColumn::make('matched_repayment_id')->label('Matched Repayment')->sortable(),
                TextColumn::make('posted_repayment_id')->label('Posted Repayment')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('import_type')
                    ->options([
                        'payroll_csv' => 'Payroll CSV',
                        'database_csv' => 'Database CSV',
                        'reimport_payroll' => 'Payroll Re-import',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'posted' => 'Posted',
                        'ignored' => 'Ignored',
                        'failed' => 'Failed',
                    ]),
            ])
            ->headerActions([
                Action::make('exportSkipped')
                    ->label('Export Skipped')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {
                        $rows = SkippedRepayment::query()->orderByDesc('created_at')->get();
                        $filename = 'skipped_repayments_' . now()->format('Ymd_His') . '.csv';

                        return response()->streamDownload(function () use ($rows) {
                            $handle = fopen('php://output', 'w');
                            fwrite($handle, "\xEF\xBB\xBF");
                            fputcsv($handle, ['id', 'csv_file', 'row_number', 'import_type', 'reason', 'loan_id', 'loan_number', 'employee_no', 'batch_no', 'repayment_number', 'receipt_date', 'receipt_amount', 'reference_number', 'matched_repayment_id', 'created_at']);

                            foreach ($rows as $row) {
                                fputcsv($handle, [
                                    $row->id,
                                    $row->csv_file,
                                    $row->row_number,
                                    $row->import_type,
                                    $row->reason,
                                    $row->loan_id,
                                    $row->loan_number,
                                    $row->employee_no,
                                    $row->batch_no,
                                    $row->repayment_number,
                                    optional($row->receipt_date)->toDateString(),
                                    $row->receipt_amount,
                                    $row->reference_number,
                                    $row->matched_repayment_id,
                                    optional($row->created_at)->toDateTimeString(),
                                ]);
                            }

                            fclose($handle);
                        }, $filename);
                    }),
                Action::make('clearSkipped')
                    ->label('Clear Skipped')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Clear skipped repayments')
                    ->modalDescription('This will permanently delete all skipped repayment records.')
                    ->action(function (): void {
                        $deleted = SkippedRepayment::query()->delete();

                        Notification::make()
                            ->title('Skipped repayments cleared')
                            ->body("Deleted {$deleted} skipped repayment record(s).")
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Action::make('postRepayment')
                    ->label('Post Repayment')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Post skipped repayment')
                    ->modalDescription('This will create or update a repayment from the skipped row and recompute the loan ledger.')
                    ->visible(fn (SkippedRepayment $record): bool => ! $record->posted_repayment_id)
                    ->action(function (SkippedRepayment $record): void {
                        try {
                            $repaymentId = app(SkippedRepaymentPostingService::class)->post($record);

                            Notification::make()
                                ->title('Skipped repayment posted')
                                ->body("Posted to repayment #{$repaymentId}.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            $record->forceFill([
                                'status' => 'failed',
                                'post_error' => $e->getMessage(),
                            ])->save();

                            Notification::make()
                                ->title('Could not post skipped repayment')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('ignore')
                    ->label('Ignore')
                    ->icon('heroicon-o-no-symbol')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (SkippedRepayment $record): bool => $record->status !== 'ignored' && ! $record->posted_repayment_id)
                    ->action(function (SkippedRepayment $record): void {
                        $record->forceFill(['status' => 'ignored'])->save();

                        Notification::make()
                            ->title('Skipped repayment ignored')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSkippedRepayments::route('/'),
            'view' => Pages\ViewSkippedRepayment::route('/{record}'),
            'edit' => Pages\EditSkippedRepayment::route('/{record}/edit'),
        ];
    }
}
