<?php

namespace App\Exports;

use App\Models\Loan;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Carbon;
use App\Services\EmployerCsvReportQueryBuilder;

class EmployerCsvExport implements FromCollection, WithHeadings
{
    protected $loanName;
    protected $month;
    protected $startDate;
    protected $endDate;

    public function __construct(string $loanName, string $month, ?string $startDate = null, ?string $endDate = null)
    {
        $this->loanName = $loanName;
        $this->month = $month;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection(): Collection
    {
        return EmployerCsvReportQueryBuilder::build(
            $this->loanName,
            $this->month,
            $this->startDate,
            $this->endDate
        );
    }

    public function headings(): array
    {
        return [
            'Loan Name',
            'Employer Code',
            'Borrower ID',
            'Employee Number',
            'Borrower Name',
            'Loan ID',
            'Loan Type',
            'Instalment Amount',
            'Instalment Month',
            'Outstanding Principal',
            'Outstanding P+I',
            'DIA',
            'VIA',
            'Status',
            'Cycle',
        ];
    }
}
