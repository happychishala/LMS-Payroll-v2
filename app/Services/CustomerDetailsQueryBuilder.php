<?php

namespace App\Services;

use App\Models\Loan;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CustomerDetailsQueryBuilder
{
    /**
     * Build KYC rows with safe fallbacks.
     *
     * @param  string|null $employer
     * @param  bool|null   $offPayrollOnly   If true, only show off-payroll (employee_no null or status like '%off%')
     * @param  string|null $search           Free-text: name / NRC / phone
     * @return \Illuminate\Support\Collection
     */
    public static function build(?string $employer, ?bool $offPayrollOnly, ?string $search): Collection
    {
        $q = Loan::with(['borrower', 'loan_type', 'statusReason'])
            ->orderBy('last_name')
            ->orderBy('other_names');

        if ($employer) {
            $q->where('employer', $employer);
        }

        if ($offPayrollOnly) {
            $q->where(function ($qq) {
                $qq->whereNull('employee_no')
                   ->orWhere('loan_status', 'like', '%off%')
                   ->orWhere('loan_status', 'like', '%closed%');
            });
        }

        if ($search) {
            $needle = '%' . trim($search) . '%';
            $q->where(function ($qq) use ($needle) {
                $qq->where('other_names', 'like', $needle)
                   ->orWhere('last_name', 'like', $needle)
                   ->orWhere('nrc', 'like', $needle)
                   ->orWhereHas('borrower', function ($b) use ($needle) {
                       $b->where('first_name', 'like', $needle)
                         ->orWhere('other_names', 'like', $needle)
                         ->orWhere('last_name', 'like', $needle)
                         ->orWhere('identification', 'like', $needle)
                         ->orWhere('mobile', 'like', $needle);
                   });
            });
        }

        return $q->get()->map(function ($loan) {
            $b = $loan->borrower;

            $borrowerName = trim(collect([
                $b?->other_names ?: $b?->first_name,
                $b?->last_name,
            ])->filter()->implode(' '));

            $loanName = trim(collect([
                $loan->other_names,
                $loan->last_name,
            ])->filter()->implode(' '));

            $dob = $b?->dob ?: $loan->date_of_birth;
            $gender = $b?->gender ?: $loan->gender;
            $nrc = $b?->identification ?: $loan->nrc;
            $address = $b?->address ?: null;
            $phone = $b?->mobile ?: null;
            $employerName = $b?->employer ?: $loan->employer;
            $nextOfKinName = trim(collect([
                $b?->next_of_kin_first_name,
                $b?->next_of_kin_last_name,
            ])->filter()->implode(' '));

            return [
                'Borrower ID' => $b?->customer_id ?: $loan->borrower_id,
                'Borrower Name' => $borrowerName ?: ($loanName ?: 'N/A'),
                'DOB' => static::formatDate($dob),
                'Gender' => $gender,
                'NRC' => $nrc,
                'Address' => $address,
                'Phone' => $phone,
                'Employer' => $employerName,
                'Employee No' => $loan->employee_no ?? null,
                'Next of Kin' => $nextOfKinName ?: null,
                'Next of Kin Phone' => $b?->phone_next_of_kin ?: null,
                'Loan Status' => $loan->loan_status ?? 'N/A',
            ];
        })->values();
    }

    protected static function formatDate($value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
