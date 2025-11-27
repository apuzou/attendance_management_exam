<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StampCorrectionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_id',
        'user_id',
        'request_date',
        'original_clock_in',
        'original_clock_out',
        'corrected_clock_in',
        'corrected_clock_out',
        'note',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'request_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function breakCorrectionRequests(): HasMany
    {
        return $this->hasMany(BreakCorrectionRequest::class);
    }

    public function isApproved(): bool
    {
        return !is_null($this->approved_at);
    }

    public function isPending(): bool
    {
        return is_null($this->approved_at);
    }
}

