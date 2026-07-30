<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportJob extends Model
{
    protected $fillable = [
        'user_id', 'type', 'status', 'total_users', 'processed_users',
        'zip_path', 'zip_name', 'month', 'year', 'current_user_name', 'error_message',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopePending($q)
    {
        return $q->whereIn('status', ['pending', 'processing']);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed']);
    }

    public function downloadUrl(): ?string
    {
        if ($this->status !== 'completed' || !$this->zip_path) {
            return null;
        }

        return route('admin.report-jobs.download', $this);
    }

    public function progressPercent(): int
    {
        if ($this->total_users === 0) {
            return 0;
        }

        return (int) round(($this->processed_users / $this->total_users) * 100);
    }
}