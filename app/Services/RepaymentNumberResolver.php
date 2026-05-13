<?php

namespace App\Services;

use App\Models\Loan;
use Carbon\Carbon;

class RepaymentNumberResolver
{
    public function expectedFor(Loan $loan, Carbon|string|null $receiptDate): ?int
    {
        if (! $receiptDate) {
            return null;
        }

        try {
            $date = $receiptDate instanceof Carbon
                ? $receiptDate
                : Carbon::parse($receiptDate);
        } catch (\Throwable $e) {
            return null;
        }

        return app(RepaymentScheduleService::class)->computeRepaymentNumber($loan, $date);
    }

    public function resolve(Loan $loan, Carbon|string|null $receiptDate, ?int $uploadedRepaymentNumber): ?int
    {
        $expected = $this->expectedFor($loan, $receiptDate);

        if ($expected === null) {
            return $uploadedRepaymentNumber;
        }

        if ($uploadedRepaymentNumber === $expected) {
            return $uploadedRepaymentNumber;
        }

        return $expected;
    }
}
