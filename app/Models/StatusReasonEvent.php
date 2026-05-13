<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusReasonEvent extends Model
{
    protected $fillable = [
        'loan_record_id',
        'loan_id',
        'client_id',
        'nrc',
        'previous_status_reason_id',
        'status_reason_id',
        'action',
        'effective_date',
        'mode_of_exit',
        'affordability_reason',
        'management_approval_confirmed',
        'notes',
        'removal_reason',
        'performed_by',
        'performed_by_name',
        'metadata',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'management_approval_confirmed' => 'boolean',
        'metadata' => 'array',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'loan_record_id');
    }

    public function statusReason(): BelongsTo
    {
        return $this->belongsTo(StatusReason::class, 'status_reason_id');
    }

    public function previousStatusReason(): BelongsTo
    {
        return $this->belongsTo(StatusReason::class, 'previous_status_reason_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
