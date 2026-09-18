<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Loan extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $guarded = [];

    protected $casts = [
        'activate_loan_agreement_form' => 'boolean',
        'exceptional_approval' => 'boolean',
        'payment' => 'decimal:2',
        'monthly_insurance' => 'decimal:2',
        'total_monthly_repayment' => 'decimal:2',
        'disbursement_amount' => 'decimal:2',
        'top_up_amount' => 'decimal:2',
        'top_up_source_total' => 'decimal:2',
        'third_parties' => 'array',
        'loan_release_date' => 'date',
        'date_of_birth' => 'date',
        'top_up_settled_at' => 'date',
    ];

    protected $fillable = [
        'balance',
        'loan_status',
        'loan_application_file_path',
        'payment',
        'monthly_insurance',
        'total_monthly_repayment',
        'disbursement_amount',

        // 👇 Fields required for the Employer CSV Report
        'employer_group',
        'employer_code',
        'borrower_id',
        'employee_number',
        'loan_type_id',
        'instalment_amount',
        'instalment_month',
        'outstanding_principal',
        'outstanding_pi',
        'dia',
        'via',
        'status',
        'cycle',
        'off_payroll',
        'is_insurance',
        'eligible_for_submission',
    ];

    // 📌 Relationships
    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class, 'borrower_id', 'id');
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(Repayments::class, 'loan_id', 'loan_id');
    }

    public function loan_type(): BelongsTo
    {
        return $this->belongsTo(\App\Models\LoanType::class, 'loan_type_id');
    }

    public function statusReason(): BelongsTo
    {
        return $this->belongsTo(\App\Models\StatusReason::class, 'status_reason_id');
    }

    public function statusReasonEvents(): HasMany
    {
        return $this->hasMany(StatusReasonEvent::class, 'loan_record_id');
    }

    public function latestStatusReasonEvent(): HasOne
    {
        return $this->hasOne(StatusReasonEvent::class, 'loan_record_id')->latestOfMany();
    }

    public function scopeActiveOpen($query)
    {
        return $query
            ->whereIn('loan_status', ['approved', 'partially_paid'])
            ->where(function ($query) {
                $query->whereNull('balance')->orWhere('balance', '>', 0);
            });
    }

    // 📌 Payroll Eligibility Scope
  public function scopeEligibleForPayroll($query)
{
    return $query->where('loan_status', 'Active')
                 ->where('balance', '>', 0)
                 ->whereDate('maturity_date', '>=', now())
                 ->where('loan_category', '!=', 'Insurance'); // if applicable
}


    // 📌 Loan Due Date Accessor
    public function getLoanDueDateAttribute()
    {
        if (!$this->loan_release_date || !$this->loan_duration) {
            return null;
        }

        $period = strtolower($this->duration_period ?? 'months');
        $releaseDate = Carbon::parse($this->loan_release_date);

        return match ($period) {
            'months', 'month' => $releaseDate->addMonths((int) $this->loan_duration),
            'days', 'day' => $releaseDate->addDays((int) $this->loan_duration),
            'years', 'year' => $releaseDate->addYears((int) $this->loan_duration),
            default => null
        };
    }

    public function repaymentSchedules(): HasMany
    {
        return $this->hasMany(RepaymentSchedule::class, 'loan_id', 'loan_id');
    }

    public function topUpParentLoan()
    {
        return $this->belongsTo(self::class, 'top_up_parent_loan_id', 'loan_id');
    }

    public function topUpChildLoan()
    {
        return $this->belongsTo(self::class, 'top_up_child_loan_id', 'loan_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('supporting_documents')
            ->acceptsMimeTypes([
                'application/pdf',
                'image/jpeg',
                'image/png',
                'application/zip',
                'application/x-zip-compressed',
            ]);

        $this->addMediaCollection('settlement_documents')
            ->acceptsMimeTypes([
                'application/pdf',
                'image/jpeg',
                'image/png',
                'application/zip',
                'application/x-zip-compressed',
            ]);
    }
}
