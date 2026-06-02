<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class BalanceMovement extends Model
{
    protected $fillable = [
        'balance_id',
        'amount_added',
        'movement_type',
        'note',
        'resulting_balance',
    ];

    public function balance(): BelongsTo
    {
        return $this->belongsTo(Balance::class);
    }
}
