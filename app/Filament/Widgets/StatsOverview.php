<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use App\Models\MissedInstallment;
use App\Models\Repayments;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class StatsOverview extends BaseWidget
{
    use InteractsWithPageFilters;
    use HasWidgetShield;
    protected static ?string $maxHeight = '100px';
    protected static ?int $sort = 1;
    public function getColumns(): int
    {
        return 3;
    }


    protected function getStats(): array
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        $activeLoans = Loan::query()
            ->when($startDate, fn(Builder $query) => $query->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn(Builder $query) => $query->whereDate('created_at', '<=', $endDate))
            ->where(fn (Builder $query) => $query
                ->where('loan_status', 'approved')
                ->orWhere('loan_status', 'partially_paid'))
            ->count();

        $pendingLoans = Loan::query()
            ->when($startDate, fn(Builder $query) => $query->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn(Builder $query) => $query->whereDate('created_at', '<=', $endDate))
            ->where('loan_status', 'processing')
            ->count();

        $defaultedLoans = $this->defaultedLoanIds($startDate, $endDate)->count();

        $fullyPaidLoans = Loan::query()
            ->when($startDate, fn(Builder $query) => $query->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn(Builder $query) => $query->whereDate('created_at', '<=', $endDate))
            ->whereIn('loan_status', ['closed', 'paid off', 'paid_off', 'paid-off', 'paidoff'])
            ->count();

        $totalLoans = Loan::query()
            ->when($startDate, fn(Builder $query) => $query->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn(Builder $query) => $query->whereDate('created_at', '<=', $endDate))
            ->count();
        $totalPrincipal = Loan::query()
            ->when($startDate, fn(Builder $query) => $query->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn(Builder $query) => $query->whereDate('created_at', '<=', $endDate))
            ->sum('principal_amount');

        $outstandingBalance = Loan::query()
            ->when($startDate, fn (Builder $query) => $query->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn (Builder $query) => $query->whereDate('created_at', '<=', $endDate))
            ->where('balance', '>', 0)
            ->sum('balance');

        $repaymentsQuery = Repayments::query()
            ->when($startDate, fn(Builder $query) => $query->whereDate('receipt_date', '>=', $startDate))
            ->when($endDate, fn(Builder $query) => $query->whereDate('receipt_date', '<=', $endDate));

        $repaymentTotals = (clone $repaymentsQuery)
            ->selectRaw('COALESCE(SUM(receipt_amount), 0) as total_collected')
            ->selectRaw('COALESCE(SUM(paid_principal), 0) as total_principal_collected')
            ->selectRaw('COALESCE(SUM(paid_interest), 0) as total_interest_collected')
            ->first();

        $totalCollected = (float) ($repaymentTotals->total_collected ?? 0);
        $totalPrincipalCollected = (float) ($repaymentTotals->total_principal_collected ?? 0);
        $totalInterestCollected = (float) ($repaymentTotals->total_interest_collected ?? 0);

        return [
            Stat::make('Total Portfolio Value', 'ZMW ' . number_format($totalPrincipal, 0))
                ->description('Total loan principal amount')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary')
                ->chart($this->getPortfolioTrend()),

            Stat::make('Total Collections', 'ZMW ' . number_format($totalCollected, 0))
                ->description('Principal: ZMW ' . number_format($totalPrincipalCollected, 0) . ' | Interest: ZMW ' . number_format($totalInterestCollected, 0))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->chart($this->getCollectionsTrend()),

            Stat::make('Outstanding Balance', 'ZMW ' . number_format($outstandingBalance, 0))
                ->description('Current unpaid balance across active loans')
                ->descriptionIcon('heroicon-m-scale')
                ->color('danger')
                ->chart($this->getOutstandingBalanceTrend()),

            Stat::make('Active Loans', $activeLoans)
                ->description($totalLoans > 0 ? round(($activeLoans / $totalLoans) * 100, 1) . '% of total loans' : 'No loans yet')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('info')
                ->url('admin/loans'),

            Stat::make('Pending Approvals', $pendingLoans)
                ->description($totalLoans > 0 ? round(($pendingLoans / $totalLoans) * 100, 1) . '% awaiting approval' : 'No pending loans')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->url('admin/loans?activeTab=Processing'),

            Stat::make('Defaulted Loans', $defaultedLoans)
                ->description($totalLoans > 0 ? round(($defaultedLoans / $totalLoans) * 100, 1) . '% with 3+ missed installments' : 'No defaults')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger')
                ->url('admin/loans?activeTab=Over+Due'),

            Stat::make('Fully Paid Loans', $fullyPaidLoans)
                ->description($totalLoans > 0 ? round(($fullyPaidLoans / $totalLoans) * 100, 1) . '% successfully completed' : 'No completed loans')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->url('admin/loans?activeTab=Settled')
        ];
    }

    private function getPortfolioTrend(): array
    {
        // Simple trend data - you can make this more sophisticated
        return [7, 3, 4, 5, 6, 3, 5, 6, 7, 8, 9, 10];
    }

    private function getCollectionsTrend(): array
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        $monthlyTotals = Repayments::query()
            ->when($startDate, fn (Builder $query) => $query->whereDate('receipt_date', '>=', $startDate))
            ->when($endDate, fn (Builder $query) => $query->whereDate('receipt_date', '<=', $endDate))
            ->whereNotNull('receipt_date')
            ->selectRaw('MONTH(receipt_date) as month_number, COALESCE(SUM(receipt_amount), 0) as total')
            ->groupBy(DB::raw('MONTH(receipt_date)'))
            ->pluck('total', 'month_number');

        $trend = array_fill(1, 12, 0.0);

        foreach ($monthlyTotals as $monthNumber => $total) {
            $trend[(int) $monthNumber] = (float) $total;
        }

        return array_values($trend);
    }

    private function getOutstandingBalanceTrend(): array
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        $monthlyTotals = Loan::query()
            ->when($startDate, fn (Builder $query) => $query->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn (Builder $query) => $query->whereDate('created_at', '<=', $endDate))
            ->where('balance', '>', 0)
            ->selectRaw('MONTH(created_at) as month_number, COALESCE(SUM(balance), 0) as total')
            ->groupBy(DB::raw('MONTH(created_at)'))
            ->pluck('total', 'month_number');

        $trend = array_fill(1, 12, 0.0);

        foreach ($monthlyTotals as $monthNumber => $total) {
            $trend[(int) $monthNumber] = (float) $total;
        }

        return array_values($trend);
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
