<?php

namespace App\Exports;

use App\Services\LoanBookQueryBuilder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LoanBookExport implements FromCollection, WithHeadings
{
    public function __construct(
        protected ?string $asOfDate,
        protected ?string $employer,
        protected ?string $loanName,
        protected ?string $status
    ) {}

    public function collection(): Collection
    {
        return LoanBookQueryBuilder::build(
            $this->asOfDate,
            $this->employer,
            $this->loanName,
            $this->status
        );
    }

    public function headings(): array
    {
        return [
            'Loan ID',
            'Borrower ID',
            'Borrower Name',
            'Employer',
            'Loan Name',
            'Issue Date',
            'Loan Cycle',
            'Original Term (Months)',
            'Monthly Instalment',
            'Principal Outstanding',
            'Principal + Interest (GRZ)',
            'Current Total Recoverable',
            'Remaining Term (Recalc)',
            'Last Payment Date',
            'DIA',
            'VIA',
            'Recency',
            'Status',
        ];
    }
}
