<?php

namespace App\Services;

use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketCreatedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

class SupportTicketAlertService
{
    public function notifyIt(SupportTicket $ticket): void
    {
        $users = $this->itUsers();
        $emails = $this->directEmails($users);

        if ($users->isEmpty() && empty($emails)) {
            Log::warning('No IT support ticket recipients configured.', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
            ]);

            return;
        }

        $notification = new SupportTicketCreatedNotification($ticket);

        if ($users->isNotEmpty()) {
            Notification::send($users, $notification);
        }

        foreach ($emails as $email) {
            Notification::route('mail', $email)->notify($notification);
        }
    }

    protected function itUsers(): Collection
    {
        $roles = collect(config('support_tickets.it_roles', []))
            ->filter()
            ->map(fn (string $role): string => trim($role))
            ->unique()
            ->values()
            ->all();

        if ($roles === []) {
            return collect();
        }

        $existingRoles = Role::query()
            ->whereIn('name', $roles)
            ->pluck('name')
            ->all();

        if ($existingRoles === []) {
            return collect();
        }

        return User::role($existingRoles)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get()
            ->unique('email')
            ->values();
    }

    protected function directEmails(Collection $users): array
    {
        $userEmails = $users
            ->pluck('email')
            ->map(fn (string $email): string => strtolower($email))
            ->all();

        return collect(config('support_tickets.it_emails', []))
            ->filter()
            ->map(fn (string $email): string => trim($email))
            ->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->reject(fn (string $email): bool => in_array(strtolower($email), $userEmails, true))
            ->unique(fn (string $email): string => strtolower($email))
            ->values()
            ->all();
    }
}
