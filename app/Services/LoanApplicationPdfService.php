<?php

namespace App\Services;

use App\Models\Loan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LoanApplicationPdfService
{
    public function generate(Loan $loan): string
    {
        $loan->loadMissing('borrower', 'loan_type');

        $pdf = Pdf::loadView('exports.loan-application', [
            'loan' => $loan,
            'borrower' => $loan->borrower,
            'loanType' => $loan->loan_type,
            'generatedAt' => now(),
        ])->setPaper('a4');

        $relativePath = sprintf(
            'loan-application-forms/%s/%s.pdf',
            now()->format('Y'),
            Str::slug((string) ($loan->loan_id ?: $loan->loan_number ?: 'loan-application')) . '_' . now()->format('Ymd_His')
        );

        if (filled($loan->loan_application_file_path) && Storage::disk('public')->exists($loan->loan_application_file_path)) {
            Storage::disk('public')->delete($loan->loan_application_file_path);
        }

        Storage::disk('public')->put($relativePath, $pdf->output());

        return $relativePath;
    }
}
