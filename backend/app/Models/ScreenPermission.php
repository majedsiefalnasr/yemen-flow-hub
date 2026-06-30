<?php

namespace App\Models;

use App\Enums\ScreenCapability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreenPermission extends Model
{
    protected $fillable = ['role_id', 'screen_id', 'capability'];

    protected function casts(): array
    {
        return [
            'capability' => ScreenCapability::class,
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }
}
