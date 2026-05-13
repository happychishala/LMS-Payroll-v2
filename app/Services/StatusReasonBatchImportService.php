<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\StatusReason;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class StatusReasonBatchImportService
{
    public function __construct(
        protected StatusReasonService $statusReasonService,
    ) {
    }

    public function import(string $path, ?User $user = null): array
    {
        $file = new \SplFileObject($path);
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
        $file->setCsvControl(',');

        $file->rewind();
        $rawHeader = $file->fgetcsv();

        if (! is_array($rawHeader) || count(array_filter($rawHeader, fn ($value) => trim((string) $value) !== '')) === 0) {
            throw ValidationException::withMessages([
                'csv_file' => 'CSV header not found.',
            ]);
        }

        $header = array_map([$this, 'normalizeHeader'], $rawHeader);
        $processed = 0;
        $assigned = 0;
        $removed = 0;
        $skipped = 0;
        $errors = [];
        $batchAssignments = [];
        $rowNumber = 1;

        while (! $file->eof()) {
            $rowNumber++;
            $row = $file->fgetcsv();

            if (! is_array($row) || count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $processed++;
            $row = array_pad($row, count($header), null);
            $csv = array_combine($header, array_slice($row, 0, count($header)));

            if (! is_array($csv)) {
                $skipped++;
                $errors[] = "Row {$rowNumber}: invalid CSV structure.";
                continue;
            }

            try {
                $result = $this->processRow($csv, $batchAssignments, $user);

                if ($result === 'removed') {
                    $removed++;
                } elseif ($result === 'assigned' || $result === 'changed') {
                    $assigned++;
                } else {
                    $skipped++;
                }
            } catch (ValidationException $exception) {
                $skipped++;
                $errors[] = "Row {$rowNumber}: " . collect($exception->errors())->flatten()->implode(' ');
            } catch (\Throwable $exception) {
                $skipped++;
                $errors[] = "Row {$rowNumber}: {$exception->getMessage()}";
            }
        }

        return compact('processed', 'assigned', 'removed', 'skipped', 'errors');
    }

    protected function processRow(array $row, array &$batchAssignments, ?User $user = null): string
    {
        $loanId = trim((string) Arr::get($row, 'loan_id'));
        $clientId = trim((string) Arr::get($row, 'client_id'));
        $nrc = trim((string) Arr::get($row, 'nrc'));
        $action = strtoupper(trim((string) Arr::get($row, 'action')));
        $code = strtoupper(trim((string) Arr::get($row, 'status_reason_code')));

        if ($loanId === '' || $clientId === '' || $nrc === '' || $action === '') {
            throw ValidationException::withMessages([
                'row' => 'Loan ID, Client ID, NRC, and Action are required.',
            ]);
        }

        if (! in_array($action, ['ASSIGN', 'REMOVE'], true)) {
            throw ValidationException::withMessages([
                'action' => 'Action must be ASSIGN or REMOVE.',
            ]);
        }

        $loan = Loan::query()
            ->with(['borrower', 'statusReason'])
            ->where('loan_id', $loanId)
            ->first();

        if (! $loan) {
            throw ValidationException::withMessages([
                'loan_id' => 'Loan ID does not exist.',
            ]);
        }

        if ((string) ($loan->borrower?->customer_id ?? '') !== $clientId) {
            throw ValidationException::withMessages([
                'client_id' => 'Loan ID and Client ID do not match.',
            ]);
        }

        if ((string) ($loan->borrower?->identification ?? '') !== $nrc) {
            throw ValidationException::withMessages([
                'nrc' => 'NRC does not match the client record.',
            ]);
        }

        $payload = [
            'effective_date' => $this->normalizeDate(Arr::get($row, 'effective_date')) ?? now()->toDateString(),
            'mode_of_exit' => $this->nullableTrim(Arr::get($row, 'mode_of_exit')),
            'affordability_reason' => $this->nullableTrim(Arr::get($row, 'affordability_drop_reason')),
            'notes' => $this->nullableTrim(Arr::get($row, 'notes')),
            'removal_reason' => $this->nullableTrim(Arr::get($row, 'removal_reason')),
            'management_approval_confirmed' => $this->toBoolean(Arr::get($row, 'management_approval_confirmed')),
        ];

        if ($action === 'REMOVE') {
            return $this->statusReasonService->remove($loan, $payload, $user)->action;
        }

        if ($code === '') {
            throw ValidationException::withMessages([
                'status_reason_code' => 'Status Reason Code is required for ASSIGN.',
            ]);
        }

        $statusReason = StatusReason::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (! $statusReason) {
            throw ValidationException::withMessages([
                'status_reason_code' => 'Status Reason Code is invalid or inactive.',
            ]);
        }

        $batchKey = $loanId . '|' . $code;

        if (isset($batchAssignments[$batchKey])) {
            throw ValidationException::withMessages([
                'status_reason_code' => 'A loan may not have the same code assigned twice in the same upload batch.',
            ]);
        }

        if ((string) optional($loan->statusReason)->code === $code) {
            throw ValidationException::withMessages([
                'status_reason_code' => 'This loan already carries the selected code.',
            ]);
        }

        $event = $this->statusReasonService->assign($loan, $statusReason, $payload, $user);
        $batchAssignments[$batchKey] = true;

        return $event->action;
    }

    protected function normalizeHeader(string|null $value): string
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';

        return trim($value, '_');
    }

    protected function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    protected function normalizeDate(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $formats = ['d/m/Y', 'Y-m-d', 'd-m-Y'];

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $value);

            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        throw ValidationException::withMessages([
            'effective_date' => 'Effective Date must be DD/MM/YYYY, DD-MM-YYYY, or YYYY-MM-DD.',
        ]);
    }

    protected function toBoolean(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'yes', 'true', 'y'], true);
    }
}
