<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use Filament\Widgets\BarChartWidget;
use Filament\Widgets\LineChartWidget;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;


class OutstandingBalance extends BarChartWidget
{
    use InteractsWithPageFilters;
    use HasWidgetShield;
  
    protected static ?int $sort = 5;

   



    public function getHeading(): string
    {
        return 'Outstanding Balance';
    }

    protected function getData(): array
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;
        $records = [];

        for ($month = 1; $month <= 12; $month++) {
            $records[] = Loan::query()
            ->when($startDate, fn(Builder $query) => $query->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn(Builder $query) => $query->whereDate('created_at', '<=', $endDate))
            ->whereMonth('created_at', $month)
            ->sum('balance');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Outstanding Balance (ZMW)',
                    'data' => array_map('floatval', $records),
                    'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                    'borderColor' => 'rgb(239, 68, 68)',
                    'borderWidth' => 1,
                    'hoverBackgroundColor' => 'rgba(239, 68, 68, 1)',
                    'borderRadius' => 4,
                    'borderSkipped' => false,
                ],
            ],
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        ];
    }

}