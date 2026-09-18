<?php

namespace App\Filament\Resources\LoanResource\Pages;

use App\Filament\Resources\LoanResource;
use App\Models\Borrower;
use App\Models\LoanAgreementForms;
use App\Models\LoanType;
use Bavix\Wallet\Models\Wallet;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use App\Services\WithholdingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use App\Notifications\LoanStatusNotification;
use App\Services\LoanApplicationPdfService;
use App\Services\LoanApprovalAlertService;

class CreateLoan extends CreateRecord
{
    protected static string $resource = LoanResource::class;

    /**
     * Normalize & adjust form data before saving.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        //
        // 1) Normalize third-party repeater entries
        //
        $thirdParties = LoanResource::normalizeThirdParties($data['third_parties'] ?? []);

        $this->guardLoanCategoryRules((int) ($data['borrower_id'] ?? 0), (string) ($data['loan_category'] ?? ''), $thirdParties);

        $data = LoanResource::fillThirdPartyColumns($data, $thirdParties);
        unset($data['third_parties']);

        //
        // 2) Sum all third-party balances
        //
        $thirdTotal = (float) ($data['total_third_party_balance'] ?? 0);

        //
        // 3) Generate loan & transaction references
        //
        $nextLoanIdentifier = LoanResource::generateNextLoanIdentifier();
        $data['loan_id'] = $nextLoanIdentifier;
        $data['loan_number'] = $nextLoanIdentifier;
        $data['transaction_reference'] = 'TRX-'
            . now()->format('YmdHis')
            . '-' . Str::upper(Str::random(6));

        //
        // 4) Cast core numeric fields
        //
        $data['principal_amount']        = (float) str_replace(',', '', $data['principal_amount']);
        $data['repayment_amount']        = (float) str_replace(',', '', $data['repayment_amount']);
        $data['interest_amount']         = (float) str_replace(',', '', $data['interest_amount']);
        $data['monthly_insurance']       = $data['monthly_insurance'] ?? 0;
        $data['total_monthly_repayment'] = $data['total_monthly_repayment'] ?? 0;
        $data['balance'] = $data['balance'] ?? $data['principal_amount'];

        //
        // 5) Recompute disbursement including third-party total
        //
        $loanType      = LoanType::findOrFail($data['loan_type_id']);
        $loanName      = strtolower($loanType->loan_name);
        $data['loan_category'] = $data['loan_category'] ?? $loanType->loan_name;
        $data['withholding_amount'] = LoanResource::isGrzRefinance($loanType->loan_name ?? null, $thirdParties)
            ? (float) $data['total_monthly_repayment']
            : 0;
        [
            'admin_fee' => $adminFee,
            'insurance_fee' => $insuranceFee,
            'arrangement_fee' => $arrangementFee,
        ] = LoanResource::calculateFeeComponents(
            $loanName,
            $data['principal_amount'],
            $data['repayment_amount'],
            (int) $data['loan_duration'],
            (float) ($data['total_monthly_repayment'] ?? 0),
        );
        $crbFee        = 60;
        $data['disbursement_amount'] = round(
            $data['principal_amount']
          - ($adminFee + $arrangementFee + $crbFee + $thirdTotal),
            2
        );

        //
        // 6) Compute due date
        //
        $releaseDate = Carbon::parse($data['loan_release_date']);
        $duration    = (int) $data['loan_duration'];
        switch ($loanType->interest_cycle) {
            case 'day(s)':   $due = $releaseDate->addDays($duration);   break;
            case 'week(s)':  $due = $releaseDate->addWeeks($duration);  break;
            case 'month(s)': $due = $releaseDate->addMonths($duration); break;
            case 'year(s)':  $due = $releaseDate->addYears($duration);  break;
            default:         $due = $releaseDate;                       break;
        }
        $data['loan_due_date'] = $due->toDateString();
        $data['maturity_date'] = $data['loan_due_date'];

        //
        // 7) Disburse if approved
        //
        $wallet = Wallet::findOrFail($data['from_this_account']);
        if ($data['loan_status'] === 'approved') {
            try {
                $wallet->withdraw($data['principal_amount'], [
                    'meta' => 'Disbursed via ' . $data['transaction_reference'],
                ]);
            } catch (\Exception $e) {
                Notification::make()
                    ->danger()
                    ->title('Disbursement Error')
                    ->body($e->getMessage())
                    ->persistent()
                    ->send();
                $this->halt();
            }
        }

        //
        // 8) Send notifications
        //
       // $this->sendSmsNotification($data);
        //$this->sendEmailNotification($data);

        return $data;
    }

    protected function guardLoanCategoryRules(int $borrowerId, string $loanCategory, array $thirdParties): void
    {
        if (! $borrowerId || $loanCategory === '') {
            return;
        }

        if (
            $loanCategory === 'Refinancing Loan'
            && LoanResource::thirdPartyTotal($thirdParties) <= 0
        ) {
            Notification::make()
                ->warning()
                ->title('Third-party balance required')
                ->body('Refinancing Loan can only be selected if a third-party balance is added.')
                ->persistent()
                ->send();

            $this->halt();
        }

        if (
            $loanCategory === 'Consumer Loan'
            && LoanResource::hasRunningLoanForCategory($borrowerId, 'Consumer Loan')
        ) {
            Notification::make()
                ->warning()
                ->title('Consumer loan already running')
                ->body('This customer can only have one running Consumer Loan at a time.')
                ->persistent()
                ->send();

            $this->halt();
        }

        if (
            $loanCategory === 'Educational Loan'
            && LoanResource::hasRunningLoanForCategory($borrowerId, 'Educational Loan')
        ) {
            Notification::make()
                ->warning()
                ->title('Educational loan already running')
                ->body('This customer already has a running Educational Loan.')
                ->persistent()
                ->send();

            $this->halt();
        }
    }

    protected function afterCreate(): void
    {
        app(WithholdingService::class)->syncForLoan($this->record->load('borrower', 'loan_type'));
        app(LoanApprovalAlertService::class)->alertApprovers($this->record->fresh(['borrower', 'loan_type']));

        if (! $this->record->activate_loan_agreement_form) {
            return;
        }

        $loanApplicationPath = app(LoanApplicationPdfService::class)->generate($this->record);

        $this->record->forceFill([
            'loan_application_file_path' => $loanApplicationPath,
        ])->saveQuietly();

        Notification::make()
            ->success()
            ->title('Loan application PDF generated')
            ->body('Open the printable loan application, get it signed, then upload the signed copy under Supporting Documents.')
            ->send();
    }
    

    // … existing sendSmsNotification, sendEmailNotification, buildStatusMessage, buildAgreementForm …
}

    /**
     * Build the loan agreement document.
     */
    
