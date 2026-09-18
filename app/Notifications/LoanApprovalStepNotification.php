<?php

namespace App\Notifications;

use App\Filament\Resources\LoanResource;
use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanApprovalStepNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Loan $loan,
        protected string $stepLabel,
        protected ?string $previousStatus = null,
    ) {
        $this->loan->loadMissing('borrower', 'loan_type');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $borrower = $this->loan->borrower;
        $loanNumber = $this->loan->loan_number ?: $this->loan->loan_id;
        $amount = number_format((float) $this->loan->principal_amount, 2);
        $status = ucfirst((string) $this->loan->loan_status);
        $previousStatus = $this->previousStatus ? ucfirst($this->previousStatus) : 'New loan';

        return (new MailMessage)
            ->subject("Loan {$loanNumber} requires attention: {$this->stepLabel}")
            ->greeting('Loan approval alert')
            ->line("Loan {$loanNumber} is now at: {$this->stepLabel}.")
            ->line("Status changed from {$previousStatus} to {$status}.")
            ->line('Borrower: ' . ($borrower?->full_name ?: trim(($borrower?->first_name ?? '') . ' ' . ($borrower?->last_name ?? '')) ?: 'N/A'))
            ->line('Loan type: ' . ($this->loan->loan_type?->loan_name ?? 'N/A'))
            ->line("Principal amount: ZMW {$amount}")
            ->action('Open Loan', LoanResource::getUrl('edit', ['record' => $this->loan]))
            ->line('Please review the loan and take the required approval action.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'loan_id' => $this->loan->loan_id,
            'loan_number' => $this->loan->loan_number,
            'loan_status' => $this->loan->loan_status,
            'step_label' => $this->stepLabel,
            'previous_status' => $this->previousStatus,
        ];
    }
}
