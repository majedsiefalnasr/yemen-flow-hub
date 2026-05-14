<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestStageHistory extends Model
{
    protected $table = 'request_stage_history';

    protected $fillable = [
        'request_id',
        'from_status',
        'to_status',
        'from_owner_role',
        'to_owner_role',
        'actor_id',
        'actor_role',
        'action',
        'reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => RequestStatus::class,
            'to_status' => RequestStatus::class,
            'from_owner_role' => UserRole::class,
            'to_owner_role' => UserRole::class,
            'actor_role' => UserRole::class,
            'metadata' => 'array',
        ];
    }

    public function importRequest(): BelongsTo
    {
        return $this->belongsTo(ImportRequest::class, 'request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
