<?php

namespace App\Exports;

use App\Models\Loan;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class NewLoansExport implements FromCollection, WithHeadings
{
    protected ?string $loanName;
    protected ?string $employer;
    protected ?string $month;
    protected ?string $startDate;
    protected ?string $endDate;

    public function __construct(?string $loanName, ?string $employer, ?string $month, ?string $startDate, ?string $endDate)
    {
        $this->loanName  = $loanName;
        $this->employer  = $employer;
        $this->month     = $month;
        $this->startDate = $startDate;
        $this->endDate   = $endDate;
    }

    public function collection(): Collection
    {
        $q = Loan::with(['borrower', 'loan_type']);

        // Filters (all optional)
        if ($this->loanName) {
            $q->whereHas('loan_type', fn($x) => $x->where('loan_name', $this->loanName));
        }

        if ($this->employer) {
            $q->where('employer', $this->employer);
        }

        // Date filter: month takes precedence; otherwise use range
        if ($this->month) {
            $year  = substr($this->month, 0, 4);
            $month = substr($this->month, 5, 2);
            $q->whereYear('loan_release_date', $year)
              ->whereMonth('loan_release_date', $month);
        } elseif ($this->startDate && $this->endDate) {
            $q->whereBetween('loan_release_date', [$this->startDate, $this->endDate]);
        } else {
            // No date input — return empty
            return collect();
        }

        return $q->orderBy('loan_release_date', 'asc')
            ->get()
            ->map(function ($loan) {
                $borrowerName = trim(($loan->other_names ?? '') . ' ' . ($loan->last_name ?? ''));

                return [
                    $loan->loan_id ?? 'N/A',                                // Loan ID
                    $borrowerName ?: 'N/A',                                 // Borrower Name
                    optional($loan->loan_type)->loan_name ?? 'Unknown',     // Loan Name
                    $loan->loan_category ?? 'N/A',                          // Loan Category
                    optional($loan->loan_release_date)?->format('Y-m-d'),   // Disbursement Date
                    number_format($loan->disbursement_amount ?? 0, 2),      // Disbursed Amount
                    $loan->third_party_name ?? 'N/A',                       // Third Party Name
                    number_format($loan->total_third_party_balance ?? 0, 2), // Third Party Balance
                    number_format($loan->admin_fee ?? 0, 2),                // Admin Fee
                    number_format($loan->insurance_fee ?? 0, 2),            // Insurance Fee
                    number_format($loan->arrangement_fee ?? 0, 2),          // Arrangement Fee
                    number_format(($loan->admin_fee ?? 0) + ($loan->insurance_fee ?? 0) + ($loan->arrangement_fee ?? 0), 2), // Total Fees Charged
                    number_format($loan->total_monthly_repayment ?? 0, 2),  // Monthly Instalment (total)
                    number_format($loan->repayment_amount ?? 0, 2),         // Instalment (excl. insurance/admin)
                    number_format($loan->monthly_insurance ?? 0, 2),        // Monthly Insurance
                    $loan->employer ?? 'N/A',                               // Employer
                    $loan->employee_no ?? 'N/A',                            // Employee Number
                    $loan->loan_status ?? 'N/A',                            // Status
                    $loan->interest_rate ?? 0,                              // Interest Rate
                    $loan->term_months ?? $loan->loan_duration ?? 'N/A',    // Term
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
            'Disbursed Amount',
            'Third Party Name',
            'Third Party Balance',
            'Admin Fee',
            'Insurance Fee',
            'Arrangement Fee',
            'Total Fees Charged',
            'Monthly Instalment (Total)',
            'Instalment (Excl. Insurance)',
            'Monthly Insurance',
            'Employer',
            'Employee Number',
            'Status',
            'Interest Rate',
            'Term (Months)',
        ];
    }
}
