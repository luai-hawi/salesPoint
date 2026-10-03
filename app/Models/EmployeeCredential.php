<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A WebAuthn credential (fingerprint / face / device PIN) registered by a staff member.
 */
class EmployeeCredential extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['public_key'];

    protected $casts = [
        'algorithm' => 'integer',
        'sign_count' => 'integer',
        'transports' => 'array',
        'last_used_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}