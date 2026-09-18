<?php

namespace App\Filament\Widgets;

use App\Models\Repayments;
use App\Models\Expense;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class ProfitLossOverview extends BaseWidget
{
    use InteractsWithPageFilters;
    use HasWidgetShield;

    protected static ?string $maxHeight = '120px';
    protected static ?int $sort = 2;

    public function getColumns(): int
    {
        return 5;
    }

    protected function getStats(): array
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        $repaymentTotals = Repayments::query()
            ->when($startDate, fn(Builder $query) => $query->whereDate('receipt_date', '>=', $startDate))
            ->when($endDate, fn(Builder $query) => $query->whereDate('receipt_date', '<=', $endDate))
            ->selectRaw('COALESCE(SUM(receipt_amount), 0) as total_collections')
            ->selectRaw('COALESCE(SUM(paid_principal), 0) as principal_recovered')
            ->selectRaw('COALESCE(SUM(paid_interest), 0) as interest_profit')
            ->first();

        $totalCollections = (float) ($repaymentTotals->total_collections ?? 0);
        $principalRecovered = (float) ($repaymentTotals->principal_recovered ?? 0);
        $interestProfit = (float) ($repaymentTotals->interest_profit ?? 0);

        $totalExpenses = Expense::query()
            ->when($startDate, fn(Builder $query) => $query->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn(Builder $query) => $query->whereDate('created_at', '<=', $endDate))
            ->sum('expense_amount');

        $netProfit = $interestProfit - $totalExpenses;
        $profitMargin = $interestProfit > 0 ? round(($netProfit / $interestProfit) * 100, 1) : 0;

        return [
            Stat::make('Total Revenue', 'ZMW ' . number_format($totalCollections, 0))
                ->description('Principal + interest received')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Principal Recovered', 'ZMW ' . number_format($principalRecovered, 0))
                ->description('Recovered loan principal')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('info'),

            Stat::make('Interest Recovered', 'ZMW ' . number_format($interestProfit, 0))
                ->description('Recovered loan interest')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),

            Stat::make('Total Expenses', 'ZMW ' . number_format($totalExpenses, 0))
                ->description('Business operating costs')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),

            Stat::make('Net Profit/Loss', 'ZMW ' . number_format($netProfit, 0))
                ->description($profitMargin >= 0 ? $profitMargin . '% profit margin' : abs($profitMargin) . '% loss margin')
                ->descriptionIcon($netProfit >= 0 ? 'heroicon-m-plus-circle' : 'heroicon-m-minus-circle')
                ->color($netProfit >= 0 ? 'success' : 'danger'),
        ];
    }
}
