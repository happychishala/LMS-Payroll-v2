<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvalidRepayment extends Model
{
    protected $fillable = [
        'csv_file',
        'row_number',
        'employee_no',
        'name',
        'nrc',
        'amount_raw',
        'amount',
        'receipt_date_raw',
        'receipt_date',
        'errors',
        'status',
        'processed_rep_id',
        'processed_at',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'processed_at' => 'datetime',
    ];
}