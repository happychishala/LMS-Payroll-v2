<?php

namespace App\Filament\Resources\LoanResource\Pages;

use App\Models\Borrower;
use App\Models\LoanType;
use App\Models\ThirdParty;
use App\Services\LoanApplicationPdfService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Filament\Resources\LoanResource;
use Haruncpi\LaravelIdGenerator\IdGenerator;
use Bavix\Wallet\Models\Wallet;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Filament\Resources\Pages\CreateRecord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Carbon\Carbon;
use App\Notifications\LoanStatusNotification;
use App\Services\LoanApprovalAlertService;
use App\Models\StatusReason;
use App\Services\StatusReasonAuthorization;
use App\Services\StatusReasonService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;


class EditLoan extends EditRecord
{
    protected static string $resource = LoanResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['third_parties'] = LoanResource::thirdPartyRepeaterState($this->record);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('openLoanApplication')
                ->label('Loan Application PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->visible(fn (): bool => filled($this->record->loan_application_file_path))
                ->url(fn (): string => Storage::disk('public')->url($this->record->loan_application_file_path))
                ->openUrlInNewTab(),
            Actions\Action::make('exceptionalApproval')
                ->label('Exceptional Approval')
                ->icon('heroicon-o-paper-clip')
                ->color('warning')
                ->modalHeading('Exceptional Approval')
                ->modalDescription('Attach the approval email screenshot for this loan.')
                ->fillForm(fn (): array => [
                    'exceptional_approval' => (bool) $this->record->exceptional_approval,
                    'exceptional_approval_email_screenshot_path' => $this->record->exceptional_approval_email_screenshot_path,
                ])
                ->form([
                    Toggle::make('exceptional_approval')
                        ->label('Exceptional Approval')
                        ->helperText('Turn this on only when this loan has been exceptionally approved.')
                        ->live(),
                    FileUpload::make('exceptional_approval_email_screenshot_path')
                        ->label('Approval Email Screenshot')
                        ->disk('public')
                        ->directory('exceptional-approval-emails')
                        ->visibility('public')
                        ->acceptedFileTypes([
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ])
                        ->maxSize(10240)
                        ->helperText('Upload the email screenshot confirming exceptional approval.')
                        ->openable()
                        ->downloadable()
                        ->required(fn (Get $get): bool => (bool) $get('exceptional_approval'))
                        ->visible(fn (Get $get): bool => (bool) $get('exceptional_approval')),
                ])
                ->action(function (array $data): void {
                    $this->record->forceFill([
                        'exceptional_approval' => (bool) ($data['exceptional_approval'] ?? false),
                        'exceptional_approval_email_screenshot_path' => $data['exceptional_approval_email_screenshot_path'] ?? null,
                    ])->save();

                    Notification::make()
                        ->success()
                        ->title('Exceptional approval saved')
                        ->send();
                }),
            Actions\Action::make('assignStatusReason')
                ->label($this->record->statusReason ? 'Change Status Reason' : 'Assign Status Reason')
                ->icon('heroicon-o-tag')
                ->color('warning')
                ->visible(fn (): bool => StatusReasonAuthorization::canAssign(auth()->user()))
                ->form([
                    Select::make('status_reason_id')
                        ->label('Status Reason')
                        ->options(fn () => StatusReason::query()->where('is_active', true)->orderBy('code')->get()->mapWithKeys(
                            fn (StatusReason $statusReason) => [$statusReason->id => "{$statusReason->code} - {$statusReason->label}"]
                        )->all())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required(),
                    Placeholder::make('status_reason_flags')
                        ->label('Rule Summary')
                        ->content(function (Get $get): string {
                            $statusReason = StatusReason::find($get('status_reason_id'));

                            if (! $statusReason) {
                                return 'Select a code to review its flags.';
                            }

                            return implode(' | ', array_filter([
                                $statusReason->suspend_submissions ? 'Suspend Submissions' : null,
                                $statusReason->client_may_replace ? 'Client May Replace' : null,
                                $statusReason->suspend_interest ? 'Suspend Interest' : null,
                                $statusReason->triggers_insurance_claim ? 'Triggers Insurance' : null,
                                $statusReason->blocks_new_loan ? 'Blocks New Loans' : null,
                            ])) ?: 'No system flags.';
                        }),
                    DatePicker::make('effective_date')
                        ->default(now()->toDateString())
                        ->required(),
                    Select::make('mode_of_exit')
                        ->options(StatusReason::modeOfExitOptions())
                        ->visible(fn (Get $get): bool => (bool) optional(StatusReason::find($get('status_reason_id')))->requires_mode_of_exit),
                    Select::make('affordability_reason')
                        ->options(StatusReason::affordabilityReasonOptions())
                        ->visible(fn (Get $get): bool => (bool) optional(StatusReason::find($get('status_reason_id')))->requires_affordability_reason)
                        ->live(),
                    Toggle::make('management_approval_confirmed')
                        ->label('Management Approval Confirmed')
                        ->visible(fn (Get $get): bool => (bool) optional(StatusReason::find($get('status_reason_id')))->requires_management_approval),
                    Textarea::make('notes')
                        ->rows(3)
                        ->helperText('Required when affordability reason is Other.')
                        ->required(fn (Get $get): bool => $get('affordability_reason') === StatusReasonService::OTHER_AFFORDABILITY_REASON),
                ])
                ->action(function (array $data, StatusReasonService $service): void {
                    $statusReason = StatusReason::findOrFail($data['status_reason_id']);

                    $service->assign($this->record->loadMissing('borrower', 'statusReason'), $statusReason, $data, auth()->user());

                    Notification::make()
                        ->success()
                        ->title('Status reason saved')
                        ->send();
                }),
            Actions\Action::make('removeStatusReason')
                ->label('Remove Status Reason')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => StatusReasonAuthorization::canRemove(auth()->user()) && filled($this->record->status_reason_id))
                ->form([
                    DatePicker::make('effective_date')
                        ->default(now()->toDateString())
                        ->required(),
                    Textarea::make('removal_reason')
                        ->required()
                        ->rows(3),
                    Textarea::make('notes')
                        ->rows(3),
                ])
                ->action(function (array $data, StatusReasonService $service): void {
                    $service->remove($this->record->loadMissing('borrower', 'statusReason'), $data, auth()->user());

                    Notification::make()
                        ->success()
                        ->title('Status reason removed')
                        ->send();
                }),
            Actions\ViewAction::make(),
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $shouldGenerateLoanApplication = (bool) ($data['activate_loan_agreement_form'] ?? false);
        $previousLoanStatus = $record->loan_status;

        $thirdParties = LoanResource::normalizeThirdParties($data['third_parties'] ?? []);
        $data = LoanResource::fillThirdPartyColumns($data, $thirdParties);
        unset($data['third_parties']);

        $loan = \App\Models\Loan::where('loan_number',"=",$data['loan_number'])->first();
        $data['monthly_insurance']        = $data['monthly_insurance'] ?? $record->monthly_insurance;
        $data['total_monthly_repayment']  = $data['total_monthly_repayment'] ?? $record->total_monthly_repayment;
        $data['disbursement_amount'] = $data['disbursement_amount']
    ?? $record->disbursement_amount;


//Disable editing an already approved loan
        if ($loan->loan_status == 'approved') {
            Notification::make()
                ->warning()
                ->title('Loan already approved')
                ->body('You cant edit an already approved loan')
                ->persistent()
                 ->send();

            $this->halt();
        }





        // In handleRecordUpdate(), grab the Wallet by its ID
            $wallet = Wallet::findOrFail($data['from_this_account']);
        // Check if the loan is being approved and they want to compile the Loan Agreement Form
        if ($data['loan_status'] === 'approved') {


//  // Remove the amount from the Specified Wallet if the wallet was not approved otherwise if the wallet was approved don't remove the funds
// if($loan->loan_status != 'approved'){
//     $wallet->withdraw($data['principal_amount'], ['meta' => 'Loan amount disbursed from ' . $data['from_this_account']]);
// }




            //Check if they have the Loan Agreement Form template for this type of loan
            $loan_agreement_text = \App\Models\LoanAgreementForms::where('loan_type_id', "=", $data['loan_type_id'])->first();
            if (!$loan_agreement_text && $data['activate_loan_agreement_form'] == 1) {
                Notification::make()
                    ->warning()
                    ->title('Invalid Agreement Form!')
                    ->body('Please create a template first if you want to compile the Loan Agreement Form')
                    ->persistent()
                    ->actions([
                        Action::make('create')
                            ->button()
                            ->url(route('filament.admin.resources.loan-agreement-forms.create'), shouldOpenInNewTab: true),
                    ])
                    ->send();

                $this->halt();
            } else {


               //  $data['loan_number'] = IdGenerator::generate(['table' => 'loans', 'field' => 'loan_number', 'length' => 10, 'prefix' => 'LN-']);




                $loanTypeModel = LoanType::findOrFail($data['loan_type_id']);
                $loan_cycle = $loanTypeModel->interest_cycle;

                $loan_duration = (int) $data['loan_duration'];
                $loan_release_date = (string) $data['loan_release_date'];
                $data['loan_due_date'] = LoanResource::computeLoanDueDateValue($loan_cycle, $loan_release_date, $loan_duration);
                $data['maturity_date'] = $data['loan_due_date'];

                $borrower = Borrower::findOrFail($data['borrower_id']);
                $loan_type = $loanTypeModel;

                $company_name = env('APP_NAME');
                $borrower_name = $borrower->first_name . ' ' . $borrower->last_name;
                $borrower_email = $borrower->email ?? '';
                $borrower_phone = $borrower->mobile ?? '';
                $loan_name = $loan_type->loan_name;
                $loan_interest_rate = $data['interest_rate'];
                $loan_amount = $data['principal_amount'];
                $loan_duration = $data['loan_duration'];
                $loan_release_date = $data['loan_release_date'];
                $loan_repayment_amount = $data['repayment_amount'];
                $loan_interest_amount = $data['interest_amount'];
                $loan_due_date = $data['loan_due_date'];
                $loan_number = $data['loan_number'];
                // The original content with placeholders
                if (($data['activate_loan_agreement_form'] ?? 0) == 1 && $data['loan_status'] === 'approved') {
                    $template_content = $loan_agreement_text?->loan_agreement_text;

                    if (! $template_content) {
                        Notification::make()
                            ->warning()
                            ->title('Invalid Agreement Form!')
                            ->body('Please create a template first if you want to compile the Loan Agreement Form')
                            ->persistent()
                            ->actions([
                                Action::make('create')
                                    ->button()
                                    ->url(route('filament.admin.resources.loan-agreement-forms.create'), shouldOpenInNewTab: true),
                            ])
                            ->send();

                        $this->halt();
                    }


                    // Replace placeholders with actual data
                    $template_content = str_replace('[Company Name]', $company_name, $template_content);
                    $template_content = str_replace('[Borrower Name]', $borrower_name, $template_content);
                    $template_content = str_replace('[Loan Tenure]', $loan_duration, $template_content);
                    $template_content = str_replace('[Loan Interest Percentage]', $loan_interest_rate, $template_content);
                    $template_content = str_replace('[Loan Interest Fee]', $loan_interest_amount, $template_content);
                    $template_content = str_replace('[Loan Amount]', $loan_amount, $template_content);
                    $template_content = str_replace('[Loan Repayments Amount]', $loan_repayment_amount, $template_content);
                    $template_content = str_replace('[Loan Due Date]', $loan_due_date, $template_content);
                    $template_content = str_replace('[Borrower Email]', $borrower_email, $template_content);
                    $template_content = str_replace('[Borrower Phone]', $borrower_phone, $template_content);
                    $template_content = str_replace('[Loan Name]', $loan_name, $template_content);
                    $template_content = str_replace('[Loan Number]', $loan_number, $template_content);

                    $characters_to_remove = ['<br>', '&nbsp;'];
                    $template_content = str_replace($characters_to_remove, '', $template_content);
                    // Create a new PhpWord instance
                    $phpWord = new PhpWord();

                // dd($template_content);
                // Add content to the document (agenda, summary, key points, sentiments)
                    $section = $phpWord->addSection();


// // Add an image to the document
// $imagePath = public_path('Logos/logo2.png'); // Adjust the path to your image
// $section->addImage($imagePath, [
//     'width' => 170, // Adjust the width as needed 150
//     'height' => 70, // Adjust the height as needed 50
//     'align' => 'center' // Center align the image
// ]);

// // A TextRun object for applying formatting
// $textRun = $section->addTextRun([
//     'lineHeight' => 1.5 // Line height as a percentage (150% for 1.5 spacing)
// ]);

// // Add formatted text to the TextRun object
// $textRun->addText($template_content, ['name' => 'Arial', 'size' => 12]);




                // \PhpOffice\PhpWord\Shared\Html::addHtml($section, $template_content);
                    \PhpOffice\PhpWord\Shared\Html::addHtml($section, $template_content, false, false);

                // dd($template_content);
                // Agenda (bold, centered, uppercase, font size 14)
                // $section->addText($template_content, ['alignment' => 'center']);

                // Save the document as a Word file

                    $current_year = date('Y');
                    $path = public_path('LOAN_AGREEMENT_FORMS/' . $current_year . '/DOCX');
                    if (!file_exists($path)) {
                        mkdir($path, 0777, true);
                    }
                    $file_name = Str::random(40) . '.docx';

                    $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
                    $objWriter->save($path . '/' . $file_name);
                    $data['loan_agreement_file_path'] = 'LOAN_AGREEMENT_FORMS/' . $current_year . '/DOCX' . '/' . $file_name;
                }

            }




        }



        // Send an SMS to the Client depending on the status of the Loan Stage

        $bulk_sms_config = ThirdParty::where('name', "=", 'SWIFT-SMS')->latest()->get()->first();
        $borrower = Borrower::findOrFail($data['borrower_id']);
        $base_uri = $bulk_sms_config->base_uri ?? '';
        $end_point = $bulk_sms_config->endpoint ?? '';

        if (
            $bulk_sms_config && $bulk_sms_config->is_active == 1 && isset($borrower->mobile)
            && isset($base_uri) && isset($end_point) && isset($bulk_sms_config->token)
            && isset($bulk_sms_config->sender_id)
        ) {


           // Define the JSON data
           $url = $base_uri . $end_point;
           $message = 'Hi ' . $borrower->first_name . ', ';
           $loan_amount = $data['principal_amount'];
           $loan_duration = $data['loan_duration'];
           $loan_release_date = $data['loan_release_date'];
           $loan_repayment_amount = $data['repayment_amount'];
           $loan_interest_amount = $data['interest_amount'];
           $loan_due_date = $data['loan_due_date'] ?? '';
           $loan_number = $data['loan_number'] ?? '';

            // Assuming $data['loan_status'] contains the current status
            $loanStatus = $data['loan_status'];

            switch ($loanStatus) {
                case 'approved':
                    $message .= 'Congratulations! Your loan application of K'.$loan_amount. ' has been approved successfully. The total repayment amount is K'.$loan_repayment_amount .' to be repaid in '.$loan_duration .' '.$loan_cycle;
                    break;

                case 'processing':
                    $message .= 'Your loan application is currently under review. We will notify you once the review process is complete.';
                    break;

                case 'denied':
                    $message .= 'We regret to inform you that your loan application has been rejected.';
                    break;

                case 'defaulted':
                    $message .= 'Unfortunately, your loan is in default status. Please contact us as soon as possible to discuss the situation.';
                    break;

                default:
                    $message .= 'Your loan application is in progress. Current status: ' . $loanStatus;
                    break;
            }


            $jsonDataPayments = [
                "sender_id" => $bulk_sms_config->sender_id,
                "numbers" => $borrower->mobile,
                "message" => $message,

            ];

            // Convert the data to JSON format
            $jsonDataPayments = json_encode($jsonDataPayments);

            Http::withHeaders([
                'Authorization' => 'Bearer ' . $bulk_sms_config->token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout(300)
                ->withBody($jsonDataPayments, 'application/json')
                ->get($url);


        }


// send via Email too if email is not Null
if(!is_null($borrower->email)){
    //dd('email is not null');
    $message = 'Hi ' . $borrower->first_name . ', ';
    $loan_amount = $data['principal_amount'];
    $loan_duration = $data['loan_duration'];
    $loan_release_date = $data['loan_release_date'];
    $loan_repayment_amount = $data['repayment_amount'];
    $loan_interest_amount = $data['interest_amount'];
    $loan_due_date = $data['loan_due_date'];
    $loan_number = $data['loan_number'];

    // Assuming $data['loan_status'] contains the current status
    $loanStatus = $data['loan_status'];

    switch ($loanStatus) {
        case 'approved':
            $message .= 'Congratulations! Your loan application of K' . $loan_amount . ' has been approved successfully. The total repayment amount is K' . $loan_repayment_amount . ' to be repaid in ' . $loan_duration . ' ' . $loan_cycle;
            break;

        case 'processing':
            $message .= 'Your loan application of K' . $loan_amount . ' is currently under review. We will notify you once the review process is complete.';
            break;

        case 'denied':
            $message .= 'We regret to inform you that your loan application of K' . $loan_amount . ' has been rejected.';
            break;

        case 'defaulted':
            $message .= 'Unfortunately, your loan is in default status. Please contact us as soon as possible to discuss the situation.';
            break;



        default:
            $message .= 'Your loan application of K' . $loan_amount . ' is in progress. Current status: ' . $loanStatus;
            break;
    }

    $borrower->notify(new LoanStatusNotification($message));
}


        if($data['loan_status'] === 'approved') {
$wallet->withdraw($data['principal_amount'], ['meta' => 'Loan amount disbursed from ' . $data['from_this_account']]);
}

        $record->update($data);
        app(LoanApprovalAlertService::class)->alertApprovers($record->fresh(['borrower', 'loan_type']), $previousLoanStatus);

        if ($shouldGenerateLoanApplication) {
            $loanApplicationPath = app(LoanApplicationPdfService::class)->generate($record->fresh(['borrower', 'loan_type']));

            $record->forceFill([
                'loan_application_file_path' => $loanApplicationPath,
            ])->saveQuietly();

            Notification::make()
                ->success()
                ->title('Loan application PDF generated')
                ->body('Open the printable loan application, get it signed, then upload the signed copy under Supporting Documents.')
                ->actions([
                    Action::make('open')
                        ->button()
                        ->label('Open PDF')
                        ->url(Storage::disk('public')->url($loanApplicationPath), shouldOpenInNewTab: true),
                ])
                ->send();
        }

        return $record;
    }

    protected function afterSave(): void
    {
        $media = $this->record->getFirstMedia('settlement_documents');

        if (! $media) {
            return;
        }

        $path = method_exists($media, 'getPathRelativeToRoot')
            ? $media->getPathRelativeToRoot()
            : ltrim((string) parse_url($media->getUrl(), PHP_URL_PATH), '/');

        if ($path && $this->record->loan_settlement_file_path !== $path) {
            $this->record->forceFill([
                'loan_settlement_file_path' => $path,
            ])->saveQuietly();
        }
    }
}
