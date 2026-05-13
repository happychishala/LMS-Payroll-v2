<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Loan;
use App\Services\LoanStatementService;
use Barryvdh\DomPDF\Facade\Pdf;

class LoanStatementExportController extends Controller
{
    public function export()
    {
        $loanId = request('loan_id');
        $statement = app(LoanStatementService::class)->build($loanId);
        $loan = $statement['loan'];

        $pdf = Pdf::loadView('exports.loan-statement', compact('loan', 'statement'));

        return $pdf->download("Loan_{$loanId}_statement.pdf");
    }
}
