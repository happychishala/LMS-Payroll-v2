<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\LoanType;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\EmployerCsvExport;
use App\Services\EmployerCsvReportQueryBuilder;

class EmployerCsvReport extends Page
{
    use WithPagination;

    protected static ?string $navigationGroup = 'Reports';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static string $view = 'filament.pages.employer-csv-report';
    protected static ?string $title = 'Employer CSV Report';

    public $loanName;
    public $month;
    public $startDate;
    public $endDate;

    public $previewRows = [];

    public function mount(): void
    {
        $this->loanName = '';
        $this->month = now()->format('Y-m');
        $this->startDate = null;
        $this->endDate = null;
    }

    public function getLoanNamesProperty()
    {
        return LoanType::orderBy('loan_name')->pluck('loan_name');
    }

    public function updated($property)
    {
        if (in_array($property, ['loanName', 'month', 'startDate', 'endDate'])) {
            $this->previewRows = [];
        }
    }

    public function preview()
    {
        $this->validate([
            'loanName' => 'required|string',
            'month' => 'required|date_format:Y-m',
            'startDate' => 'nullable|date',
            'endDate' => 'nullable|date|after_or_equal:startDate',
        ]);

        $this->previewRows = EmployerCsvReportQueryBuilder::build(
            $this->loanName,
            $this->month,
            $this->startDate,
            $this->endDate
        )->take(20)->values()->all();
    }

    public function export()
    {
        $this->validate([
            'loanName' => 'required|string',
            'month' => 'required|date_format:Y-m',
            'startDate' => 'nullable|date',
            'endDate' => 'nullable|date|after_or_equal:startDate',
        ]);

        return Excel::download(
            new EmployerCsvExport($this->loanName, $this->month, $this->startDate, $this->endDate),
            'loan_report_' . str($this->loanName)->slug('_') . '_' . now()->format('Ymd_His') . '.csv'
        );
    }
}
