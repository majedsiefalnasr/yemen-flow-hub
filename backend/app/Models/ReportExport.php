<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExport extends Model
{
    protected $fillable = [
        'requested_by',
        'report_type',
        'filters',
        'format',
        'status',
        'file_path',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'version' => 'integer',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
