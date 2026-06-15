<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'username',
    'email',
    'role',
    'nip',
    'nik',
    'jabatan',
    'security_team',
    'security_cycle_start_date',
    'unit',
    'phone',
    'address',
    'signature_path',
    'annual_leave_quota',
    'annual_leave_remaining',
    'password',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'security_cycle_start_date' => 'date',
            'password' => 'hashed',
        ];
    }

    public function performanceEntries()
    {
        return $this->hasMany(PerformanceEntry::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function approvedBookings()
    {
        return $this->hasMany(Booking::class, 'approved_by');
    }

    public function usesSecurityShift(): bool
    {
        return $this->jabatan === 'Keamanan'
            && in_array($this->security_team, ['A', 'B', 'C'], true)
            && filled($this->security_cycle_start_date);
    }

    public function isScheduledWorkday(Carbon $date, array $holidayDates = []): bool
    {
        if ($this->usesSecurityShift()) {
            $startDate = Carbon::parse($this->security_cycle_start_date)->startOfDay();
            $diff = (int) $startDate->diffInDays($date->copy()->startOfDay(), false);
            $cycleIndex = (($diff % 3) + 3) % 3;
            $teamIndex = ['A' => 0, 'B' => 1, 'C' => 2][$this->security_team];

            return $cycleIndex === $teamIndex;
        }

        return ! $date->isWeekend() && ! in_array($date->toDateString(), $holidayDates, true);
    }
}
