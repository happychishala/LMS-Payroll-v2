<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Withholding extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'borrower_id',
        'installment_due_date',
        'installment_amount',
        'withholding_status',
        'remarks',
    ];

   public function loan()
{
    return $this->belongsTo(Loan::class, 'loan_id', 'loan_id');
}

public function borrower()
{
    return $this->belongsTo(Borrower::class, 'borrower_id', 'customer_id');
}

}
