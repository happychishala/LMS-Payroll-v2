<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopUpLoanItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'old_balance' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function batch()
    {
        return $this->belongsTo(TopUpLoanBatch::class, 'top_up_loan_batch_id');
    }

    public function oldLoan()
    {
        return $this->belongsTo(Loan::class, 'old_loan_id', 'loan_id');
    }

    public function settlementRepayment()
    {
        return $this->belongsTo(Repayments::class, 'settlement_repayment_id');
    }
}
