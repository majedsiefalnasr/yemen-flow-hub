<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchedulerRunLog extends Model
{
    protected $fillable = [
        'command',
        'status',
        'affected_count',
        'meta',
        'error_message',
        'ran_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'ran_at' => 'datetime',
            'affected_count' => 'integer',
        ];
    }
}
