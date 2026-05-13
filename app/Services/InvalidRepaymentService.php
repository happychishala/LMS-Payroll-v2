<?php

namespace App\Services;

use App\Models\InvalidRepayment;
use App\Models\Loan;
use App\Models\Repayments;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InvalidRepaymentService
{
    public function applyCorrection(InvalidRepayment $invalid)
    {
        // re-validate fields
        $employeeNo = trim($invalid->employee_no);
        $amount = (float) preg_replace('/[^\d.-]/','', $invalid->amount_raw);
        $receiptDate = $invalid->receipt_date ?? Carbon::parse($invalid->receipt_date_raw ?? now());

        // find loans
        $loan = Loan::where('employee_no', $employeeNo)
                  ->whereIn('loan_status', ['approved','partially_paid'])
                  ->orderBy('loan_release_date')
                  ->first();

        if (! $loan) {
            $invalid->errors = trim(($invalid->errors ?? '') . " | No active loan found for employee");
            $invalid->save();
            return false;
        }

        // compute repaymentNumber using existing business rules (GRZ/CNMC etc.)
        // [call your existing helper that computes repayment number by loan & receiptDate]
        $repaymentNumber = app(\App\Services\RepaymentScheduleService::class)->computeRepaymentNumber($loan, $receiptDate);
        $repaymentNumber = app(\App\Services\RepaymentAllocationService::class)
            ->resolveRepaymentNumberForOutstandingInterest($loan, $repaymentNumber);

        // determine allocation for that month (insurance->interest->principal)
        $allocation = app(\App\Services\RepaymentAllocationService::class)->allocateForMonth($loan, $amount, $repaymentNumber, $receiptDate);

        // upsert and recompute ledger (use earlier upsertAndRecompute)
        DB::transaction(function () use ($loan, $repaymentNumber, $allocation, $invalid) {
            $rep = app(\App\Services\RepaymentImportService::class)
                ->upsertAndRecompute($loan, $repaymentNumber, $allocation);

            $invalid->status = 'processed';
            $invalid->processed_rep_id = $rep->id ?? null;
            $invalid->processed_at = now();
            $invalid->save();
        });

        return true;
    }
}
