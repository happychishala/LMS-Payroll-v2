<?php

namespace App\Filament\Pages;

use App\Exports\CrbInputsExport;
use App\Models\Loan;
use App\Services\CrbInputReportService;
use Filament\Pages\Page;
use Maatwebsite\Excel\Facades\Excel;

class CrbInputsReport extends Page
{
    protected static ?string $navigationGroup = 'Reports';
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?int $navigationSort = 70;
    protected static string $view = 'filament.pages.crb-inputs-report';
    protected static ?string $title = 'CRB Inputs';

    public ?string $reportDate = '';
    public ?string $employer = '';
    public bool $includeRecordsWithIssues = false;
    protected ?array $cachedReportData = null;

    public function mount(): void
    {
        $this->reportDate = now()->toDateString();
    }

    public function updated($name, $value): void
    {
        if (in_array($name, ['reportDate', 'employer', 'includeRecordsWithIssues'], true)) {
            $this->cachedReportData = null;
        }
    }

    public function getEmployersProperty()
    {
        return Loan::query()
            ->whereNotNull('employer')
            ->distinct()
            ->orderBy('employer')
            ->pluck('employer');
    }

    public function getPreviewRowsProperty()
    {
        return $this->getReportData()['rows']->take(20)->values();
    }

    public function getIssueRowsProperty()
    {
        return $this->getReportData()['issues']->take(50)->values();
    }

    public function getSummaryProperty(): array
    {
        return $this->getReportData()['summary'];
    }

    public function export()
    {
        $this->validate([
            'reportDate' => 'required|date',
            'employer' => 'nullable|string',
            'includeRecordsWithIssues' => 'nullable|boolean',
        ]);

        return Excel::download(
            new CrbInputsExport(
                $this->reportDate,
                $this->employer ?: null,
                $this->includeRecordsWithIssues
            ),
            'crb_inputs_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    protected function getReportData(): array
    {
        return $this->cachedReportData ??= app(CrbInputReportService::class)->report(
            $this->reportDate ?: now()->toDateString(),
            $this->employer ?: null,
            $this->includeRecordsWithIssues
        );
    }
}
