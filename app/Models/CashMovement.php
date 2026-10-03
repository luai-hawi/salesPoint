<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CashMovement extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'reason',
        'note',
        'occurred_at',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'occurred_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
