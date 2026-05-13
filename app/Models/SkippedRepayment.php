<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkippedRepayment extends Model
{
    protected $fillable = [
        'csv_file',
        'row_number',
        'import_type',
        'reason',
        'status',
        'loan_id',
        'loan_number',
        'employee_no',
        'batch_no',
        'repayment_number',
        'receipt_date',
        'receipt_amount',
        'reference_number',
        'source_payload',
        'matched_repayment_id',
        'posted_repayment_id',
        'posted_at',
        'post_error',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'receipt_amount' => 'decimal:2',
        'source_payload' => 'array',
        'posted_at' => 'datetime',
    ];
}
