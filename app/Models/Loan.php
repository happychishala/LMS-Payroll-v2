<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Loan extends Model
{
    use HasFactory;


    public function loan_type()
    {

        return $this->belongsTo(LoanType::class, 'loan_type_id','id');
    }

    public function borrower()
    {

        return $this->belongsTo(Borrower::class, 'borrower_id','id');
    }

    public function getLoanDueDateAttribute($value) {
        return date('d,F Y', strtotime($value));
    }
    public function loanType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(LoanType::class, 'loan_type_id');
}



    protected $casts = [
        'activate_loan_agreement_form' => 'boolean',
         'payment' => 'decimal:2',
         'monthly_insurance'         => 'decimal:2',
        'total_monthly_repayment'   => 'decimal:2',
        'disbursement_amount'      => 'decimal:2',

    ];
     /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'balance',
        'loan_status',
         'payment',
          'monthly_insurance',
        'total_monthly_repayment',
        'disbursement_amount',
    ];


}
