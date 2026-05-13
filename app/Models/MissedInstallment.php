<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MissedInstallment extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'employee_no',
        'employer',
        'due_month',
        'missed_date',
        'loan_status_at_generation',
        'expected_amount',
        'paid_amount',
        'shortfall_amount',
        'repayment_count',
        'status',
        'remarks',
        'generated_at',
    ];

    protected $casts = [
        'due_month' => 'date',
        'missed_date' => 'date',
        'expected_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'shortfall_amount' => 'decimal:2',
        'repayment_count' => 'integer',
        'generated_at' => 'datetime',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class, 'loan_id', 'loan_id');
    }
}
