<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TraderCompany extends Model
{
    use HasFactory;

    protected $fillable = [
        'trader_id',
        'company_name',
    ];

    public function trader(): BelongsTo
    {
        return $this->belongsTo(Trader::class);
    }
}
