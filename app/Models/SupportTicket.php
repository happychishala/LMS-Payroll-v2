<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SupportTicket extends Model
{
    protected $guarded = [];

    protected $casts = [
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupportTicket $ticket): void {
            if (blank($ticket->ticket_number)) {
                $ticket->ticket_number = static::nextTicketNumber();
            }
        });
    }

    public static function nextTicketNumber(): string
    {
        do {
            $number = 'IT-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5));
        } while (static::query()->where('ticket_number', $number)->exists());

        return $number;
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function setStatusAttribute(?string $value): void
    {
        $this->attributes['status'] = strtolower(trim((string) $value));
    }

    public function setPriorityAttribute(?string $value): void
    {
        $this->attributes['priority'] = strtolower(trim((string) $value));
    }
}
