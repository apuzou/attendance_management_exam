<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'note',
        'last_modified_by',
        'last_modified_at',
    ];

    protected $casts = [
        'date' => 'date',
        'last_modified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function breakTimes(): HasMany
    {
        return $this->hasMany(BreakTime::class);
    }

    public function lastModifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_modified_by');
    }

    public function stampCorrectionRequests(): HasMany
    {
        return $this->hasMany(StampCorrectionRequest::class);
    }

    public function getTotalBreakMinutes(): int
    {
        $totalMinutes = 0;

        foreach ($this->breakTimes as $breakTime) {
            if ($breakTime->break_start && $breakTime->break_end) {
                $start = Carbon::parse($breakTime->break_start);
                $end = Carbon::parse($breakTime->break_end);
                $totalMinutes += $start->diffInMinutes($end);
            }
        }

        return $totalMinutes;
    }

    public function getTotalBreakTime(): string
    {
        $totalMinutes = $this->getTotalBreakMinutes();
        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        return sprintf('%d:%02d', $hours, $minutes);
    }

    public function getWorkMinutes(): int
    {
        if (!$this->clock_in || !$this->clock_out) {
            return 0;
        }

        $clockIn = Carbon::parse($this->clock_in);
        $clockOut = Carbon::parse($this->clock_out);

        $totalMinutes = $clockIn->diffInMinutes($clockOut);
        $breakMinutes = $this->getTotalBreakMinutes();

        return max(0, $totalMinutes - $breakMinutes);
    }

    public function getWorkTime(): string
    {
        $workMinutes = $this->getWorkMinutes();
        $hours = floor($workMinutes / 60);
        $minutes = $workMinutes % 60;

        return sprintf('%d:%02d', $hours, $minutes);
    }
}

