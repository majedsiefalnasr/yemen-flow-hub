<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Permission extends Model
{
    protected $fillable = ['slug', 'name_ar', 'name_en', 'group'];

    public function roles(): array
    {
        return DB::table('role_permissions')
            ->where('permission_id', $this->id)
            ->distinct()
            ->pluck('role')
            ->toArray();
    }
}
