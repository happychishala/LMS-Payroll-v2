<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Repayments;

class LoanBalanceService
{
    public function resolveOutstandingBalance(Loan $loan): float
    {
        $latestClosingBalance = Repayments::query()
            ->where('loan_id', $loan->loan_id)
            ->whereNotNull('closing_balance')
            ->orderByDesc('receipt_date')
            ->orderByDesc('id')
            ->value('closing_balance');

        if ($latestClosingBalance !== null) {
            return round((float) $latestClosingBalance, 2);
        }

        $loanBalance = $loan->balance;
        if ($loanBalance !== null && is_numeric($loanBalance)) {
            return round((float) $loanBalance, 2);
        }

        return $this->derivePrincipalOutstanding($loan);
    }

    public function derivePrincipalOutstanding(Loan $loan): float
    {
        $principal = round((float) ($loan->principal_amount ?? 0), 2);
        $paidPrincipal = round((float) Repayments::query()
            ->where('loan_id', $loan->loan_id)
            ->sum('paid_principal'), 2);

        return round(max(0, $principal - $paidPrincipal), 2);
    }

    public function applyPrincipalPayment(Loan $loan, float $paidPrincipal): array
    {
        $openingBalance = $this->resolveOutstandingBalance($loan);
        $paidPrincipal = round(max(0, $paidPrincipal), 2);
        $closingBalance = round(max(0, $openingBalance - $paidPrincipal), 2);

        return [
            'opening_balance' => $openingBalance,
            'paid_principal' => $paidPrincipal,
            'closing_balance' => $closingBalance,
            'should_close' => $closingBalance <= 0.009,
        ];
    }
}
