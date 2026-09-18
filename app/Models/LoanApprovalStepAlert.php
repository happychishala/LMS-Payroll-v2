<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanApprovalStepAlert extends Model
{
    protected $guarded = [];

    protected $casts = [
        'role_names' => 'array',
        'emails' => 'array',
        'is_active' => 'boolean',
    ];

    public function setStatusAttribute(?string $value): void
    {
        $this->attributes['status'] = strtolower(trim((string) $value));
    }
}
