<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallmentPlan extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'user_id',
        'customer_id',
        'bill_id',
        'customer_name_override',
        'total_amount',
        'initial_payment',
        'note',
        'is_standalone',
        'created_by',
    ];

    protected $casts = [
        'total_amount'    => 'decimal:2',
        'initial_payment' => 'decimal:2',
        'is_standalone'   => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InstallmentPayment::class)->orderBy('due_date');
    }

    /**
     * Get the display name for the debtor (customer name or override).
     */
    public function getDebtorNameAttribute(): string
    {
        if ($this->customer) {
            return $this->customer->name;
        }
        return $this->customer_name_override ?? __('messages.Unknown');
    }

    /**
     * Total amount paid so far (initial + paid installments).
     */
    public function getPaidAmountAttribute(): float
    {
        $paidInstallments = $this->payments->where('is_paid', true)->sum('amount');
        return (float) $this->initial_payment + (float) $paidInstallments;
    }

    /**
     * Remaining debt.
     */
    public function getRemainingAmountAttribute(): float
    {
        return max(0, (float) $this->total_amount - $this->paid_amount);
    }
}
