<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    public const TYPE_START = 'awal';
    public const TYPE_END = 'akhir';
    public const TYPE_FIELD = 'dinas_luar';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $attributes = ['approval_status' => self::STATUS_PENDING, 'submission_version' => 1];

    protected $fillable = [
        'approval_status', 'reviewed_by', 'reviewed_at', 'rejection_reason',
        'user_id',
        'work_date',
        'type',
        'recorded_at',
        'latitude',
        'longitude',
        'accuracy',
        'address',
        'selfie_path',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'submission_version' => 'integer',
            'reviewed_at' => 'datetime',
            'work_date' => 'date',
            'recorded_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvalLabel(): string
    {
        return match ($this->approval_status) {
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_REJECTED => 'Ditolak',
            default => 'Menunggu persetujuan',
        };
    }

    public static function dayStatus($records, bool $onLeave = false): string
    {
        if ($onLeave) return 'izin';
        if ($records->contains('approval_status', self::STATUS_PENDING)) return 'pending';
        $approved = $records->where('approval_status', self::STATUS_APPROVED)->keyBy('type');
        if ($approved->has(self::TYPE_FIELD)) return 'dinas_luar';
        if ($approved->has(self::TYPE_END)) return 'hadir';
        if ($approved->has(self::TYPE_START)) return 'belum_lengkap';
        if ($records->contains('approval_status', self::STATUS_REJECTED)) return 'rejected';
        return 'alfa';
    }

    public static function labels(): array
    {
        return [
            self::TYPE_START => 'Absen Awal',
            self::TYPE_END => 'Absen Akhir',
            self::TYPE_FIELD => 'Absen Dinas Luar',
        ];
    }

    public function label(): string
    {
        return self::labels()[$this->type] ?? 'Absensi';
    }
}
