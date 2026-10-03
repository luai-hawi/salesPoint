<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPayment extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'currency',
        'months',
        'period_start',
        'period_end',
        'paid_at',
        'method',
        'reference',
        'note',
        'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'paid_at' => 'date',
    ];

    public function shop()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
