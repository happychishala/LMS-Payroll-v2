<?php

namespace App\Exports;

use App\Services\CrbInputReportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class CrbInputsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(
        protected string $reportDate,
        protected ?string $employer = null,
        protected bool $includeRecordsWithIssues = false,
    ) {}

    public function collection(): Collection
    {
        $rows = app(CrbInputReportService::class)->build(
            $this->reportDate,
            $this->employer,
            $this->includeRecordsWithIssues
        );

        return $rows->map(fn (array $row) => collect(CrbInputReportService::HEADINGS)
            ->map(fn (string $heading) => $row[$heading] ?? '')
            ->all());
    }

    public function headings(): array
    {
        return CrbInputReportService::HEADINGS;
    }

    public function title(): string
    {
        return 'Final';
    }
}
