<?php

namespace App\Exports;

use App\Models\Loan;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OutrightSettlementsExport implements FromCollection, WithHeadings
{
    protected $startDate;
    protected $endDate;
    protected $settlementStart;
    protected $settlementEnd;

    public function __construct($startDate, $endDate, $settlementStart, $settlementEnd)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->settlementStart = $settlementStart;
        $this->settlementEnd = $settlementEnd;
    }

    public function collection(): Collection
    {
        return Loan::with('borrower', 'loan_type')
            ->where('loan_status', 'Paid Off')
            ->when($this->startDate && $this->endDate, function ($q) {
                $q->whereBetween('loan_release_date', [$this->startDate, $this->endDate]);
            })
            ->when($this->settlementStart && $this->settlementEnd, function ($q) {
                $q->whereBetween('updated_at', [$this->settlementStart, $this->settlementEnd]);
            })
            ->get()
            ->map(function ($loan) {
                return [
                    $loan->loan_id,
                    optional($loan->borrower)->full_name ?? ($loan->other_names . ' ' . $loan->last_name),
                    optional($loan->loan_type)->loan_name,
                    $loan->loan_category,
                    optional($loan->loan_release_date)?->format('Y-m-d'),
                    optional($loan->updated_at)?->format('Y-m-d'),
                    $loan->disbursement_amount,
                    $loan->balance,
                    $loan->employer,
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Loan ID',
            'Borrower Name',
            'Loan Name',
            'Loan Category',
            'Disbursement Date',
            'Settlement Date',
            'Disbursed Amount',
            'Balance',
            'Employer',
        ];
    }
}
