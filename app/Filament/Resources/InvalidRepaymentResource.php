<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvalidRepaymentResource\Pages;
use App\Models\InvalidRepayment;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Resources\Form;
use Filament\Forms;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Log;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\ExportBulkAction;
use Filament\Notifications\Notification;

class InvalidRepaymentResource extends Resource
{
    protected static ?string $model = InvalidRepayment::class;
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationGroup = 'Repayments';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('employee_no')->required(),
            Forms\Components\TextInput::make('name'),
            Forms\Components\TextInput::make('amount_raw')->label('Amount (raw)')->required(),
            Forms\Components\DatePicker::make('receipt_date')->label('Receipt date')->required(),
            Forms\Components\Textarea::make('errors')->disabled(),
            Forms\Components\Select::make('status')->options([
                'pending' => 'Pending',
                'processed' => 'Processed',
                'ignored' => 'Ignored',
            ]),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            TextColumn::make('id'),
            TextColumn::make('csv_file')->searchable(),
            TextColumn::make('row_number'),
            TextColumn::make('employee_no')->searchable(),
            TextColumn::make('name')->searchable(),
            TextColumn::make('amount_raw'),
            TextColumn::make('errors')->limit(80)->searchable(),
            TextColumn::make('status'),
            TextColumn::make('created_at')->dateTime(),
        ])->searchable()
        ->filters([])
        ->headerActions([
            Action::make('exportInvalid')
                ->label('Export Invalid')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $rows = InvalidRepayment::orderBy('id')->get();
                    $filename = 'invalid_repayments_' . now()->format('Ymd_His') . '.csv';
                    return response()->streamDownload(function () use ($rows) {
                        $handle = fopen('php://output', 'w');
                        // BOM for Excel compatibility
                        fwrite($handle, "\xEF\xBB\xBF");
                        fputcsv($handle, ['id','csv_file','row_number','employee_no','name','nrc','amount_raw','amount','receipt_date_raw','receipt_date','errors','status','processed_rep_id','processed_at','created_at','updated_at']);
                        foreach ($rows as $r) {
                            fputcsv($handle, [
                                $r->id,
                                $r->csv_file,
                                $r->row_number,
                                $r->employee_no,
                                $r->name,
                                $r->nrc,
                                $r->amount_raw,
                                $r->amount,
                                $r->receipt_date_raw,
                                $r->receipt_date,
                                $r->errors,
                                $r->status,
                                $r->processed_rep_id,
                                optional($r->processed_at)->toDateTimeString(),
                                optional($r->created_at)->toDateTimeString(),
                                optional($r->updated_at)->toDateTimeString(),
                            ]);
                        }
                        fclose($handle);
                    }, $filename);
                }),
            Action::make('clearInvalids')
                ->label('Clear Invalids')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Clear invalid repayments')
                ->modalDescription('This will permanently delete all invalid repayment rows.')
                ->action(function (): void {
                    $deleted = InvalidRepayment::query()->delete();

                    Notification::make()
                        ->title('Invalid repayments cleared')
                        ->body("Deleted {$deleted} invalid repayment record(s).")
                        ->success()
                        ->send();
                }),
        ])
 
        ->actions([
            EditAction::make(), // standard edit action (requires Edit page)

            Action::make('applyCorrection')
                ->label('Apply Correction')
                ->icon('heroicon-o-check')
                ->action(function (InvalidRepayment $record) {
                    // call your service to apply correction (adjust to your service signature)
                    app(\App\Services\InvalidRepaymentService::class)->applyCorrection($record);
                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Correction applied')
                        ->send();
                })
                ->requiresConfirmation()
                // only show when pending/unprocessed
                ->visible(fn (InvalidRepayment $record): bool => ($record->status ?? '') === 'pending'),
         ])
 
        ->bulkActions([
            BulkAction::make('exportSelected')
                ->label('Export Selected')
                ->action(function ($records) {
                    $rows = $records;
                    $filename = 'invalid_repayments_selected_' . now()->format('Ymd_His') . '.csv';
                    return response()->streamDownload(function () use ($rows) {
                        $handle = fopen('php://output', 'w');
                        fwrite($handle, "\xEF\xBB\xBF");
                        fputcsv($handle, ['id','csv_file','row_number','employee_no','name','nrc','amount_raw','amount','receipt_date_raw','receipt_date','errors','status','processed_rep_id','processed_at','created_at','updated_at']);
                        foreach ($rows as $r) {
                            fputcsv($handle, [
                                $r->id,
                                $r->csv_file,
                                $r->row_number,
                                $r->employee_no,
                                $r->name,
                                $r->nrc,
                                $r->amount_raw,
                                $r->amount,
                                $r->receipt_date_raw,
                                $r->receipt_date,
                                $r->errors,
                                $r->status,
                                $r->processed_rep_id,
                                optional($r->processed_at)->toDateTimeString(),
                                optional($r->created_at)->toDateTimeString(),
                                optional($r->updated_at)->toDateTimeString(),
                            ]);
                        }
                        fclose($handle);
                    }, $filename);
                })
                ->requiresConfirmation()
                ->deselectRecordsAfterCompletion(),
             DeleteBulkAction::make(),
             ExportBulkAction::make(),
         ]);
     }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvalidRepayments::route('/'),
            'edit' => Pages\EditInvalidRepayment::route('/{record}/edit'),
        ];
    }
}
