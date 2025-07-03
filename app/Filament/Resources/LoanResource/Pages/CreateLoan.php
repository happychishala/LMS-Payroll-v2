<?php

namespace App\Filament\Resources\LoanResource\Pages;

use Illuminate\Support\Facades\Log;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Bavix\Wallet\Models\Wallet;
use Haruncpi\LaravelIdGenerator\IdGenerator;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use App\Filament\Resources\LoanResource;
use App\Models\LoanAgreementForms;
use App\Models\LoanType;
use App\Models\ThirdParty;
use App\Models\Borrower;
use App\Notifications\LoanStatusNotification;



class CreateLoan extends CreateRecord
{
    protected static string $resource = LoanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-generate loan and transaction reference numbers
        $data['loan_number'] = IdGenerator::generate([
            'table'  => 'loans',
            'field'  => 'loan_number',
            'length' => 12,
            'prefix' => 'LN-',
        ]);
        $data['transaction_reference'] = 'TRX-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6));
        $data['monthly_insurance']        = $data['monthly_insurance'] ?? 0;
        $data['total_monthly_repayment']  = $data['total_monthly_repayment'] ?? 0;
        // Ensure disbursement_amount is passed through
$data['disbursement_amount'] = $data['disbursement_amount'] ?? 0;



        // Validate Loan Agreement Form template if requested
        if (! LoanAgreementForms::where('loan_type_id', $data['loan_type_id'])->exists()
            && ! empty($data['activate_loan_agreement_form'])
        ) {
            Notification::make()
                ->warning()
                ->title('Missing Agreement Template')
                ->body('Create a template first to compile the Loan Agreement Form.')
                ->persistent()
                ->actions([
                    Action::make('create_template')
                        ->label('New Template')
                        ->url(route('filament.admin.resources.loan-agreement-forms.create'), shouldOpenInNewTab: true),
                ])
                ->send();
            // Halt creation
            $this->halt();
        }

        // Cast numeric fields
        $data['principal_amount'] = (float) str_replace(',', '', $data['principal_amount']);
        $data['repayment_amount'] = (float) str_replace(',', '', $data['repayment_amount']);
        $data['interest_amount']  = (float) str_replace(',', '', $data['interest_amount']);
        $data['balance']          = $data['repayment_amount'];

        // Calculate due date based on cycle
        $loanType     = LoanType::findOrFail($data['loan_type_id']);
        $loanCycle    = $loanType->interest_cycle;
        $releaseDate  = Carbon::parse($data['loan_release_date']);
        $duration     = (int) $data['loan_duration'];

        switch ($loanCycle) {
            case 'day(s)':   $due = $releaseDate->addDays($duration);  break;
            case 'week(s)':  $due = $releaseDate->addWeeks($duration); break;
            case 'month(s)': $due = $releaseDate->addMonths($duration); break;
            case 'year(s)':  $due = $releaseDate->addYears($duration);  break;
            default:         $due = $releaseDate;                     break;
        }
        $data['loan_due_date'] = $due->toDateString();

        // Deduct funds if approved
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

        // Send SMS notification if configured
        $this->sendSmsNotification($data);

        // Send email notification
        $this->sendEmailNotification($data);

        // Compile Loan Agreement if requested
        if ($data['loan_status'] === 'approved' && ! empty($data['activate_loan_agreement_form'])) {
            $data['loan_agreement_file_path'] = $this->buildAgreementForm($data);
        }

        return $data;
    }

    protected function sendSmsNotification(array $data): void
    {
        $config   = ThirdParty::where('name', 'SWIFT-SMS')->latest()->first();
        $borrower = Borrower::find($data['borrower_id']);
        if (! $config || $config->is_active !== 'Active' || empty($borrower->mobile)) {
            return;
        }

        $message = $this->buildStatusMessage($data, $borrower);
        Http::withHeaders([
            'Authorization' => 'Bearer ' . $config->token,
            'Accept'        => 'application/json',
        ])->post($config->base_uri . $config->endpoint, [
            'sender_id' => $config->sender_id,
            'numbers'   => $borrower->mobile,
            'message'   => $message,
        ]);
    }

    protected function sendEmailNotification(array $data): void
    {
        $borrower = Borrower::find($data['borrower_id']);
        if (empty($borrower->email)) {
            return;
        }

        $message = $this->buildStatusMessage($data, $borrower);

        try {
            $borrower->notify(new LoanStatusNotification($message));
        } catch (\Throwable $e) {
            Log::warning('Email send failed: ' . $e->getMessage());
            Notification::make()
                ->warning()
                ->title('Email Send Failed')
                ->body('Could not send loan status email: ' . $e->getMessage())
                ->send();
        }
    }

    protected function buildStatusMessage(array $data, $borrower): string
    {
        $loanStatus = $data['loan_status'];
        $amt        = $data['principal_amount'];
        $repay      = $data['repayment_amount'];
        $dur        = $data['loan_duration'];
        $cycle      = LoanType::find($data['loan_type_id'])->interest_cycle;

        switch ($loanStatus) {
            case 'approved':
                return "Hi {$borrower->first_name}, your K{$amt} loan was approved. Total repay K{$repay} in {$dur} {$cycle}.";
            case 'processing':
                return "Hi {$borrower->first_name}, your K{$amt} loan is under review.";
            case 'denied':
                return "Hi {$borrower->first_name}, we regret your K{$amt} loan was denied.";
            case 'defaulted':
                return "Hi {$borrower->first_name}, your loan is in default status.";
            default:
                return "Hi {$borrower->first_name}, status: {$loanStatus} for your loan.";
        }
    }

    protected function buildAgreementForm(array $data): string
    {
        $template = LoanAgreementForms::where('loan_type_id', $data['loan_type_id'])->first();
        $content  = $template->loan_agreement_text;

        // Replace placeholders
        $replacements = [
            '[Loan Number]'               => $data['loan_number'],
            '[Borrower Name]'             => Borrower::find($data['borrower_id'])->full_name,
            '[Loan Amount]'               => $data['principal_amount'],
            '[Loan Repayment Amount]'     => $data['repayment_amount'],
            '[Loan Due Date]'             => $data['loan_due_date'],
            // add more as needed...
        ];
        $content = str_replace(array_keys($replacements), array_values($replacements), $content);

        // Generate Word document
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        \PhpOffice\PhpWord\Shared\Html::addHtml($section, $content, false, false);

        $year = now()->year;
        $dir  = public_path("LOAN_AGREEMENT_FORMS/{$year}/DOCX");
        if (! file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        $fileName = Str::random(40) . '.docx';
        $path     = "{$dir}/{$fileName}";

        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return "LOAN_AGREEMENT_FORMS/{$year}/DOCX/{$fileName}";
    }
}
