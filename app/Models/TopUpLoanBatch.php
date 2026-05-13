<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopUpLoanBatch extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'loan_release_date' => 'date',
        'topup_amount' => 'decimal:2',
        'total_settled_amount' => 'decimal:2',
        'new_principal_amount' => 'decimal:2',
        'source_loan_count' => 'integer',
        'metadata' => 'array',
    ];

    public function items()
    {
        return $this->hasMany(TopUpLoanItem::class);
    }

    public function borrower()
    {
        return $this->belongsTo(Borrower::class);
    }

    public function newLoan()
    {
        return $this->belongsTo(Loan::class, 'new_loan_id', 'loan_id');
    }
}
