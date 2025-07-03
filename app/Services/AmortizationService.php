<?php
namespace App\Services;

use Carbon\Carbon;

class AmortizationService
{
    /**
     * Generate an amortization schedule.
     *
     * @param float  $principal     Loan amount
     * @param float  $annualRate    Annual interest rate (percent)
     * @param int    $termMonths    Loan term in months
     * @param string $startDate     First payment date (Y-m-d)
     * @return array  List of installments [
     *     'period' => 1,
     *     'date'   => '2025-06-13',
     *     'payment'=> 100.00,
     *     'interest'=> 10.00,
     *     'principal'=> 90.00,
     *     'balance'=> 4910.00,
     * ]
     */
    public function generateSchedule(float $principal, float $annualRate, int $termMonths, string $startDate): array
    {
        $monthlyRate = ($annualRate / 100) / 12;
        if ($monthlyRate > 0) {
            $payment = ($monthlyRate * $principal) / (1 - pow(1 + $monthlyRate, -$termMonths));
        } else {
            $payment = $principal / $termMonths;
        }
        $payment = round($payment, 2);

        $schedule = [];
        $balance = $principal;
        $date = Carbon::parse($startDate);

        for ($period = 1; $period <= $termMonths; $period++) {
            $interest  = round($balance * $monthlyRate, 2);
            $principalPaid = round($payment - $interest, 2);
            $balance = round($balance - $principalPaid, 2);

            $schedule[] = [
                'period'    => $period,
                'date'      => $date->toDateString(),
                'payment'   => $payment,
                'interest'  => $interest,
                'principal' => $principalPaid,
                'balance'   => max($balance, 0),
            ];

            $date->addMonth();
        }

        return $schedule;
    }
}
