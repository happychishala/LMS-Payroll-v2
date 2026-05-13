<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Loan;
use App\Models\Repayments;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use Carbon\Carbon;

class RepaymentImportCorrectionController extends Controller
{
    protected function parseDate(?string $d): ?string
    {
        if (empty($d)) return null;
        $d = trim($d);
        $formats = ['j/n/Y', 'j/n/y', 'd/m/Y', 'd/m/y', 'Y-m-d', 'n/j/Y', 'n/j/y', 'm/d/Y', 'm/d/y'];
        foreach ($formats as $fmt) {
            $parsed = $this->parseDateWithFormat($fmt, $d);
            if ($parsed) {
                return $parsed;
            }
        }
        return null;
    }

    protected function parseDateWithFormat(string $format, string $value): ?string
    {
        try {
            $date = Carbon::createFromFormat($format, $value);
            $errors = Carbon::getLastErrors();

            if ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) {
                return null;
            }

            return $date->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function import(Request $request)
    {
        $invalid = $request->input('invalid', []);
        if (empty($invalid)) {
            return Redirect::back()->with('warning', 'No corrected rows submitted.');
        }

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($invalid as $i => $row) {
            $employeeNo = trim($row['employee_no'] ?? '');
            $amountRaw  = $row['amount'] ?? '';
            $amount     = floatval(str_replace([',', ' '], '', (string)$amountRaw));
            $receiptDate = $this->parseDate($row['receipt_date'] ?? '') ?? now()->format('Y-m-d');
            $nrc = $row['nrc'] ?? '';

            // Basic validation
            if (empty($employeeNo) || $amount <= 0) {
                $errors[] = "Row {$row['row_number']}: invalid employee or amount";
                continue;
            }

            $loans = Loan::where('employee_no', $employeeNo)
                ->whereIn('loan_status', ['approved','partially_paid'])
                ->orderBy('loan_release_date')
                ->get();

            if ($loans->isEmpty()) {
                $errors[] = "Row {$row['row_number']}: no active loans for {$employeeNo}";
                $skipped++;
                continue;
            }

            $remaining = $amount;
            foreach ($loans as $loan) {
                if ($remaining <= 0) break;

                $outstanding = $loan->balance ?? 0;
                $monthlyRepayment = $loan->total_monthly_repayment ?? 0;
                $term = $loan->loan_duration ?? 12;
                $monthsSince = Carbon::parse($loan->loan_release_date)->diffInMonths(Carbon::parse($receiptDate)) + 1;
                $period = min(max(1, $monthsSince), $term);

                $sanlam = round($outstanding * 0.0008, 2);
                $totalInsurancePaid = Repayments::where('loan_id', $loan->loan_id)->sum('insurance_paid');
                $zedfin = max(0, round($sanlam - $totalInsurancePaid, 2));

                $insurancePaid = min($remaining, $sanlam);
                $remaining -= $insurancePaid;

                $rate = $loan->interest_rate / 100 / 12;
                $principalDue = $rate > 0
                    ? abs(ppmt($rate, $period, $term, $outstanding))
                    : ($outstanding / $term);
                $principalPaid = min($remaining, round($principalDue, 2));
                $remaining -= $principalPaid;

                $interestPaid = min($remaining, round($monthlyRepayment - $principalPaid - $insurancePaid, 2));
                $remaining -= $interestPaid;

                $closingBalance = max(0, $outstanding - $principalPaid);

                try {
                    Repayments::create([
                        'loan_id' => $loan->loan_id,
                        'employee_no' => $employeeNo,
                        'nrc' => $nrc,
                        'receipt_date' => $receiptDate,
                        'receipt_amount' => $amount,
                        'paid_principal' => $principalPaid,
                        'paid_interest' => $interestPaid,
                        'insurance_paid' => $insurancePaid,
                        'opening_balance' => $outstanding,
                        'closing_balance' => $closingBalance,
                        'repayment_number' => $period,
                        'monthly_repayment_balance' => $monthlyRepayment,
                        'payment_status' => 'Paid',
                        'sanlam' => $sanlam,
                        'zed_fin' => $zedfin,
                        'interest_rate' => $loan->interest_rate,
                        'loan_amount' => $loan->principal_amount,
                        'term' => $term,
                        'loan_issue_date' => $loan->loan_release_date,
                        'employer' => $loan->borrower->employer ?? '',
                        'balance' => $closingBalance,
                        'payments_method' => 'payroll',
                        'loan_number' => $loan->loan_number,
                        'payment_date' => $receiptDate,
                    ]);
                    $created++;
                } catch (\Exception $e) {
                    Log::error('Repayment import error: '.$e->getMessage());
                    $skipped++;
                }
            }
        }

        // clear invalid rows from session
        Session::forget('invalid_rows');
        Session::forget('valid_rows');

        $msg = "Imported {$created} repayments. Skipped {$skipped} rows.";
        if (!empty($errors)) {
            $msg .= ' Warnings: ' . implode(' | ', array_slice($errors, 0, 5));
        }

        return Redirect::back()->with('success', $msg);
    }
}
