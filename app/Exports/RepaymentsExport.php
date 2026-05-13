<?php

namespace App\Exports;

use App\Models\Repayments;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RepaymentsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    use Exportable;

    public function __construct(private readonly Builder $query)
    {
    }

    public function query(): Builder
    {
        return clone $this->query;
    }

    public function headings(): array
    {
        return [
            'Loan ID',
            'Employee No',
            'Loan Release Date',
            'Employer',
            'Receipt Date',
            'Receipt Amount',
            'Payment Type',
            'Principal',
            'Interest',
            'Insurance',
            'Balance',
            'Status',
            'Reference Number',
        ];
    }

    public function map($row): array
    {
        return [
            $row->loan_number,
            $row->employee_no,
            optional($row->loan_issue_date)->toDateString(),
            $row->employer,
            optional($row->receipt_date)->toDateString(),
            (float) ($row->receipt_amount ?? 0),
            $row->payments_method,
            (float) ($row->paid_principal ?? 0),
            (float) ($row->paid_interest ?? 0),
            (float) ($row->insurance_paid ?? 0),
            (float) ($row->closing_balance ?? 0),
            $row->payment_status,
            $row->reference_number,
        ];
    }
}
