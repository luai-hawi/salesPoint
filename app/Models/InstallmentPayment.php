<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallmentPayment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'installment_plan_id',
        'user_id',
        'amount',
        'due_date',
        'is_paid',
        'paid_at',
        'paid_by',
        'note',
    ];

    protected $casts = [
        'amount'   => 'decimal:2',
        'due_date' => 'date',
        'is_paid'  => 'boolean',
        'paid_at'  => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InstallmentPlan::class, 'installment_plan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paidByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function dismissals(): HasMany
    {
        return $this->hasMany(InstallmentDismissal::class);
    }

    /**
     * Whether this payment is dismissed for today by the given user.
     */
    public function isDismissedForToday(int $userId): bool
    {
        return $this->dismissals()
            ->where('user_id', $userId)
            ->where('dismissed_for_date', today())
            ->exists();
    }

    /**
     * Status label: paid | overdue | due_today | upcoming
     */
    public function getStatusAttribute(): string
    {
        if ($this->is_paid) {
            return 'paid';
        }
        if ($this->due_date->isToday()) {
            return 'due_today';
        }
        if ($this->due_date->isPast()) {
            return 'overdue';
        }
        return 'upcoming';
    }
}
