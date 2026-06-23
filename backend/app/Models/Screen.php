<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Screen extends Model
{
    protected $fillable = ['key', 'label'];

    public function screenPermissions(): HasMany
    {
        return $this->hasMany(ScreenPermission::class);
    }
}
