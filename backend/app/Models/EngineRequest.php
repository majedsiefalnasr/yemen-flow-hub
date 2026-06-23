<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EngineRequest extends Model
{
    protected $table = 'engine_requests';

    protected $fillable = [
        'workflow_version_id',
        'current_stage_id',
        'reference',
        'status',
        'created_by',
        'bank_id',
        'merchant_id',
        'data',
        'version',
        'amount',
        'currency',
        'invoice_number',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'version' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'current_stage_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(WorkflowHistoryEntry::class, 'request_id')->orderBy('created_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EngineRequestDocument::class, 'request_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['CLOSED', 'REJECTED'], true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'ACTIVE');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        if ($user->bank_id !== null) {
            return $query->where('bank_id', $user->bank_id);
        }

        return $query;
    }
}
