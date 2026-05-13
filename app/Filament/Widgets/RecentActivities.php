<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use App\Models\Repayments;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class RecentActivities extends BaseWidget
{
    use InteractsWithPageFilters;
    use HasWidgetShield;

    protected static ?string $heading = 'Recent Activities';
    protected static ?int $sort = 7;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // Get recent loans with borrower information
                Loan::query()
                    ->with('borrower')
                    ->select([
                        'id',
                        'borrower_id',
                        \DB::raw("'' as first_name"),
                        \DB::raw("'' as last_name"),
                        \DB::raw("'' as other_names"),
                        'principal_amount as amount',
                        'loan_status as status',
                        'created_at',
                        \DB::raw("'loan' as type"),
                        \DB::raw("'Loan Application' as description")
                    ])
                    ->when($this->filters['startDate'] ?? null, fn(Builder $query) => $query->whereDate('created_at', '>=', $this->filters['startDate']))
                    ->when($this->filters['endDate'] ?? null, fn(Builder $query) => $query->whereDate('created_at', '<=', $this->filters['endDate']))
                    ->union(
                        // Get recent repayments with borrower information through loan relationship
                        Repayments::query()
                            ->join('loans', 'repayments.loan_id', '=', 'loans.loan_id')
                            ->join('borrowers', 'loans.borrower_id', '=', 'borrowers.id')
                            ->select([
                                'repayments.id',
                                \DB::raw("'' as borrower_id"),
                                'borrowers.first_name',
                                'borrowers.last_name',
                                'borrowers.other_names',
                                'repayments.receipt_amount as amount',
                                \DB::raw("'paid' as status"),
                                'repayments.receipt_date as created_at',
                                \DB::raw("'repayment' as type"),
                                \DB::raw("'Loan Repayment' as description")
                            ])
                            ->when($this->filters['startDate'] ?? null, fn(Builder $query) => $query->whereDate('repayments.receipt_date', '>=', $this->filters['startDate']))
                            ->when($this->filters['endDate'] ?? null, fn(Builder $query) => $query->whereDate('repayments.receipt_date', '<=', $this->filters['endDate']))
                    )
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'loan' => 'info',
                        'repayment' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                Tables\Columns\TextColumn::make('borrower_name')
                    ->label('Customer')
                    ->getStateUsing(function ($record) {
                        if ($record->type === 'loan' && $record->borrower) {
                            return trim($record->borrower->first_name . ' ' . $record->borrower->last_name . ' ' . ($record->borrower->other_names ?? ''));
                        } elseif ($record->type === 'repayment') {
                            return trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? '') . ' ' . ($record->other_names ?? ''));
                        }
                        return 'Unknown';
                    })
                    ->searchable()
                    ->limit(20),

                Tables\Columns\TextColumn::make('description')
                    ->label('Activity'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('ZMW')
                    ->color(fn ($record): string =>
                        $record->type === 'repayment' ? 'success' : 'info'
                    ),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved', 'paid' => 'success',
                        'processing' => 'warning',
                        'defaulted' => 'danger',
                        'closed' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated(false);
    }
}