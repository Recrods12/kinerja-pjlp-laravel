<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'work_date', 'work_time', 'task', 'note', 'sort_order'])]
class PerformanceEntry extends Model
{
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
