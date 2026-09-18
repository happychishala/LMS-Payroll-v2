<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanApprovalStepAlert;
use App\Models\User;
use App\Notifications\LoanApprovalStepNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class LoanApprovalAlertService
{
    public function alertApprovers(Loan $loan, ?string $previousStatus = null): void
    {
        if (! config('loan_approval_alerts.enabled', true)) {
            return;
        }

        $status = $this->normalizeStatus($loan->loan_status);
        $previousStatus = $this->normalizeStatus($previousStatus);

        if ($status === '' || $status === $previousStatus) {
            return;
        }

        $step = $this->stepForStatus($status);

        if (! is_array($step)) {
            return;
        }

        $users = $this->usersForRoles($step['roles'] ?? []);
        $emails = $this->directEmails($step['emails'] ?? [], $users);

        if ($users->isEmpty() && empty($emails)) {
            Log::warning('No loan approval alert recipients configured.', [
                'loan_id' => $loan->loan_id,
                'loan_status' => $loan->loan_status,
            ]);

            return;
        }

        $notification = new LoanApprovalStepNotification(
            $loan,
            $step['label'] ?? ucfirst($status),
            $previousStatus ?: null,
        );

        if ($users->isNotEmpty()) {
            Notification::send($users, $notification);
        }

        foreach ($emails as $email) {
            Notification::route('mail', $email)->notify($notification);
        }
    }

    protected function stepForStatus(string $status): ?array
    {
        if (Schema::hasTable('loan_approval_step_alerts')) {
            $step = LoanApprovalStepAlert::query()
                ->where('status', $status)
                ->where('is_active', true)
                ->first();

            if ($step) {
                return [
                    'label' => $step->label,
                    'roles' => $step->role_names ?? [],
                    'emails' => $step->emails ?? [],
                ];
            }
        }

        $step = config("loan_approval_alerts.steps.{$status}");

        return is_array($step) ? $step : null;
    }

    protected function usersForRoles(array $roles): Collection
    {
        $roles = collect($roles)
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

        $missingRoles = array_values(array_diff($roles, $existingRoles));

        if ($missingRoles !== []) {
            Log::warning('Loan approval alert role is not configured.', [
                'roles' => $missingRoles,
            ]);
        }

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

    protected function directEmails(array $emails, Collection $users): array
    {
        $userEmails = $users
            ->pluck('email')
            ->map(fn (string $email): string => strtolower($email))
            ->all();

        return collect($emails)
            ->filter()
            ->map(fn (string $email): string => trim($email))
            ->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->reject(fn (string $email): bool => in_array(strtolower($email), $userEmails, true))
            ->unique(fn (string $email): string => strtolower($email))
            ->values()
            ->all();
    }

    protected function normalizeStatus(?string $status): string
    {
        return strtolower(trim((string) $status));
    }
}
