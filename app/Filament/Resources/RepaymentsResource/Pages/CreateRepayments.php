<?php

namespace App\Filament\Resources\RepaymentsResource\Pages;

use App\Filament\Resources\RepaymentsResource;
use App\Models\Loan;
use App\Models\Repayments;
use App\Services\RepaymentAllocationService;
use App\Services\RepaymentScheduleService;
use App\Services\WithholdingService;
use Bavix\Wallet\Models\Wallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Carbon;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class CreateRepayments extends CreateRecord
{
    protected static string $resource = RepaymentsResource::class;

    /**
     * @param  array  $data
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function handleRecordCreation(array $data): Model
    {
        // Determine which key holds the Loan ID
        $loanId = $data['loan_id'] ?? $data['loan'] ?? null;
        if (! $loanId) {
            Notification::make()
                ->warning()
                ->title('Loan Required')
                ->body('Please select a loan before saving.')
                ->send();

            $this->halt();
        }

        // Fetch loan and wallet
        $loan = Loan::where('loan_id', $loanId)->firstOrFail();
        $wallet = Wallet::findOrFail($loan->from_this_account);

        $paymentAmt = (float) ($data['payments'] ?? 0);
        $receiptDate = Carbon::parse($data['receipt_date'] ?? now()->toDateString());
        $repaymentNumber = app(RepaymentScheduleService::class)
            ->computeRepaymentNumber($loan, $receiptDate);
        $repaymentNumber = app(RepaymentAllocationService::class)
            ->resolveRepaymentNumberForOutstandingInterest($loan, $repaymentNumber);

        $allocation = app(RepaymentAllocationService::class)->allocateForMonth(
            $loan,
            $paymentAmt,
            $repaymentNumber,
            $receiptDate,
            true,
        );

        $paidPrincipal = (float) ($allocation['paid_principal'] ?? 0);
        $paidInterest = (float) ($allocation['paid_interest'] ?? 0);
        $insurancePaid = (float) ($allocation['insurance_paid'] ?? 0);
        $openingBalance = (float) ($allocation['opening_balance'] ?? 0);
        $newBalance = (float) ($allocation['closing_balance'] ?? 0);
        $appliedAmount = (float) ($allocation['receipt_amount'] ?? $paymentAmt);
        $shouldClose = $newBalance <= 0.009;

        // Create the repayment record
        $repayment = Repayments::create([
            'loan_id' => $loanId,
            'employee_no' => $loan->employee_no,
            'nrc' => $loan->nrc,
            'receipt_date' => $receiptDate->toDateString(),
            'receipt_amount' => $appliedAmount,
            'paid_principal' => $paidPrincipal,
            'paid_interest' => $paidInterest,
            'insurance_paid' => $insurancePaid,
            'opening_balance' => $openingBalance,
            'closing_balance' => $newBalance,
            'payments' => $paymentAmt,
            'balance' => $newBalance,
            'repayment_number' => $repaymentNumber,
            'payments_method' => $data['payments_method'],
            'reference_number' => $data['reference_number'] 
                                   ?? 'No reference entered by '.auth()->user()->name,
            'loan_number' => $loan->loan_number,
            'principal' => $loan->principal_amount,
            'payment_date' => $receiptDate->toDateString(),
            'payment_status' => $shouldClose ? 'Paid' : 'Pending',
            'monthly_repayment_balance' => $loan->total_monthly_repayment ?? 0,
            'sanlam' => $loan->monthly_insurance ?? 0,
            'interest_rate' => $loan->interest_rate,
            'loan_amount' => $loan->principal_amount,
            'term' => $loan->loan_duration,
            'loan_issue_date' => $loan->loan_release_date,
            'employer' => $loan->borrower->employer ?? $loan->employer ?? null,
        ]);

        // Deposit into the wallet
        $wallet->deposit($paymentAmt, ['meta' => 'Loan repayment']);

        // If fully paid, generate settlement form and update loan
        if ($shouldClose) {
            $data['loan_settlement_file_path'] = $this->settlement_form($loan);

            $loan->update([
                'balance' => 0,
                'loan_status' => 'Closed',
                'loan_settlement_file_path' => $data['loan_settlement_file_path'],
            ]);
        } else {
            $loan->update([
                'balance' => $newBalance,
                'loan_status' => 'partially_paid',
            ]);
        }

        app(WithholdingService::class)->syncFromRepayment($loan->load('repayments', 'loan_type'), $repayment);

        return $repayment;
    }

    /**
     * Generate and save the loan settlement Word document.
     */
    protected function settlement_form(Loan $loan): string
    {
        $borrower        = \App\Models\Borrower::findOrFail($loan->borrower_id);
        $companyName     = env('APP_NAME');
        $companyAddress  = 'Lusaka, Zambia';
        $borrowerName    = $borrower->first_name.' '.$borrower->last_name;
        $borrowerContact = $borrower->mobile ?? '';
        $loanAmount      = $loan->repayment_amount;
        $settledDate     = date('d, F Y');
        $currentDate     = date('d, F Y');

        // Load your latest template
        $templateContent = \App\Models\LoanSettlementForms::latest()
            ->first()
            ->loan_settlement_text;

        // Replace placeholders
        $placeholders = [
            '{company_name}'    => $companyName,
            '{company_address}' => $companyAddress,
            '{customer_name}'   => $borrowerName,
            '{customer_address}'=> $borrowerContact,
            '{loan_amount}'     => $loanAmount,
            '{settled_date}'    => $settledDate,
            '{current_date}'    => $currentDate,
        ];
        $content = strtr($templateContent, $placeholders);
        $content = str_replace(['<br>', '&nbsp;'], '', $content);

        // Create Word document
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        \PhpOffice\PhpWord\Shared\Html::addHtml($section, $content, false, false);

        $year = date('Y');
        $dir  = public_path("LOAN_SETTLEMENT_FORMS/{$year}/DOCX");
        if (! file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        $fileName = Str::random(40).'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')
            ->save("{$dir}/{$fileName}");

        return "LOAN_SETTLEMENT_FORMS/{$year}/DOCX/{$fileName}";
    }

    protected function getRedirectUrl(): string
    {
        // Avoid using getUrl('index') which relies on non-existent route
        // Instead, redirect back to the resource index page directly
        $panelPath = trim(config('filament.path', 'admin'), '/');
        return url($panelPath ?: '/').'/resources/repayments';
    }
}
