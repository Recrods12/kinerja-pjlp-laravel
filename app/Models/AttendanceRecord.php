<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    public const TYPE_START = 'awal';
    public const TYPE_END = 'akhir';
    public const TYPE_FIELD = 'dinas_luar';

    protected $fillable = [
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
