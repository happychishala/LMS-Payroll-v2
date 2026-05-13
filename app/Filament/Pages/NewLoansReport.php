<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Loan;
use App\Models\LoanType;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\NewLoansExport;

class NewLoansReport extends Page
{
    use WithPagination;

    protected static ?string $navigationGroup = 'Reports';
    protected static ?string $navigationIcon  = 'heroicon-o-rectangle-stack';
    protected static string $view             = 'filament.pages.new-loans-report';
    protected static ?string $title           = 'New Loans Report';

    public ?string $loanName   = '';
    public ?string $employer   = '';
    public ?string $month      = '';  // YYYY-MM
    public ?string $startDate  = '';  // YYYY-MM-DD
    public ?string $endDate    = '';  // YYYY-MM-DD
    public array $previewRows  = [];

    public function mount(): void
    {
        $this->month = now()->format('Y-m'); // sensible default
    }

    public function getLoanNamesProperty()
    {
        return LoanType::orderBy('loan_name')->pluck('loan_name');
    }

    public function getEmployersProperty()
    {
        return Loan::whereNotNull('employer')
            ->distinct()
            ->orderBy('employer')
            ->pluck('employer');
    }

    public function getNewLoansProperty()
    {
        $q = Loan::with(['borrower', 'loan_type']);

        if ($this->loanName) {
            $q->whereHas('loan_type', fn($x) => $x->where('loan_name', $this->loanName));
        }

        if ($this->employer) {
            $q->where('employer', $this->employer);
        }

        if ($this->month) {
            $year  = substr($this->month, 0, 4);
            $month = substr($this->month, 5, 2);
            $q->whereYear('loan_release_date', $year)
              ->whereMonth('loan_release_date', $month);
        } elseif ($this->startDate && $this->endDate) {
            $q->whereBetween('loan_release_date', [$this->startDate, $this->endDate]);
        } else {
            // If neither month nor date range provided, show nothing
            $q->whereRaw('1=0');
        }

        return $q->orderBy('loan_release_date', 'desc')->paginate(10);
    }

    public function updated($property): void
    {
        if (in_array($property, ['loanName', 'employer', 'month', 'startDate', 'endDate'], true)) {
            $this->previewRows = [];
            $this->resetPage();
        }
    }

    public function preview(): void
    {
        if (! $this->month && ! ($this->startDate && $this->endDate)) {
            $this->addError('month', 'Select a month or provide a start & end date.');

            return;
        }

        $this->validate([
            'month'      => 'nullable|date_format:Y-m',
            'startDate'  => 'nullable|date',
            'endDate'    => 'nullable|date|after_or_equal:startDate',
            'loanName'   => 'nullable|string',
            'employer'   => 'nullable|string',
        ]);

        $this->previewRows = $this->getNewLoansProperty()
            ->getCollection()
            ->take(10)
            ->all();
    }

    public function export()
    {
        // Custom validation: require month OR date range
        if (!$this->month && !($this->startDate && $this->endDate)) {
            $this->addError('month', 'Select a month or provide a start & end date.');
            return null;
        }

        // Basic date validations
        $this->validate([
            'month'      => 'nullable|date_format:Y-m',
            'startDate'  => 'nullable|date',
            'endDate'    => 'nullable|date|after_or_equal:startDate',
            'loanName'   => 'nullable|string',
            'employer'   => 'nullable|string',
        ]);

        return Excel::download(
            new NewLoansExport($this->loanName, $this->employer, $this->month, $this->startDate, $this->endDate),
            'new_loans_report_' . now()->format('Ymd_His') . '.csv'
        );
    }
}
