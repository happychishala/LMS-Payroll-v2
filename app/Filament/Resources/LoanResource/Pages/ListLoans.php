<?php

namespace App\Filament\Resources\LoanResource\Pages;

use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\LoanResource;
use App\Models\Loan;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLoans extends ListRecords
{
    protected static string $resource = LoanResource::class;

    public $loanIdSearch = '';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'All' => Tab::make('All')
                ->icon('heroicon-m-rectangle-stack'),
            'Active' => Tab::make('Active')
                ->icon('heroicon-m-home')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(fn (Builder $statusQuery) => $statusQuery
                    ->where('loan_status', 'approved')
                    ->orWhere('loan_status', 'partially_paid'))),
            'Settled' => Tab::make('Settled')
                ->icon('heroicon-m-home')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('loan_status', ['closed', 'Closed', 'Paid Off', 'paid off', 'paid_off', 'paid-off', 'paidoff'])),
            'Processing' => Tab::make('Processing')
                ->icon('heroicon-m-home')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('loan_status', 'processing')),
            'Requested' => Tab::make('Requested')
                ->icon('heroicon-m-document-text')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('loan_status', 'requested')),
            'Refund' => Tab::make('Refund')
                ->icon('heroicon-m-arrow-uturn-left')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('loan_status', ['Refund', 'refund'])),
            'Over Due' => Tab::make('Over Due')
                ->icon('heroicon-m-home')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('loan_status', 'defaulted')),
            'Failed' => Tab::make('Failed')
                ->icon('heroicon-m-home')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('loan_status', 'denied')),
        ];
    }

    public function applyLoanIdSearch()
    {
        $this->resetPage();
    }

    protected function getTableQuery(): ?\Illuminate\Database\Eloquent\Builder
    {
        $this->syncLoanStatusesFromBalance();

        $query = parent::getTableQuery();

        if ($this->loanIdSearch) {
            $query->where('loan_id', $this->loanIdSearch);
        }

        return $query;
    }

    protected function syncLoanStatusesFromBalance(): void
    {
        Loan::query()
            ->where('balance', '>=', -1)
            ->where('balance', '<=', 5)
            ->whereNotIn('loan_status', ['Closed', 'closed', 'Refund', 'refund'])
            ->update(['loan_status' => 'Closed']);

        Loan::query()
            ->where('balance', '<', -1)
            ->whereNotIn('loan_status', ['Refund', 'refund'])
            ->update(['loan_status' => 'Refund']);
    }
}
