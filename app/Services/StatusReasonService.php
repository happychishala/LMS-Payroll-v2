<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\StatusReason;
use App\Models\StatusReasonEvent;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StatusReasonService
{
    public const OTHER_AFFORDABILITY_REASON = 'Other';

    public function assign(Loan $loan, StatusReason $statusReason, array $payload, ?User $user = null): StatusReasonEvent
    {
        $this->validateAssignment($loan, $statusReason, $payload);

        return DB::transaction(function () use ($loan, $statusReason, $payload, $user): StatusReasonEvent {
            $previous = $loan->statusReason;

            $event = StatusReasonEvent::create([
                'loan_record_id' => $loan->id,
                'loan_id' => $loan->loan_id,
                'client_id' => $loan->borrower?->customer_id,
                'nrc' => $loan->borrower?->identification,
                'previous_status_reason_id' => $previous?->id,
                'status_reason_id' => $statusReason->id,
                'action' => $previous ? 'changed' : 'assigned',
                'effective_date' => Arr::get($payload, 'effective_date', now()->toDateString()),
                'mode_of_exit' => Arr::get($payload, 'mode_of_exit'),
                'affordability_reason' => Arr::get($payload, 'affordability_reason'),
                'management_approval_confirmed' => (bool) Arr::get($payload, 'management_approval_confirmed', false),
                'notes' => Arr::get($payload, 'notes'),
                'performed_by' => $user?->id,
                'performed_by_name' => $user?->name,
                'metadata' => [
                    'status_reason_code' => $statusReason->code,
                    'status_reason_label' => $statusReason->label,
                    'previous_status_reason_code' => $previous?->code,
                    'previous_status_reason_label' => $previous?->label,
                ],
            ]);

            $loan->forceFill([
                'status_reason_id' => $statusReason->id,
            ])->save();

            return $event;
        });
    }

    public function remove(Loan $loan, array $payload, ?User $user = null): StatusReasonEvent
    {
        $current = $loan->statusReason;

        if (! $current) {
            throw ValidationException::withMessages([
                'status_reason_id' => 'This loan does not have an active status reason.',
            ]);
        }

        $removalReason = trim((string) Arr::get($payload, 'removal_reason'));

        if ($removalReason === '') {
            throw ValidationException::withMessages([
                'removal_reason' => 'Removal reason is required.',
            ]);
        }

        return DB::transaction(function () use ($loan, $payload, $user, $current, $removalReason): StatusReasonEvent {
            $event = StatusReasonEvent::create([
                'loan_record_id' => $loan->id,
                'loan_id' => $loan->loan_id,
                'client_id' => $loan->borrower?->customer_id,
                'nrc' => $loan->borrower?->identification,
                'previous_status_reason_id' => $current->id,
                'status_reason_id' => null,
                'action' => 'removed',
                'effective_date' => Arr::get($payload, 'effective_date', now()->toDateString()),
                'notes' => Arr::get($payload, 'notes'),
                'removal_reason' => $removalReason,
                'performed_by' => $user?->id,
                'performed_by_name' => $user?->name,
                'metadata' => [
                    'previous_status_reason_code' => $current->code,
                    'previous_status_reason_label' => $current->label,
                ],
            ]);

            $loan->forceFill([
                'status_reason_id' => null,
            ])->save();

            return $event;
        });
    }

    protected function validateAssignment(Loan $loan, StatusReason $statusReason, array $payload): void
    {
        if (! $statusReason->is_active) {
            throw ValidationException::withMessages([
                'status_reason_id' => 'The selected status reason is inactive.',
            ]);
        }

        if ((int) $loan->status_reason_id === (int) $statusReason->id) {
            throw ValidationException::withMessages([
                'status_reason_id' => 'This loan already has the selected status reason.',
            ]);
        }

        if ($statusReason->requires_mode_of_exit && trim((string) Arr::get($payload, 'mode_of_exit')) === '') {
            throw ValidationException::withMessages([
                'mode_of_exit' => 'Mode of exit is required for this status reason.',
            ]);
        }

        if ($statusReason->requires_affordability_reason && trim((string) Arr::get($payload, 'affordability_reason')) === '') {
            throw ValidationException::withMessages([
                'affordability_reason' => 'Affordability drop reason is required for this status reason.',
            ]);
        }

        if (
            Arr::get($payload, 'affordability_reason') === self::OTHER_AFFORDABILITY_REASON
            && trim((string) Arr::get($payload, 'notes')) === ''
        ) {
            throw ValidationException::withMessages([
                'notes' => 'Notes are required when affordability reason is Other.',
            ]);
        }

        if ($statusReason->requires_management_approval && ! Arr::get($payload, 'management_approval_confirmed')) {
            throw ValidationException::withMessages([
                'management_approval_confirmed' => 'Confirm management approval before applying this status reason.',
            ]);
        }
    }
}
