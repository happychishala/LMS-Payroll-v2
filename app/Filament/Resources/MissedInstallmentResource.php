<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MissedInstallmentResource\Pages;
use App\Models\MissedInstallment;
use App\Services\MissedInstallmentService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;

class MissedInstallmentResource extends Resource
{
    protected static ?string $model = MissedInstallment::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Repayments';
    protected static ?string $navigationLabel = 'Missed Installments';

    public static function form(Forms\Form $form): Forms\Form
    {
        $schema = [
            Forms\Components\TextInput::make('loan_id')->disabled(),
            Forms\Components\TextInput::make('employee_no')->disabled(),
            Forms\Components\TextInput::make('employer')->disabled(),
            Forms\Components\DatePicker::make('due_month')->disabled(),
            Forms\Components\TextInput::make('expected_amount')->numeric()->disabled(),
            Forms\Components\TextInput::make('paid_amount')->numeric()->disabled(),
            Forms\Components\TextInput::make('shortfall_amount')->numeric()->disabled(),
            Forms\Components\TextInput::make('repayment_count')->numeric()->disabled(),
            Forms\Components\Select::make('status')
                ->options([
                    'partial' => 'Partial',
                    'missed' => 'Missed',
                    'resolved' => 'Resolved',
                ])
                ->required(),
            Forms\Components\Textarea::make('remarks')->columnSpanFull(),
            Forms\Components\DateTimePicker::make('generated_at')->disabled(),
        ];

        if (static::hasMissedDateColumn()) {
            array_splice($schema, 4, 0, [
                Forms\Components\DatePicker::make('missed_date')->disabled(),
            ]);
        }

        if (static::hasLoanStatusAtGenerationColumn()) {
            array_splice($schema, static::hasMissedDateColumn() ? 5 : 4, 0, [
                Forms\Components\TextInput::make('loan_status_at_generation')
                    ->label('Loan Status At Generation')
                    ->disabled(),
            ]);
        }

        return $form->schema($schema);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        $columns = [
            TextColumn::make('loan_id')->label('Loan ID')->searchable(),
            TextColumn::make('employee_no')->label('Employee No')->searchable(),
            TextColumn::make('employer')->searchable(),
            TextColumn::make('loan.loan_release_date')
                ->label('Issue Date')
                ->date()
                ->sortable(),
        ];

        if (static::hasMissedDateColumn()) {
            $columns[] = TextColumn::make('missed_date')->label('Missed Date')->date()->sortable();
        }

        if (static::hasLoanStatusAtGenerationColumn()) {
            $columns[] = TextColumn::make('loan_status_at_generation')
                ->label('Loan Status At Generation')
                ->badge()
                ->sortable()
                ->searchable();
        }

        $columns = array_merge($columns, [
            TextColumn::make('due_month')->label('Month')->date('F Y')->sortable(),
            TextColumn::make('expected_amount')->money('ZMW')->sortable(),
            TextColumn::make('paid_amount')->money('ZMW')->sortable(),
            TextColumn::make('shortfall_amount')->money('ZMW')->sortable(),
            TextColumn::make('repayment_count')->label('Repayments')->sortable(),
            TextColumn::make('status')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'resolved' => 'success',
                    'partial' => 'warning',
                    'missed' => 'danger',
                    default => 'gray',
                }),
            TextColumn::make('generated_at')->dateTime()->toggleable(),
        ]);

        $filters = [
            Tables\Filters\Filter::make('client_history')
                ->label('Client History')
                ->form([
                    Forms\Components\TextInput::make('loan_id')->label('Loan ID'),
                    Forms\Components\TextInput::make('employee_no')->label('Employee No'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(filled($data['loan_id'] ?? null), fn (Builder $query) => $query->where('loan_id', 'like', '%' . trim((string) $data['loan_id']) . '%'))
                        ->when(filled($data['employee_no'] ?? null), fn (Builder $query) => $query->where('employee_no', 'like', '%' . trim((string) $data['employee_no']) . '%'));
                }),
            Tables\Filters\SelectFilter::make('status')
                ->label('Installment Status')
                ->placeholder('All')
                ->options([
                    'partial' => 'Partial',
                    'missed' => 'Missed',
                ]),
            Tables\Filters\SelectFilter::make('employer')
                ->options(fn () => parent::getEloquentQuery()
                    ->whereNotNull('employer')
                    ->orderBy('employer')
                    ->distinct()
                    ->pluck('employer', 'employer')
                    ->toArray()),
            Tables\Filters\Filter::make('due_month')
                ->form([
                    Forms\Components\DatePicker::make('month')->label('Month'),
                ])
                ->query(function ($query, array $data) {
                    if (empty($data['month'])) {
                        return $query;
                    }

                    $month = Carbon::parse($data['month']);

                    return $query
                        ->whereYear('due_month', $month->year)
                        ->whereMonth('due_month', $month->month);
                }),
        ];

        if (static::hasGeneratedAtColumn()) {
            $filters[] = Tables\Filters\SelectFilter::make('generated_at')
                ->label('Generation Batch')
                ->options(fn () => MissedInstallment::query()
                    ->whereNotNull('generated_at')
                    ->orderByDesc('generated_at')
                    ->get()
                    ->mapWithKeys(fn (MissedInstallment $row) => [
                        optional($row->generated_at)->toDateTimeString() => optional($row->generated_at)->format('d/m/Y H:i:s'),
                    ])
                    ->toArray());
        }

        return $table
            ->defaultSort(static::hasMissedDateColumn() ? 'missed_date' : 'due_month', 'desc')
            ->columns($columns)
            ->filters($filters)
            ->headerActions([
                Action::make('generateMonth')
                    ->label('Generate Range')
                    ->icon('heroicon-o-arrow-path')
                    ->form([
                        Forms\Components\DatePicker::make('from_month')
                            ->label('From Month')
                            ->default(now()->startOfMonth())
                            ->required(),
                        Forms\Components\DatePicker::make('to_month')
                            ->label('To Month')
                            ->default(now()->startOfMonth())
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $result = app(MissedInstallmentService::class)
                            ->generateForRange(
                                Carbon::parse($data['from_month']),
                                Carbon::parse($data['to_month']),
                            );

                        Notification::make()
                            ->success()
                            ->title('Missed installments generated')
                            ->body("Range {$result['from_month']} to {$result['to_month']}: processed {$result['months_processed']} month(s), created {$result['created']}, updated {$result['updated']}, deleted {$result['deleted']}")
                            ->send();
                    }),
                Action::make('exportAll')
                    ->label('Export All')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {
                        $rows = parent::getEloquentQuery()
                            ->orderBy('due_month', 'desc')
                            ->orderBy('loan_id')
                            ->get();

                        return response()->streamDownload(function () use ($rows) {
                            $handle = fopen('php://output', 'w');
                            fwrite($handle, "\xEF\xBB\xBF");
                            fputcsv($handle, static::exportHeaders());

                            foreach ($rows as $row) {
                                fputcsv($handle, static::exportRow($row));
                            }

                            fclose($handle);
                        }, 'missed_installments_' . now()->format('Ymd_His') . '.csv');
                    }),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkAction::make('exportSelected')
                    ->label('Export Selected')
                    ->action(function ($records) {
                        $rows = $records;

                        return response()->streamDownload(function () use ($rows) {
                            $handle = fopen('php://output', 'w');
                            fwrite($handle, "\xEF\xBB\xBF");
                            fputcsv($handle, static::exportHeaders());

                            foreach ($rows as $row) {
                                fputcsv($handle, static::exportRow($row));
                            }

                            fclose($handle);
                        }, 'missed_installments_selected_' . now()->format('Ymd_His') . '.csv');
                    })
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion(),
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMissedInstallments::route('/'),
            'edit' => Pages\EditMissedInstallment::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    protected static function hasMissedDateColumn(): bool
    {
        return Schema::hasColumn('missed_installments', 'missed_date');
    }

    protected static function hasLoanStatusAtGenerationColumn(): bool
    {
        return Schema::hasColumn('missed_installments', 'loan_status_at_generation');
    }

    protected static function hasGeneratedAtColumn(): bool
    {
        return Schema::hasColumn('missed_installments', 'generated_at');
    }

    protected static function exportHeaders(): array
    {
        $headers = ['id', 'loan_id', 'employee_no', 'employer', 'due_month'];

        if (static::hasMissedDateColumn()) {
            $headers[] = 'missed_date';
        }

        if (static::hasLoanStatusAtGenerationColumn()) {
            $headers[] = 'loan_status_at_generation';
        }

        return array_merge($headers, [
            'expected_amount',
            'paid_amount',
            'shortfall_amount',
            'repayment_count',
            'status',
            'remarks',
            'generated_at',
            'created_at',
            'updated_at',
        ]);
    }

    protected static function exportRow(MissedInstallment $row): array
    {
        $data = [
            $row->id,
            $row->loan_id,
            $row->employee_no,
            $row->employer,
            optional($row->due_month)->toDateString(),
        ];

        if (static::hasMissedDateColumn()) {
            $data[] = optional($row->missed_date)->toDateString();
        }

        if (static::hasLoanStatusAtGenerationColumn()) {
            $data[] = $row->loan_status_at_generation;
        }

        return array_merge($data, [
            $row->expected_amount,
            $row->paid_amount,
            $row->shortfall_amount,
            $row->repayment_count,
            $row->status,
            $row->remarks,
            optional($row->generated_at)->toDateTimeString(),
            optional($row->created_at)->toDateTimeString(),
            optional($row->updated_at)->toDateTimeString(),
        ]);
    }
}
