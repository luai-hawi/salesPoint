<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A person working in a shop (HR record). Not necessarily a login account:
 * staff can use the public staff portal with the username/password stored here.
 */
class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_owner_id',
        'name',
        'job_title',
        'monthly_salary',
        'username',
        'password',
        'phone',
        'is_active',
        'portal_enabled',
        'biometric_required',
        'salary_type',
        'hourly_rate',
        'daily_rate',
        'hire_date',
        'schedule',
        'staff_notes',
        'last_portal_login_at',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'password' => 'hashed',
        'monthly_salary' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'daily_rate' => 'decimal:2',
        'is_active' => 'boolean',
        'portal_enabled' => 'boolean',
        'biometric_required' => 'boolean',
        'hire_date' => 'date',
        'schedule' => 'array',
        'last_portal_login_at' => 'datetime',
    ];

    public function getSalaryTypeAttribute($value): string
    {
        return in_array($value, ['monthly', 'hourly', 'daily'], true) ? $value : 'monthly';
    }

    public function getIsActiveAttribute($value): bool
    {
        return $value !== false && $value !== 0 && $value !== '0';
    }

    public function getPortalEnabledAttribute($value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }

    public function shopOwner()
    {
        return $this->belongsTo(User::class, 'shop_owner_id');
    }

    public function remainingSalary()
    {
        return (float) $this->monthly_salary - (float) ($this->amount_taken ?? 0);
    }

    public function payments()
    {
        return $this->hasMany(EmployeePayment::class);
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function devices()
    {
        return $this->hasMany(EmployeeDevice::class);
    }

    public function credentials()
    {
        return $this->hasMany(EmployeeCredential::class);
    }

    public function adjustments()
    {
        return $this->hasMany(EmployeeAdjustment::class);
    }

    public function leaves()
    {
        return $this->hasMany(EmployeeLeave::class);
    }

    public function paidThisMonth()
    {
        return $this->payments()
            ->whereYear('payment_date', now()->year)
            ->whereMonth('payment_date', now()->month)
            ->sum('amount');
    }

    public function remainingThisMonth()
    {
        return $this->monthly_salary - $this->paidThisMonth();
    }
}