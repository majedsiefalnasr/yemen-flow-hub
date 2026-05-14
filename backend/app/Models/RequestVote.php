<?php

namespace App\Models;

use App\Enums\VoteType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestVote extends Model
{
    protected $fillable = [
        'request_id',
        'user_id',
        'vote',
        'justification',
        'is_director_override',
    ];

    protected function casts(): array
    {
        return [
            'vote' => VoteType::class,
            'is_director_override' => 'boolean',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ImportRequest::class, 'request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
