<?php
namespace App\Observers;

use App\Models\Loan;
use App\Services\WithholdingService;

class LoanObserver
{
    public function created(Loan $loan)
    {
        app(WithholdingService::class)->syncForLoan($loan->load('borrower', 'loan_type'));
    }
}
