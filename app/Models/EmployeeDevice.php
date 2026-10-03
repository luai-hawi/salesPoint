<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A phone that stays signed in to the staff portal. Only the hash of its secret token is stored.
 */
class EmployeeDevice extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function revoke(): void
    {
        if ($this->revoked_at === null) {
            $this->forceFill(['revoked_at' => now()])->save();
        }
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}