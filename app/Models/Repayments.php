<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Repayments extends Model
{
    use HasFactory;
 protected $guarded = [];

    protected $dates = ['payment_date'];
    protected $casts = [
        // if your column really is named `repayment_date`:
        'repayment_date' => 'date',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
    public function loan_number()
{
    return $this->belongsTo(Loan::class, 'loan_id', 'id');
}

public function getCreatedAtAttribute($value) {
    return date('d,F Y H:m:i', strtotime($value));
}

}
