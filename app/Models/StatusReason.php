<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StatusReason extends Model
{
    protected $fillable = [
        'code',
        'series',
        'group',
        'label',
        'assignment_condition',
        'notes',
        'suspend_submissions',
        'client_may_replace',
        'suspend_interest',
        'requires_management_approval',
        'triggers_insurance_claim',
        'blocks_new_loan',
        'allows_manager_override',
        'requires_mode_of_exit',
        'requires_affordability_reason',
        'input_options',
        'is_active',
    ];

    protected $casts = [
        'input_options' => 'array',
        'suspend_submissions' => 'boolean',
        'client_may_replace' => 'boolean',
        'suspend_interest' => 'boolean',
        'requires_management_approval' => 'boolean',
        'triggers_insurance_claim' => 'boolean',
        'blocks_new_loan' => 'boolean',
        'allows_manager_override' => 'boolean',
        'requires_mode_of_exit' => 'boolean',
        'requires_affordability_reason' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'status_reason_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(StatusReasonEvent::class);
    }

    public static function modeOfExitOptions(): array
    {
        return [
            'Dismissed' => 'Dismissed',
            'Resigned' => 'Resigned',
            'Seconded' => 'Seconded',
            'Retired' => 'Retired',
            'Contract Not Renewed' => 'Contract Not Renewed',
            'Misconduct' => 'Misconduct',
        ];
    }

    public static function affordabilityReasonOptions(): array
    {
        return [
            'New Third Party - Loan Month' => 'New Third Party - Loan Month',
            'New Third Party - Submission Month' => 'New Third Party - Submission Month',
            'Dropped Housing Allowance' => 'Dropped Housing Allowance',
            'Rural Hardship Allowance Reduction' => 'Rural Hardship Allowance Reduction',
            'Teachers Allowance Reduction' => 'Teachers Allowance Reduction',
            'Other' => 'Other',
        ];
    }
}
