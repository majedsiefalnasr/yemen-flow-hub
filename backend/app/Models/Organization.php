<?php

namespace App\Models;

use App\Models\Concerns\ProtectsSystemRecords;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory, ProtectsSystemRecords;

    protected $fillable = ['code', 'name', 'is_system', 'is_active', 'version'];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function banks(): HasMany
    {
        return $this->hasMany(Bank::class);
    }
}
