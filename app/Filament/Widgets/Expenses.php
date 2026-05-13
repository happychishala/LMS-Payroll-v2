<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use Filament\Widgets\LineChartWidget;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use App\Models\Expense;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;


class Expenses extends LineChartWidget
{
    use InteractsWithPageFilters;
    use HasWidgetShield;


    protected static ?int $sort = 6;





    public function getHeading(): string
    {
        return 'Business Expenses';
    }

    protected function getData(): array
{
    $startDate = $this->filters['startDate'] ?? null;
    $endDate = $this->filters['endDate'] ?? null;
    $records = [];

    for ($month = 1; $month <= 12; $month++) {
        $records[] = Expense::query()
            ->when($startDate, fn(Builder $query) => $query->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn(Builder $query) => $query->whereDate('created_at', '<=', $endDate))
            ->whereMonth('created_at', $month)
            ->sum('expense_amount');
    }

    return [
        'datasets' => [
            [
                'label' => 'Business Expenses (ZMW)',
                'data' => array_map('floatval', $records),
                'borderColor' => 'rgb(245, 101, 101)',
                'backgroundColor' => 'rgba(245, 101, 101, 0.1)',
                'fill' => true,
                'tension' => 0.4,
                'pointBackgroundColor' => 'rgb(245, 101, 101)',
                'pointBorderColor' => '#fff',
                'pointBorderWidth' => 2,
                'pointRadius' => 4,
                'pointHoverRadius' => 6,
            ],
        ],
        'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    ];
}


}