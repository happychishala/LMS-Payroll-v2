<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Loan;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\CustomerDetailsExport;
use App\Services\CustomerDetailsQueryBuilder;

class CustomerDetailsReport extends Page
{
    use WithPagination;

    protected static ?string $navigationGroup = 'Loans';
    protected static ?string $navigationIcon  = 'heroicon-o-user-circle';
    protected static ?int $navigationSort     = 60;
    protected static string $view             = 'filament.pages.customer-details-report';
    protected static ?string $title           = 'Customer Details (KYC)';

    public ?string $employer = '';
    public ?bool $offPayrollOnly = false;
    public ?string $search = '';

    public function getEmployersProperty()
    {
        return Loan::whereNotNull('employer')->distinct()->orderBy('employer')->pluck('employer');
    }

    /** Preview (first 25 rows) */
    public function getPreviewRowsProperty()
    {
        return CustomerDetailsQueryBuilder::build(
            $this->employer ?: null,
            (bool) $this->offPayrollOnly,
            $this->search ?: null
        )->take(25)->values();
    }

    public function updated($prop)
    {
        if (in_array($prop, ['employer','offPayrollOnly','search'])) {
            $this->resetPage();
        }
    }

    public function export()
    {
        $this->validate([
            'employer'       => 'nullable|string',
            'offPayrollOnly' => 'nullable|boolean',
            'search'         => 'nullable|string|max:100',
        ]);

        return Excel::download(
            new CustomerDetailsExport(
                $this->employer ?: null,
                (bool) $this->offPayrollOnly,
                $this->search ?: null
            ),
            'customer_details_' . now()->format('Ymd_His') . '.csv'
        );
    }
}
