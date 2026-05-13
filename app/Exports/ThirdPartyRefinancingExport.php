<?php

namespace App\Exports;

use App\Services\ThirdPartyRefinancingQueryBuilder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ThirdPartyRefinancingExport implements FromCollection, WithHeadings
{
    public function __construct(
        protected ?string $startDate,
        protected ?string $endDate,
        protected ?string $employer,
        protected ?string $thirdPartyName,
        protected ?string $recency
    ) {}

    public function collection(): Collection
    {
        $rows = ThirdPartyRefinancingQueryBuilder::build(
            $this->startDate,
            $this->endDate,
            $this->employer,
            $this->thirdPartyName,
            $this->recency
        );

        $count            = $rows->count();
        $totalThirdParty  = (float) $rows->sum('Amount Paid to Third Party');
        $totalNetToBorrow = (float) $rows->sum('Net to Borrower');
        $totalDisbursed   = (float) $rows->sum('Disbursed Amount (Total)');

        // Append totals row
        $rows->push([
            'Disbursement Date'           => 'TOTALS',
            'Loan ID'                     => $count . ' loans',
            'Borrower ID'                 => null,
            'Borrower Name'               => null,
            'Employer'                    => null,
            'Loan Name'                   => null,
            'Third-Party Name(s)'         => null,
            'Amount Paid to Third Party'  => $totalThirdParty,
            'Net to Borrower'             => $totalNetToBorrow,
            'Disbursed Amount (Total)'    => $totalDisbursed,
            'Reference/POP No.'           => null,
            'Created By'                  => null,
            'First Payroll Receipt (Y/N)' => null,
            'DIA'                         => null,
            'VIA'                         => null,
            'Recency'                     => null,
        ]);

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Disbursement Date',
            'Loan ID',
            'Borrower ID',
            'Borrower Name',
            'Employer',
            'Loan Name',
            'Third-Party Name(s)',
            'Amount Paid to Third Party',
            'Net to Borrower',
            'Disbursed Amount (Total)',
            'Reference/POP No.',
            'Created By',
            'First Payroll Receipt (Y/N)',
            'DIA',
            'VIA',
            'Recency',
        ];
    }
}
