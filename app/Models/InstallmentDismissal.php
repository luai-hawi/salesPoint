<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentDismissal extends Model
{
    protected $fillable = [
        'installment_payment_id',
        'user_id',
        'dismissed_for_date',
    ];

    protected $casts = [
        'dismissed_for_date' => 'date',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(InstallmentPayment::class, 'installment_payment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
