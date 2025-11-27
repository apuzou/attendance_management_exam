<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreakCorrectionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'stamp_correction_request_id',
        'break_time_id',
        'original_break_start',
        'original_break_end',
        'corrected_break_start',
        'corrected_break_end',
    ];

    public function stampCorrectionRequest(): BelongsTo
    {
        return $this->belongsTo(StampCorrectionRequest::class);
    }

    public function breakTime(): BelongsTo
    {
        return $this->belongsTo(BreakTime::class);
    }

    public function isModification(): bool
    {
        return !is_null($this->break_time_id);
    }

    public function isAddition(): bool
    {
        return is_null($this->break_time_id);
    }
}

