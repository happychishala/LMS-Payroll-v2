<?php

namespace App\Exports;

use App\Services\CustomerDetailsQueryBuilder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CustomerDetailsExport implements FromCollection, WithHeadings
{
    public function __construct(
        protected ?string $employer,
        protected ?bool $offPayrollOnly,
        protected ?string $search
    ) {}

    public function collection(): Collection
    {
        return CustomerDetailsQueryBuilder::build(
            $this->employer,
            $this->offPayrollOnly,
            $this->search
        );
    }

    public function headings(): array
    {
        return [
            'Borrower ID',
            'Borrower Name',
            'DOB',
            'Gender',
            'NRC',
            'Address',
            'Phone',
            'Employer',
            'Employee No',
            'Next of Kin',
            'Next of Kin Phone',
            'Loan Status',
        ];
    }
}
