<?php

namespace App\Filament\Pages;

use App\Services\CustomerHistoryService;
use Filament\Pages\Page;

class CustomerHistory extends Page
{
    protected static ?string $navigationGroup = 'Customers';
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Customer History';
    protected static ?int $navigationSort = 20;
    protected static string $view = 'filament.pages.customer-history';
    protected static ?string $title = 'Customer History';

    public ?string $search = '';
    public ?int $selectedBorrowerId = null;

    public function mount(): void
    {
        $this->search = request()->query('search', $this->search);
        $selectedBorrowerId = request()->integer('borrower');

        if ($selectedBorrowerId > 0) {
            $this->selectedBorrowerId = $selectedBorrowerId;
        }
    }

    public function selectBorrower(int $borrowerId): void
    {
        $this->selectedBorrowerId = $borrowerId;
    }

    public function clearSelection(): void
    {
        $this->selectedBorrowerId = null;
    }

    public function updatedSearch(): void
    {
        if ($this->selectedBorrowerId) {
            $this->selectedBorrowerId = null;
        }
    }

    public function getSearchResultsProperty()
    {
        return app(CustomerHistoryService::class)->searchBorrowers($this->search);
    }

    public function getCustomerHistoryProperty(): ?array
    {
        return app(CustomerHistoryService::class)->build($this->selectedBorrowerId);
    }
}
