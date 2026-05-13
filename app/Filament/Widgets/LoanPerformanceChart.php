<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use App\Models\MissedInstallment;
use App\Models\Repayments;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class LoanPerformanceChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use HasWidgetShield;

    protected static ?string $heading = 'Loan Performance Overview';
    protected static ?int $sort = 8;
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        $loanStatuses = Loan::query()
            ->when($startDate, fn(Builder $query) => $query->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn(Builder $query) => $query->whereDate('created_at', '<=', $endDate))
            ->selectRaw('loan_status, COUNT(*) as count')
            ->groupBy('loan_status')
            ->pluck('count', 'loan_status')
            ->toArray();

        $defaultedCount = $this->defaultedLoanIds($startDate, $endDate)->count();

        $labels = [];
        $data = [];
        $backgroundColors = [];

        $statusConfig = [
            'approved' => ['label' => 'Active', 'color' => 'rgba(34, 197, 94, 0.8)'],
            'partially_paid' => ['label' => 'Partially Paid', 'color' => 'rgba(251, 191, 36, 0.8)'],
            'processing' => ['label' => 'Processing', 'color' => 'rgba(59, 130, 246, 0.8)'],
            'defaulted' => ['label' => 'Defaulted', 'color' => 'rgba(239, 68, 68, 0.8)'],
            'closed' => ['label' => 'Closed', 'color' => 'rgba(107, 114, 128, 0.8)'],
        ];

        foreach ($statusConfig as $status => $config) {
            $count = $status === 'defaulted'
                ? $defaultedCount
                : ($loanStatuses[$status] ?? 0);

            if ($count > 0) {
                $labels[] = $config['label'];
                $data[] = $count;
                $backgroundColors[] = $config['color'];
            }
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $backgroundColors,
                    'borderWidth' => 2,
                    'borderColor' => '#ffffff',
                    'hoverBorderWidth' => 3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => [
                        'padding' => 20,
                        'usePointStyle' => true,
                    ],
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) {
                            const label = context.label || "";
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                            return label + ": " + value + " loans (" + percentage + "%)";
                        }',
                    ],
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }

    private function defaultedLoanIds(?string $startDate, ?string $endDate): Collection
    {
        $defaultedLoanIds = MissedInstallment::query()
            ->when($startDate, fn (Builder $query) => $query->whereDate('due_month', '>=', $startDate))
            ->when($endDate, fn (Builder $query) => $query->whereDate('due_month', '<=', $endDate))
            ->where('status', 'missed')
            ->select('loan_id')
            ->groupBy('loan_id')
            ->havingRaw('COUNT(*) >= 3')
            ->pluck('loan_id');

        if ($defaultedLoanIds->isEmpty()) {
            return collect();
        }

        $excludedLoanIds = Loan::query()
            ->whereIn('loan_id', $defaultedLoanIds->all())
            ->whereIn('loan_status', ['closed', 'paid off', 'paid_off', 'paid-off', 'paidoff'])
            ->pluck('loan_id');

        return $defaultedLoanIds
            ->diff($excludedLoanIds)
            ->values();
    }
}
