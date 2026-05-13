<?php

namespace App\Filament\Widgets;

use App\Models\Repayments;
use Filament\Widgets\BarChartWidget;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class TotalCollected extends BarChartWidget
{
    use InteractsWithPageFilters;
    use HasWidgetShield;
    protected static ?string $heading = 'Total Collected';
    protected static ?string $maxHeight = '200px';
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;
        $records = [];
        for ($month = 1; $month <= 12; $month++) {
            $records[] = Repayments::query()
            ->when($startDate, fn(Builder $query) => $query->whereDate('receipt_date', '>=', $startDate))
            ->when($endDate, fn(Builder $query) => $query->whereDate('receipt_date', '<=', $endDate))
            ->whereMonth('receipt_date', $month)
            ->sum('receipt_amount');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Monthly Collections (ZMW)',
                    'data' => array_map('floatval', $records),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 1,
                    'hoverBackgroundColor' => 'rgba(34, 197, 94, 1)',
                ],
            ],
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        ];
    }
}
