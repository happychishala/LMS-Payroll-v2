<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Loan;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\OutrightSettlementsExport;

class OutrightSettlementsReport extends Page
{
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-check-circle';
    protected static ?string $navigationGroup = 'Reports';
    protected static string $view = 'filament.pages.outright-settlements-report';
    protected static ?string $title = 'Outright Settlements Report';

    public $startDate;
    public $endDate;
    public $settlementStart;
    public $settlementEnd;
    public array $previewRows = [];

    public function mount(): void
    {
        $this->startDate = '';
        $this->endDate = '';
        $this->settlementStart = '';
        $this->settlementEnd = '';
    }

    public function getSettledLoansProperty()
    {
        return Loan::with('borrower', 'loan_type')
            ->whereIn('loan_status', ['Paid Off','outright_settled'])
            ->when($this->startDate && $this->endDate, function ($q) {
                $q->whereBetween('loan_release_date', [$this->startDate, $this->endDate]);
            })
            ->when($this->settlementStart && $this->settlementEnd, function ($q) {
                $q->whereBetween('updated_at', [$this->settlementStart, $this->settlementEnd]);
            })
            ->paginate(10);
    }

    public function updated($property): void
    {
        if (in_array($property, ['startDate', 'endDate', 'settlementStart', 'settlementEnd'], true)) {
            $this->previewRows = [];
            $this->resetPage();
        }
    }

    public function preview(): void
    {
        $this->validate([
            'startDate' => 'nullable|date',
            'endDate' => 'nullable|date|after_or_equal:startDate',
            'settlementStart' => 'nullable|date',
            'settlementEnd' => 'nullable|date|after_or_equal:settlementStart',
        ]);

        $this->previewRows = $this->getSettledLoansProperty()
            ->getCollection()
            ->all();
    }

    public function export()
    {
        $this->validate([
            'startDate' => 'nullable|date',
            'endDate' => 'nullable|date|after_or_equal:startDate',
            'settlementStart' => 'nullable|date',
            'settlementEnd' => 'nullable|date|after_or_equal:settlementStart',
        ]);

        return Excel::download(
            new OutrightSettlementsExport($this->startDate, $this->endDate, $this->settlementStart, $this->settlementEnd),
            'outright_settlements_report_' . now()->format('Y_m_d_H_i') . '.csv'
        );
    }
}
