<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Borrower extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, Notifiable;

    /**
     * Cast and format the created_at attribute for readability.
     */
    public function getCreatedAtAttribute($value)
    {
        return date('d, F Y H:i:s', strtotime($value));
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'borrower_id',
        'customer_id',
        'verification_status',
        'verified_by',
        'verified_at',
        'rejection_reason',
        'first_name',
        'last_name',
        'other_names',
        'gender',
        'dob',
        'age',
        'occupation',
        'identification',
        'mobile',
        'email',
        'address',
        'city',
        'province',
        'zipcode',
        'next_of_kin_first_name',
        'next_of_kin_last_name',
        'phone_next_of_kin',
        'address_next_of_kin',
        'relationship_next_of_kin',
        'next_of_kin_nrc',
        'bank_name',
        'bank_branch',
        'bank_sort_code',
        'bank_account_number',
        'bank_account_type',
        'bank_account_name',
        'mobile_money_name',
        'mobile_money_number',
        'full_name',
        'added_by',
        'mine_number',
        'title',
        'retirement_date',
        'term_date',
        'start_contract',
        'end_contract',
        'contract_type',
        'marital_status',
        'nationality',
        'employer',
        'employer_number',
        'employer_address',
        'employer_position',
        'cycle',
        'attachment',
    ];

    protected $casts = [
        'retirement_date' => 'date',
        'term_date' => 'date',
        'start_contract' => 'date',
        'end_contract' => 'date',
        'verified_at' => 'datetime',
    ];

    /**
     * One borrower can have many files.
     */
    public function files()
    {
        return $this->hasMany(BorrowerFiles::class, 'borrower_id', 'id');
    }

    /**
     * One borrower can have many loans.
     */
    public function loans()
    {
        return $this->hasMany(Loan::class, 'borrower_id', 'id');
    }

    public function created_by()
    {
        return $this->belongsTo(User::class, 'added_by', 'id');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by', 'id');
    }

    public function isPending(): bool
    {
        return $this->verification_status === 'pending';
    }

    /**
     * Register the media collections for the borrower.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
            ->acceptsMimeTypes([
                'application/pdf',
                'image/jpeg',
                'image/png',
                'application/zip',
                'application/x-zip-compressed',
            ]);
    }
}
