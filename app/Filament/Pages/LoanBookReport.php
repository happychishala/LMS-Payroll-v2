<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Loan;
use App\Models\LoanType;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\LoanBookExport;
use App\Services\LoanBookQueryBuilder;

class LoanBookReport extends Page
{
    use WithPagination;

    protected static ?string $navigationGroup = 'Loans';
    protected static ?string $navigationIcon  = 'heroicon-o-book-open';
    protected static ?int $navigationSort     = 50;
    protected static string $view             = 'filament.pages.loan-book-report';
    protected static ?string $title           = 'Loan Book';

    public ?string $asOfDate = '';
    public ?string $employer = '';
    public ?string $loanName = '';
    public ?string $status   = '';

    public function mount(): void
    {
        $this->asOfDate = now()->toDateString();
    }

    public function getEmployersProperty()
    {
        return Loan::whereNotNull('employer')->distinct()->orderBy('employer')->pluck('employer');
    }

    public function getLoanNamesProperty()
    {
        return LoanType::orderBy('loan_name')->pluck('loan_name');
    }

    public function getStatusesProperty()
    {
        return Loan::whereNotNull('loan_status')->distinct()->orderBy('loan_status')->pluck('loan_status');
    }

    /** Ensures Blade has data for the preview table */
    public function getPreviewRowsProperty()
    {
        return LoanBookQueryBuilder::build(
            $this->asOfDate ?: null,
            $this->employer ?: null,
            $this->loanName ?: null,
            $this->status ?: null
        )->take(25)->values();
    }

    /** Snapshot data for the header cards/tables */
    public function getSnapshotProperty(): array
    {
        $rows = LoanBookQueryBuilder::build(
            $this->asOfDate ?: null,
            $this->employer ?: null,
            $this->loanName ?: null,
            $this->status ?: null
        );

        return LoanBookQueryBuilder::summarize($rows);
    }

    public function updated($prop)
    {
        if (in_array($prop, ['asOfDate','employer','loanName','status'])) {
            $this->resetPage();
        }
    }

    public function export()
    {
        $this->validate([
            'asOfDate' => 'required|date',
            'employer' => 'nullable|string',
            'loanName' => 'nullable|string',
            'status'   => 'nullable|string',
        ]);

        return Excel::download(
            new LoanBookExport(
                $this->asOfDate,
                $this->employer ?: null,
                $this->loanName ?: null,
                $this->status ?: null
            ),
            'loan_book_' . now()->format('Ymd_His') . '.csv'
        );
    }
}
