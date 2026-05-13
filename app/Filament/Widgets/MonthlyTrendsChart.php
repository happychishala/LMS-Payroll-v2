<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use App\Models\Repayments;
use App\Models\Expense;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class MonthlyTrendsChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use HasWidgetShield;

    protected static ?string $heading = 'Monthly Financial Trends';
    protected static ?int $sort = 9;
    protected static ?string $maxHeight = '350px';
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        $principalCollections = [];
        $interestCollections = [];
        $disbursements = [];
        $expenses = [];
        $outstanding = [];

        for ($month = 1; $month <= 12; $month++) {
            // Principal recovered
            $principalCollections[] = Repayments::query()
                ->when($startDate, fn(Builder $query) => $query->whereDate('receipt_date', '>=', $startDate))
                ->when($endDate, fn(Builder $query) => $query->whereDate('receipt_date', '<=', $endDate))
                ->whereMonth('receipt_date', $month)
                ->sum('paid_principal');

            // Interest profit
            $interestCollections[] = Repayments::query()
                ->when($startDate, fn(Builder $query) => $query->whereDate('receipt_date', '>=', $startDate))
                ->when($endDate, fn(Builder $query) => $query->whereDate('receipt_date', '<=', $endDate))
                ->whereMonth('receipt_date', $month)
                ->sum('paid_interest');

            // Disbursements (loan principals)
            $disbursements[] = Loan::query()
                ->when($startDate, fn(Builder $query) => $query->whereDate('loan_release_date', '>=', $startDate))
                ->when($endDate, fn(Builder $query) => $query->whereDate('loan_release_date', '<=', $endDate))
                ->whereMonth('loan_release_date', $month)
                ->sum('principal_amount');

            // Expenses
            $expenses[] = Expense::query()
                ->when($startDate, fn(Builder $query) => $query->whereDate('created_at', '>=', $startDate))
                ->when($endDate, fn(Builder $query) => $query->whereDate('created_at', '<=', $endDate))
                ->whereMonth('created_at', $month)
                ->sum('expense_amount');

            // Outstanding balance (cumulative)
            $outstanding[] = Loan::query()
                ->when($startDate, fn(Builder $query) => $query->whereDate('created_at', '>=', $startDate))
                ->when($endDate, fn(Builder $query) => $query->whereDate('created_at', '<=', $endDate))
                ->whereMonth('created_at', $month)
                ->sum('balance');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Principal Recovered',
                    'data' => array_map('floatval', $principalCollections),
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => false,
                    'tension' => 0.4,
                    'pointBackgroundColor' => 'rgb(59, 130, 246)',
                    'pointBorderColor' => '#fff',
                    'pointBorderWidth' => 2,
                    'pointRadius' => 3,
                ],
                [
                    'label' => 'Interest Profit',
                    'data' => array_map('floatval', $interestCollections),
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'fill' => false,
                    'tension' => 0.4,
                    'pointBackgroundColor' => 'rgb(34, 197, 94)',
                    'pointBorderColor' => '#fff',
                    'pointBorderWidth' => 2,
                    'pointRadius' => 3,
                ],
                [
                    'label' => 'Disbursements',
                    'data' => array_map('floatval', $disbursements),
                    'borderColor' => 'rgb(168, 85, 247)',
                    'backgroundColor' => 'rgba(168, 85, 247, 0.1)',
                    'fill' => false,
                    'tension' => 0.4,
                    'pointBackgroundColor' => 'rgb(168, 85, 247)',
                    'pointBorderColor' => '#fff',
                    'pointBorderWidth' => 2,
                    'pointRadius' => 3,
                ],
                [
                    'label' => 'Expenses',
                    'data' => array_map('floatval', $expenses),
                    'borderColor' => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'fill' => false,
                    'tension' => 0.4,
                    'pointBackgroundColor' => 'rgb(239, 68, 68)',
                    'pointBorderColor' => '#fff',
                    'pointBorderWidth' => 2,
                    'pointRadius' => 3,
                ],
            ],
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'top',
                    'labels' => [
                        'usePointStyle' => true,
                        'padding' => 15,
                    ],
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                    'callbacks' => [
                        'label' => 'function(context) {
                            let label = context.dataset.label || "";
                            if (label) {
                                label += ": ";
                            }
                            label += "ZMW " + context.parsed.y.toLocaleString();
                            return label;
                        }',
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => 'function(value) {
                            return "ZMW " + value.toLocaleString();
                        }',
                    ],
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
        ];
    }
}
