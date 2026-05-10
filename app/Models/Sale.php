<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Sale extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'is_active'  => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(SaleRule::class);
    }

    /**
     * Is this sale currently active (enabled + within date range)?
     */
    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $today = Carbon::today();

        if ($this->start_date && $today->lt($this->start_date)) {
            return false;
        }

        if ($this->end_date && $today->gt($this->end_date)) {
            return false;
        }

        return true;
    }

    public function getStatusAttribute(): string
    {
        if (! $this->is_active) {
            return 'disabled';
        }

        $today = Carbon::today();

        if ($this->start_date && $today->lt($this->start_date)) {
            return 'scheduled';
        }

        if ($this->end_date && $today->gt($this->end_date)) {
            return 'expired';
        }

        return 'active';
    }
}
