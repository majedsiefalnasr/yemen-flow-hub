<?php

namespace App\Models;

use App\Enums\StageAccessLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StagePermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'stage_id',
        'organization_id',
        'team_id',
        'role_id',
        'user_id',
        'access_level',
        'display_label',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'access_level' => StageAccessLevel::class,
            'version' => 'integer',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'stage_id');
    }
}
