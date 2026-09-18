<?php

namespace App\Notifications;

use App\Filament\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupportTicketCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(protected SupportTicket $ticket)
    {
        $this->ticket->loadMissing('submittedBy');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New IT ticket {$this->ticket->ticket_number}: {$this->ticket->subject}")
            ->greeting('New IT support ticket')
            ->line("Ticket: {$this->ticket->ticket_number}")
            ->line('Submitted by: ' . ($this->ticket->submittedBy?->name ?? 'Unknown user'))
            ->line('Priority: ' . ucfirst($this->ticket->priority))
            ->line('Category: ' . ucfirst(str_replace('_', ' ', $this->ticket->category)))
            ->line($this->ticket->description)
            ->action('Open Ticket', SupportTicketResource::getUrl('edit', ['record' => $this->ticket]));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'subject' => $this->ticket->subject,
        ];
    }
}
