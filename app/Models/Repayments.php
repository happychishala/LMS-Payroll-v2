<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Repayments extends Model
{
    use HasFactory;

    // Allow mass assignment on all fields
    protected $guarded = [];

    // Date columns to be treated as Carbon instances
    protected $dates = [
        'receipt_date',
        'created_at',
        'updated_at',
    ];

    // Attribute casting
    protected $casts = [
        'loan_id'            => 'string',
        'employee_no'        => 'string',
        'receipt_date'       => 'date',
        'payment_date'       => 'date',
        'receipt_amount'     => 'decimal:2',
        'paid_principal'     => 'decimal:2',
        'paid_interest'      => 'decimal:2',
        'insurance_charge'   => 'decimal:2',
        'sanlam'             => 'decimal:2',
        'zed_fin'            => 'decimal:2',
        'insurance_paid'     => 'decimal:2',
        'balance'            => 'decimal:2',
        'repayment_number'   => 'integer',
        'nrc'                => 'string',
    ];

    /**
     * The loan this repayment belongs to.
     */
    public function loan()
    {
        return $this->belongsTo(Loan::class, 'loan_id', 'loan_id');
    }

    /**
     * Alias relationship for loan number lookup.
     */
    public function loan_number()
    {
        return $this->belongsTo(Loan::class, 'loan_id', 'id');
    }

    /**
     * Format created_at for display.
     */
    public function getCreatedAtAttribute($value)
    {
        return date('d, F Y H:i:s', strtotime($value));
    }
}
