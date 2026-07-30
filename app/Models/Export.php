<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Export extends Model
{
    protected $fillable = ['user_id', 'type', 'file_path', 'file_name', 'status', 'error_message'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function url()
    {
        if ($this->status !== 'completed' || ! $this->file_path) {
            return null;
        }

        return route('admin.exports.download', $this);
    }
}