<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use Filament\Widgets\LineChartWidget;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;


class PrincipleReleased extends LineChartWidget
{
    use InteractsWithPageFilters;
    use HasWidgetShield;
   
    protected static ?string $maxHeight = '200px';
    protected static ?int $sort = 4;

   



    public function getHeading(): string
    {
        return 'Funds Disbursed';
    }

    protected function getData(): array
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;
        $records = [];

        for ($month = 1; $month <= 12; $month++) {
            $records[] = Loan::query()
            ->when($startDate, fn(Builder $query) => $query->whereDate('loan_release_date', '>=', $startDate))
            ->when($endDate, fn(Builder $query) => $query->whereDate('loan_release_date', '<=', $endDate))
            ->whereMonth('loan_release_date', $month)
            ->sum('principal_amount');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Funds Disbursed (ZMW)',
                    'data' => array_map('floatval', $records),
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                    'pointBackgroundColor' => 'rgb(59, 130, 246)',
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